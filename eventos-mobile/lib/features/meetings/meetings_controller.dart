import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:permission_handler/permission_handler.dart';

import '../../models/lounge_model.dart';
import '../../models/meeting_model.dart';
import '../../utils/enum/enums.dart';
import '../../utils/helpers/helper_functions.dart';
import '../../utils/helpers/toast_msg.dart';
import '../lounge/pages/lounge_room_view.dart';
import 'meeting_time_utils.dart';
import 'meetings_service.dart';
import 'pages/request_meeting_form_view.dart';
import 'pages/request_meeting_people_view.dart';

class MeetingsController extends GetxController {
  final _service = MeetingsService();

  final dataStatus = ApiState.initial.obs;
  final meetings = <Meeting>[].obs;
  final capabilities = Rxn<MeetingCapabilities>();
  final adImageUrl = ''.obs;

  final searchKey = ''.obs;
  final searchController = TextEditingController();
  final showPast = false.obs;
  final sortType = 'newest'.obs;
  final selectedStatuses = <String>[].obs;
  final selectedDirection = 'all'.obs;

  final actingIds = <String>{}.obs;
  final joiningId = ''.obs;

  // ── Request flow state ─────────────────────────────────────────────────
  final partners = <MeetingPartner>[].obs;
  final partnersLoading = false.obs;
  final partnerSearch = ''.obs;
  final partnerSearchController = TextEditingController();
  final roleFilter = ''.obs;
  final selectedPartner = Rxn<MeetingPartner>();

  final titleController = TextEditingController();
  final agendaController = TextEditingController();
  final locationText = ''.obs;
  final selectedDate = ''.obs;
  final selectedSlot = ''.obs;
  final fallbackDate = ''.obs;
  final fallbackTime = ''.obs;
  final availability = Rxn<LoungeAvailability>();
  final slotsLoading = false.obs;
  final sending = false.obs;
  final formError = ''.obs;
  final agendaLength = 0.obs;

  Timer? _partnerSearchTimer;
  bool _loadedOnce = false;

  bool get canRequest {
    final caps = capabilities.value;
    if (caps == null) return true;
    if (!caps.enabled) return false;
    if (caps.allowedRoles.isEmpty) return false;
    return caps.canRequest;
  }

  List<String> get allowedRoles =>
      capabilities.value?.allowedRoles ??
      const ['attendee', 'speaker', 'exhibitor', 'sponsor'];

  List<Meeting> get filteredMeetings {
    final q = searchKey.value.trim().toLowerCase();
    final now = DateTime.now().millisecondsSinceEpoch;
    final statuses = selectedStatuses.toList();
    final direction = selectedDirection.value;

    var list = meetings.where((m) {
      if (!showPast.value) {
        final end = meetingEndMs(m);
        if (end != null && end < now) return false;
      }
      if (direction != 'all' && m.direction != direction) return false;
      if (statuses.isNotEmpty) {
        final bucket = (m.status == 'declined' || m.status == 'canceled')
            ? 'rejected'
            : m.status;
        if (!statuses.contains(bucket)) return false;
      }
      if (q.isEmpty) return true;
      final title = (m.title ?? '').toLowerCase();
      final agenda = (m.agenda ?? '').toLowerCase();
      final name = (m.counterpart?.name ?? '').toLowerCase();
      final loc = m.displayLocation.toLowerCase();
      return title.contains(q) ||
          agenda.contains(q) ||
          name.contains(q) ||
          loc.contains(q);
    }).toList();

    switch (sortType.value) {
      case 'oldest':
        list.sort((a, b) => (a.createdAt ?? '').compareTo(b.createdAt ?? ''));
        break;
      case 'name':
        list.sort(
          (a, b) => (a.counterpart?.name ?? '')
              .toLowerCase()
              .compareTo((b.counterpart?.name ?? '').toLowerCase()),
        );
        break;
      default:
        list.sort((a, b) => (b.createdAt ?? '').compareTo(a.createdAt ?? ''));
    }
    return list;
  }

  List<String> get slotDates {
    final a = availability.value;
    if (a == null) return const [];
    return a.dates.where((d) => (a.slots[d]?.isNotEmpty ?? false)).toList();
  }

  bool get useSlots {
    final a = availability.value;
    return a != null && a.enabled && slotDates.isNotEmpty;
  }

  bool get isIntelligent {
    final a = availability.value;
    return a?.intelligent == true || capabilities.value?.intelligent == true;
  }

