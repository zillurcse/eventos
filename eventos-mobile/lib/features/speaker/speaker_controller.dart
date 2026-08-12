import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../models/speaker_model.dart';
import '../../models/session_model.dart';
import '../../models/mappers/speaker_mapper.dart';
import '../../utils/enum/enums.dart';
import '../../utils/helpers/helper_functions.dart';
import '../../utils/helpers/toast_msg.dart';
import '../../utils/helpers/type_helper.dart';
import '../bookmarks/bookmark_controller.dart';
import '../chat/chat_controller.dart';
import 'speaker_service.dart';

class SpeakerController extends GetxController {
  final _service = SpeakerService();

  final dataStatus = ApiState.initial.obs;
  final Rx<SpeakerPageModel> speakerPage = const SpeakerPageModel().obs;
  final RxnInt expandedSpeakerId = RxnInt();

  final RxString searchKey = "".obs;
  final TextEditingController searchController = TextEditingController();
  final RxnString sortType = RxnString();

  // Client-side filters (mirrors delegates Advance Filter)
  final savedOnly = false.obs;
  final activeCompanies = <String>[].obs;
  final activeTitles = <String>[].obs;

  List<Map<String, dynamic>> _allRawSpeakers = [];
  List<Map<String, dynamic>> _allRawSessions = [];

  List<SpeakerItemModel> get speakers => speakerPage.value.speakers;
  SpeakerAdModel? get contentAd => speakerPage.value.contentAd;
  SpeakerAdModel? get featuredAd => speakerPage.value.featuredAd;

  bool get hasActiveFilters =>
      savedOnly.value ||
      activeCompanies.isNotEmpty ||
      activeTitles.isNotEmpty;

  List<String> get companyOptions => _uniqueTop(
        _allRawSpeakers.map((s) => (s['company'] ?? '').toString()),
        12,
      );

  List<String> get titleOptions => _uniqueTop(
        _allRawSpeakers.map((s) => (s['designation'] ?? '').toString()),
        12,
      );

  List<SpeakerItemModel> get filteredSpeakers {
    final saved = savedOnly.value;

    if (saved && Get.isRegistered<BookmarkController>()) {
      Get.find<BookmarkController>().bookmarkedSpeakers.length;
    }

    if (!saved) return speakers;

    return speakers.where((s) {
      if (!Get.isRegistered<BookmarkController>()) return false;
      return Get.find<BookmarkController>().isOnHashed('speaker', s.id);
    }).toList();
  }

  static List<String> _uniqueTop(Iterable<String> values, int limit) {
    final seen = <String, int>{};
    for (final v in values) {
      final t = v.trim();
      if (t.isEmpty) continue;
      seen[t] = (seen[t] ?? 0) + 1;
    }
    final entries = seen.entries.toList()
      ..sort((a, b) => b.value.compareTo(a.value));
    return entries.take(limit).map((e) => e.key).toList();
  }

  void toggleSavedOnly() => savedOnly.value = !savedOnly.value;

  void toggleCompanyFilter(String company) {
    if (activeCompanies.contains(company)) {
      activeCompanies.remove(company);
    } else {
      activeCompanies.add(company);
    }
    _applyFilters();
  }

  void toggleTitleFilter(String title) {
    if (activeTitles.contains(title)) {
      activeTitles.remove(title);
    } else {
      activeTitles.add(title);
    }
    _applyFilters();
  }

  void clearFilters() {
    savedOnly.value = false;
    activeCompanies.clear();
    activeTitles.clear();
    _applyFilters();
  }

  @override
  void onInit() {
    super.onInit();
    debounce(
      searchKey,
      (_) => _applyFilters(),
      time: const Duration(milliseconds: 500),
    );
  }

  void setSearchKey(String val) {
    searchKey.value = val;
  }

  void clearSearch() {
    searchController.clear();
    searchKey.value = "";
  }

  void setSortType(String? type) {
    sortType.value = type;
    _applyFilters();
  }

  final detailStatus = ApiState.initial.obs;
  final Rx<SpeakerDetailModel?> speakerDetail = Rx<SpeakerDetailModel?>(null);
  final RxList<SessionModel> speakerSessions = <SessionModel>[].obs;