  bool get isPhysical {
    final format = availability.value?.format ?? '';
    return format == 'venue' || format == 'hybrid';
  }

  bool get needsLocation =>
      !isIntelligent && (availability.value?.locationRequired == true);

  List<String> get locationOptions => availability.value?.locations ?? const [];

  List<String> get daySlots {
    final a = availability.value;
    if (a == null) return const [];
    return a.slots[selectedDate.value] ?? const [];
  }

  bool isBusy(String slot) {
    final a = availability.value;
    if (a == null) return false;
    final key = '${selectedDate.value}|$slot';
    return a.busy.any((b) => '${b.date}|${b.slot}' == key);
  }

  @override
  void onClose() {
    _partnerSearchTimer?.cancel();
    searchController.dispose();
    partnerSearchController.dispose();
    titleController.dispose();
    agendaController.dispose();
    super.onClose();
  }

  void setSearchKey(String val) => searchKey.value = val;

  void clearSearch() {
    searchController.clear();
    searchKey.value = '';
  }

  void setSortType(String key) => sortType.value = key;

  void setShowPast(bool value) => showPast.value = value;

  void setDirection(String value) => selectedDirection.value = value;

  void toggleStatus(String status) {
    if (selectedStatuses.contains(status)) {
      selectedStatuses.remove(status);
    } else {
      selectedStatuses.add(status);
    }
  }

  void clearFilters() {
    selectedStatuses.clear();
    selectedDirection.value = 'all';
  }

  Future<void> fetchMeetings({bool silent = false}) async {
    if (silent && _loadedOnce) {
      try {
        await _loadMeetings();
        await _loadCapabilities();
      } catch (_) {}
      return;
    }

    await handleApiClient(
      onStateChanged: (state) => dataStatus(state),
      handleApiCall: () async {
        await Future.wait([_loadMeetings(), _loadCapabilities()]);
      },
    );
    _fetchAds();
  }

  Future<void> _loadMeetings() async {
    final response = await _service.getMeetings();
    final body = response.data;
    if (body is! Map) return;
    final raw = body['data'];
    final list = raw is List ? raw : const [];
    meetings.assignAll(
      list
          .whereType<Map>()
          .map((e) => Meeting.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
    );
    _loadedOnce = true;
  }

  Future<void> _loadCapabilities() async {
    try {
      final response = await _service.getCapabilities();
      final body = response.data;
      if (body is! Map) return;
      final raw = body['data'];
      if (raw is Map) {
        capabilities.value =
            MeetingCapabilities.fromJson(Map<String, dynamic>.from(raw));
      }
    } catch (_) {
      // Capabilities are optional for viewing the list.
    }
  }

  Future<void> _fetchAds() async {
    try {
      final response = await _service.getAds();
      final body = response.data;
      if (body is! Map) return;
      final data = body['data'];
      if (data is! Map) return;
      final strip = data['strip'];
      if (strip is! List || strip.isEmpty) return;
      final first = strip.first;
      if (first is Map) {
        final url = (first['image_url'] ?? first['image'] ?? '').toString();
        if (url.isNotEmpty) adImageUrl.value = url;
      }
    } catch (_) {}
  }

  Future<void> respond(Meeting meeting, String action) async {
    if (actingIds.contains(meeting.id)) return;
    actingIds.add(meeting.id);
    actingIds.refresh();
    try {
      final response = await _service.respond(meeting.id, action);
      final body = response.data;
      if (body is Map && body['data'] is Map) {
        final updated =
            Meeting.fromJson(Map<String, dynamic>.from(body['data'] as Map));
        final i = meetings.indexWhere((m) => m.id == meeting.id);
        if (i != -1) meetings[i] = updated;
      }
      if (action == 'accept') {
        await _loadCapabilities();
      }
    } on DioException catch (e) {
      ToastMsg.showApiErrorMessage(e);
    } catch (e) {
      ToastMsg.showErrorMessage(e.toString());
    } finally {
      actingIds.remove(meeting.id);
      actingIds.refresh();
    }
  }

  Future<void> joinMeeting(Meeting meeting) async {
    if (joiningId.value.isNotEmpty) return;

    final cam = await Permission.camera.request();
    final mic = await Permission.microphone.request();
    if (!cam.isGranted && !mic.isGranted) {
      ToastMsg.showErrorMessage(
        'Camera or microphone permission is required to join a meeting.',
      );
      return;
    }

    joiningId.value = meeting.id;
    try {
      final response = await _service.join(meeting.id);
      final body = response.data;
      if (body is! Map) throw Exception('Unexpected join response');
      final data = body['data'] is Map
          ? Map<String, dynamic>.from(body['data'] as Map)
          : Map<String, dynamic>.from(body);

      final url = (data['url'] ?? '').toString();
      final token = (data['token'] ?? '').toString();
      if (url.isEmpty || token.isEmpty) {
        throw Exception('Join did not return a media token');
      }

      final config = LoungeJoinConfig(
        provider: (data['provider'] ?? 'webrtc').toString(),
        url: url,
        room: (data['room'] ?? '').toString(),
        token: token,
        title: (data['title'] ?? meeting.title ?? 'Meeting').toString(),
      );

      await Get.to(
        () => LoungeRoomView(config: config),
        transition: Transition.fadeIn,
      );
      await fetchMeetings(silent: true);
    } on DioException catch (e) {
      ToastMsg.showApiErrorMessage(e);
    } catch (e) {
      ToastMsg.showErrorMessage(e.toString());
    } finally {
      joiningId.value = '';
    }
  }

  void openRequestMeeting() {
    if (!canRequest) {
      ToastMsg.showErrorMessage(
        'You have reached the maximum number of meeting requests.',
      );
      return;
    }
    _resetRequestForm();
    Get.to(() => const RequestMeetingPeopleView());
    fetchPartners();
  }

  /// Skip the people picker and open the request form with [partner] selected.
  Future<void> requestMeetingWith(MeetingPartner partner) async {
    if (!canRequest) {
      ToastMsg.showErrorMessage(
        'You have reached the maximum number of meeting requests.',
      );
      return;
    }
    _resetRequestForm();
    await choosePartner(partner);
    Get.to(() => const RequestMeetingFormView());
  }

  void _resetRequestForm() {
    selectedPartner.value = null;
    titleController.clear();
    agendaController.clear();
    locationText.value = '';
    selectedDate.value = '';
    selectedSlot.value = '';
    fallbackDate.value = '';
    fallbackTime.value = '';
    availability.value = null;
    formError.value = '';
    agendaLength.value = 0;
    partnerSearchController.clear();
    partnerSearch.value = '';
    roleFilter.value = '';
  }

  void setPartnerSearch(String val) {
    partnerSearch.value = val;
    _partnerSearchTimer?.cancel();
    _partnerSearchTimer = Timer(const Duration(milliseconds: 300), fetchPartners);
  }

  void clearPartnerSearch() {
    partnerSearchController.clear();
    partnerSearch.value = '';
    fetchPartners();
  }

  void setRoleFilter(String role) {
    roleFilter.value = roleFilter.value == role ? '' : role;
    fetchPartners();
  }

  Future<void> fetchPartners() async {
    partnersLoading.value = true;
    try {
      final response = await _service.getPartners(
        q: partnerSearch.value,
        role: roleFilter.value.isEmpty ? null : roleFilter.value,
      );
      final body = response.data;
      if (body is! Map) return;
      final raw = body['data'];
      final list = raw is List ? raw : const [];
      partners.assignAll(
        list
            .whereType<Map>()
            .map((e) => MeetingPartner.fromJson(Map<String, dynamic>.from(e)))
            .toList(),
      );
      final roles = body['roles'];
      if (roles is List && capabilities.value != null) {
        capabilities.value = capabilities.value!.copyWith(
          allowedRoles: roles.map((e) => e.toString()).toList(),
        );
      }
    } catch (_) {
      partners.clear();
    } finally {
      partnersLoading.value = false;
    }
  }

  Future<void> choosePartner(MeetingPartner partner) async {
    selectedPartner.value = partner;
    titleController.text = 'Meeting with ${partner.name}';
    selectedDate.value = '';
    selectedSlot.value = '';
    fallbackDate.value = '';
    fallbackTime.value = '';
    locationText.value = '';
    formError.value = '';
    agendaController.clear();
    agendaLength.value = 0;

    slotsLoading.value = true;
    availability.value = null;
    try {
      final response = await _service.getLoungeAvailability(withId: partner.id);
      final body = response.data;
      if (body is Map && body['data'] is Map) {
        availability.value = LoungeAvailability.fromJson(
          Map<String, dynamic>.from(body['data'] as Map),
        );
        if (slotDates.isNotEmpty) {
          selectedDate.value = slotDates.first;
        }
        if (needsLocation && locationOptions.length == 1) {
          locationText.value = locationOptions.first;
        }
      }
    } catch (_) {
      availability.value = null;
    } finally {
      slotsLoading.value = false;
    }
  }

  void pickSlot(String slot) {
    if (isBusy(slot)) return;
    selectedSlot.value = selectedSlot.value == slot ? '' : slot;
  }

  void pickDate(String date) {
    selectedDate.value = date;
    selectedSlot.value = '';
  }

  void pickLocation(String place) {
    locationText.value = locationText.value == place ? '' : place;
  }

  void onAgendaChanged(String value) {
    agendaLength.value = value.length;
  }

  Future<bool> submitRequest() async {
    final partner = selectedPartner.value;
    if (partner == null) return false;
    formError.value = '';

    if (!canRequest) {
      formError.value = 'You have reached the maximum number of meeting requests.';
      return false;
    }

    if (isIntelligent && isPhysical) {
      if (!useSlots) {
        formError.value = 'No meeting slots are available right now.';
        return false;
      }
      if (selectedSlot.value.isEmpty) {
        formError.value = 'Pick an available time slot.';
        return false;
      }
    } else if (useSlots && selectedSlot.value.isEmpty) {
      formError.value = 'Pick an available time slot.';
      return false;
    }

    if (needsLocation && locationText.value.trim().isEmpty) {
      formError.value = locationOptions.isNotEmpty
          ? 'Choose where you want to meet.'
          : 'Enter where you want to meet, e.g. Hall 4.';
      return false;
    }

    String? startsAtIso;
    if (!useSlots &&
        (fallbackDate.value.isNotEmpty || fallbackTime.value.isNotEmpty)) {
      if (fallbackDate.value.isEmpty || fallbackTime.value.isEmpty) {
        formError.value = 'Pick both a date and a time.';
        return false;
      }
      final candidate =
          DateTime.tryParse('${fallbackDate.value}T${fallbackTime.value}');
      if (candidate == null || candidate.isBefore(DateTime.now())) {
        formError.value = 'Pick a time that is now or later.';
        return false;
      }
      startsAtIso = candidate.toUtc().toIso8601String();
    }

    sending.value = true;
    try {
      final body = <String, dynamic>{
        'invitees': [partner.id],
        'title': titleController.text.trim().isEmpty
            ? null
            : titleController.text.trim(),
        'agenda': agendaController.text.trim().isEmpty
            ? null
            : agendaController.text.trim(),
        'location': needsLocation ? locationText.value.trim() : null,
        'type': 'one_on_one',
      };

      if (useSlots || (isIntelligent && isPhysical)) {
        body['date'] = selectedDate.value;
        body['slot'] = selectedSlot.value;
      } else {
        body['starts_at'] = startsAtIso;
      }

      final response = await _service.createMeeting(body);
      final resBody = response.data;
      if (resBody is Map && resBody['data'] is Map) {
        final created = Meeting.fromJson(
          Map<String, dynamic>.from(resBody['data'] as Map),
        );
        meetings.insert(0, created);
      }

      final caps = capabilities.value;
      if (caps != null) {
        final used = caps.requestsUsed + 1;
        final max = caps.requestsMax;
        capabilities.value = caps.copyWith(
          requestsUsed: used,
          canRequest: max == null || used < max,
        );
      }

      ToastMsg.showSuccessMessage('Meeting request sent.');
      return true;
    } on DioException catch (e) {
      formError.value = _extractError(e) ??
          'Could not send the request. Please try again.';
      if (useSlots && partner.id.isNotEmpty) {
        try {
          final response =
              await _service.getLoungeAvailability(withId: partner.id);
          final body = response.data;
          if (body is Map && body['data'] is Map) {
            availability.value = LoungeAvailability.fromJson(
              Map<String, dynamic>.from(body['data'] as Map),
            );
            if (selectedSlot.value.isNotEmpty && isBusy(selectedSlot.value)) {
              selectedSlot.value = '';
            }
          }
        } catch (_) {}
      }
      return false;
    } catch (e) {
      formError.value = e.toString();
      return false;
    } finally {
      sending.value = false;
    }
  }

  String? _extractError(DioException e) {
    final data = e.response?.data;
    if (data is Map) {
      final msg = data['message']?.toString();
      if (msg != null && msg.isNotEmpty) return msg;
    }
    return null;
  }
}