  Future<void> fetchSpeakers() async {
    await handleApiClient(
      onStateChanged: (state) => dataStatus(state),
      handleApiCall: () async {
        final response = await _service.getSpeakers();
        final body = response.data;
        if (body is! Map) return;

        final payload = body['data'] is Map
            ? Map<String, dynamic>.from(body['data'] as Map)
            : Map<String, dynamic>.from(body);

        _allRawSpeakers = (payload['speakers'] as List? ?? [])
            .whereType<Map>()
            .map((e) => Map<String, dynamic>.from(e))
            .toList();

        _applyFilters();
      },
    );
  }

  void _applyFilters() {
    final filtered = SpeakerMapper.filterRaw(
      speakers: _allRawSpeakers,
      search: searchKey.value,
      sortBy: sortType.value,
      companies: activeCompanies.isNotEmpty ? activeCompanies.toList() : null,
      titles: activeTitles.isNotEmpty ? activeTitles.toList() : null,
    );
    final page = SpeakerMapper.pageFromV1({'speakers': filtered});
    speakerPage.value = _withBookmarkState(page);
  }

  SpeakerPageModel _withBookmarkState(SpeakerPageModel page) {
    if (!Get.isRegistered<BookmarkController>()) return page;
    final bm = Get.find<BookmarkController>();
    return SpeakerPageModel(
      speakers: page.speakers.map((s) {
        final loved = bm.isOnHashed('speaker', s.id);
        if (s.isLoved == loved) return s;
        return SpeakerItemModel(
          id: s.id,
          name: s.name,
          image: s.image,
          designation: s.designation,
          categoryId: s.categoryId,
          category: s.category,
          presentationTitle: s.presentationTitle,
          isLoved: loved,
          haveNotes: s.haveNotes,
          notes: s.notes,
        );
      }).toList(),
      featuredAd: page.featuredAd,
      contentAd: page.contentAd,
    );
  }

  Future<void> fetchSpeakerDetail(int id) async {
    speakerDetail.value = null;
    speakerSessions.clear();
    await handleApiClient(
      onStateChanged: (state) => detailStatus(state),
      handleApiCall: () async {
        if (_allRawSpeakers.isEmpty) {
          final response = await _service.getSpeakers();
          final body = response.data;
          if (body is Map) {
            final payload = body['data'] is Map
                ? Map<String, dynamic>.from(body['data'] as Map)
                : Map<String, dynamic>.from(body);
            _allRawSpeakers = (payload['speakers'] as List? ?? [])
                .whereType<Map>()
                .map((e) => Map<String, dynamic>.from(e))
                .toList();
          }
        }

        Map<String, dynamic>? raw;
        for (final s in _allRawSpeakers) {
          if (TypeHelper.toInt(s['id']) == id) {
            raw = s;
            break;
          }
        }
        if (raw == null) {
          throw Exception('Speaker not found');
        }

        if (_allRawSessions.isEmpty) {
          try {
            final sessionsResponse = await _service.getSessions();
            final body = sessionsResponse.data;
            if (body is Map) {
              final payload = body['data'] is Map
                  ? Map<String, dynamic>.from(body['data'] as Map)
                  : Map<String, dynamic>.from(body);
              final sessionsRaw = payload['sessions'];
              final list = sessionsRaw is Map && sessionsRaw['data'] is List
                  ? sessionsRaw['data'] as List
                  : (sessionsRaw as List? ?? []);
              _allRawSessions = list
                  .whereType<Map>()
                  .map((e) => Map<String, dynamic>.from(e))
                  .toList();
            }
          } catch (_) {}
        }

        final sessions = SpeakerMapper.sessionsForSpeaker(
          allSessions: _allRawSessions,
          speakerId: id,
        );

        var detail = SpeakerMapper.detailFromV1(raw, sessions: sessions);
        if (Get.isRegistered<BookmarkController>()) {
          final loved = Get.find<BookmarkController>()
              .isOnHashed('speaker', detail.id);
          if (detail.isLoved != loved) {
            detail = SpeakerDetailModel(
              id: detail.id,
              name: detail.name,
              image: detail.image,
              email: detail.email,
              designation: detail.designation,
              category: detail.category,
              presentationTitle: detail.presentationTitle,
              presentationFile: detail.presentationFile,
              facebook: detail.facebook,
              linkedin: detail.linkedin,
              twitter: detail.twitter,
              instagram: detail.instagram,
              whatsapp: detail.whatsapp,
              bio: detail.bio,
              tags: detail.tags,
              allowRating: detail.allowRating,
              isFeatured: detail.isFeatured,
              isLoved: loved,
              haveNotes: detail.haveNotes,
              notes: detail.notes,
              sessions: detail.sessions,
            );
          }
        }
        speakerDetail.value = detail;
        speakerSessions.assignAll(sessions);
      },
    );
  }

  String? _uuidFor(int speakerId) {
    for (final s in _allRawSpeakers) {
      if (TypeHelper.toInt(s['id']) == speakerId) {
        final uuid = (s['id'] ?? '').toString();
        if (uuid.isNotEmpty) return uuid;
      }
    }
    return null;
  }

  Future<bool> addOrUpdateSpeakerNote(int speakerId, String noteText) async {
    final uuid = _uuidFor(speakerId);
    if (uuid == null || noteText.trim().isEmpty) return false;
    try {
      await _service.addOrUpdateSpeakerNote(uuid, noteText.trim());
      return true;
    } catch (_) {
      return false;
    }
  }

  Future<bool> toggleBookmark(int speakerId) async {
    final uuid = _uuidFor(speakerId);
    if (uuid == null) {
      ToastMsg.showErrorMessage('Unable to bookmark this speaker.');
      return false;
    }
    if (!Get.isRegistered<BookmarkController>()) return false;
    final bookmarkCtrl = Get.find<BookmarkController>();
    var speaker = speakers.firstWhereOrNull((s) => s.id == speakerId);
    if (speaker == null && speakerDetail.value?.id == speakerId) {
      final d = speakerDetail.value!;
      speaker = SpeakerItemModel(
        id: d.id,
        name: d.name,
        image: d.image,
        designation: d.designation,
        category: d.category,
        presentationTitle: d.presentationTitle,
        isLoved: d.isLoved,
        haveNotes: d.haveNotes,
        notes: d.notes,
      );
    }
    final success = await bookmarkCtrl.toggle(
      type: 'speaker',
      uuid: uuid,
      speaker: speaker,
    );
    if (success) {
      _syncLovedFlag(speakerId, bookmarkCtrl.isOnHashed('speaker', speakerId));
    }
    return success;
  }

  void _syncLovedFlag(int speakerId, bool loved) {
    final page = speakerPage.value;
    speakerPage.value = SpeakerPageModel(
      speakers: page.speakers.map((s) {
        if (s.id != speakerId) return s;
        return SpeakerItemModel(
          id: s.id,
          name: s.name,
          image: s.image,
          designation: s.designation,
          categoryId: s.categoryId,
          category: s.category,
          presentationTitle: s.presentationTitle,
          isLoved: loved,
          haveNotes: s.haveNotes,
          notes: s.notes,
        );
      }).toList(),
      featuredAd: page.featuredAd,
      contentAd: page.contentAd,
    );

    final detail = speakerDetail.value;
    if (detail?.id == speakerId) {
      speakerDetail.value = SpeakerDetailModel(
        id: detail!.id,
        name: detail.name,
        image: detail.image,
        email: detail.email,
        designation: detail.designation,
        category: detail.category,
        presentationTitle: detail.presentationTitle,
        presentationFile: detail.presentationFile,
        facebook: detail.facebook,
        linkedin: detail.linkedin,
        twitter: detail.twitter,
        instagram: detail.instagram,
        whatsapp: detail.whatsapp,
        bio: detail.bio,
        tags: detail.tags,
        allowRating: detail.allowRating,
        isFeatured: detail.isFeatured,
        isLoved: loved,
        haveNotes: detail.haveNotes,
        notes: detail.notes,
        sessions: detail.sessions,
      );
    }
  }

  Future<void> startChat(
    int speakerId, {
    String? name,
    String? imageUrl,
  }) async {
    final uuid = _uuidFor(speakerId);
    if (uuid == null) {
      ToastMsg.showErrorMessage('Unable to start chat.');
      return;
    }
    if (!Get.isRegistered<ChatController>()) {
      ToastMsg.showErrorMessage('Chat is not available.');
      return;
    }
    await Get.find<ChatController>().startChatWith(
      uuid,
      name: name,
      imageUrl: imageUrl,
    );
  }

  @override
  void onClose() {
    searchController.dispose();
    super.onClose();
  }
}
