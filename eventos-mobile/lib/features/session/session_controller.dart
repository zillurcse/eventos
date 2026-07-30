import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../models/session_day_model.dart';
import '../../models/session_model.dart';
import '../../models/session_track_model.dart';
import '../../models/reception_speaker_model.dart';
import '../../models/session_detail_response_model.dart';
import '../../utils/enum/enums.dart';
import '../../utils/helpers/helper_functions.dart';
import '../bookmarks/bookmark_controller.dart';
import 'session_service.dart';

class SessionController extends GetxController {
  final _service = SessionService();

  // ── States ───────────────────────────────────────────────────────────────
  final dataStatus = ApiState.initial.obs;
  final RxList<SessionDayModel> days = <SessionDayModel>[].obs;
  final activeDayIndex = 0.obs;

  // Detail States
  final detailStatus = ApiState.initial.obs;
  final Rxn<SessionDetailModel> sessionDetail = Rxn<SessionDetailModel>();
  final searchQuery = "".obs;
  late final TextEditingController searchController;

  // Active filter states
  final selectedTrackId = RxnInt();
  final selectedTag = RxnString();
  final selectedTimezone = RxnString();
  final selectedSpeakerId = RxnInt();

  // Timezone data returned by the API
  final RxMap<String, String> timezoneData = <String, String>{
    "event_timezone": "Asia/Muscat",
    "current_timezone": "Asia/Dhaka",
    "user_timezone": "Asia/Dhaka",
  }.obs;

  // Master lists for dropdown options (to prevent shrinking when filtered)
  final RxList<SessionTrackModel> masterTracks = <SessionTrackModel>[].obs;
  final RxList<String> masterTags = <String>[].obs;
  final RxList<ReceptionSpeakerModel> masterSpeakers = <ReceptionSpeakerModel>[].obs;

  @override
  void onInit() {
    super.onInit();
    searchController = TextEditingController();
    searchController.addListener(() {
      searchQuery(searchController.text);
    });
    
    // Server-side search debounce
    debounce(
      searchQuery,
      (_) => fetchSessions(),
      time: const Duration(milliseconds: 500),
    );
  }

  @override
  void onClose() {
    searchController.dispose();
    super.onClose();
  }

  // Convenience active day getter
  SessionDayModel? get activeDay {
    if (days.isEmpty || activeDayIndex.value >= days.length) return null;
    return days[activeDayIndex.value];
  }

  // ── Getters for dropdown options ──
  List<SessionTrackModel> get tracksList => masterTracks.isNotEmpty ? masterTracks : _extractTracks(days);
  List<String> get tagsList => masterTags.isNotEmpty ? masterTags : _extractTags(days);
  List<ReceptionSpeakerModel> get speakersList => masterSpeakers.isNotEmpty ? masterSpeakers : _extractSpeakers(days);

  final List<String> timezonesList = const [
    "GST",
    "UTC",
    "GMT",
    "EST",
    "PST",
    "IST",
  ];

  // Helper extraction methods
  List<SessionTrackModel> _extractTracks(List<SessionDayModel> dayList) {
    final seenIds = <int>{};
    final list = <SessionTrackModel>[];
    for (final day in dayList) {
      for (final track in day.tracks) {
        if (!seenIds.contains(track.id)) {
          seenIds.add(track.id);
          list.add(track);
        }
      }
    }
    return list;
  }

  List<String> _extractTags(List<SessionDayModel> dayList) {
    final set = <String>{};
    for (final day in dayList) {
      for (final session in day.schedules) {
        for (final tag in session.tags) {
          if (tag.isNotEmpty) {
            set.add(tag);
          }
        }
      }
    }
    return set.toList();
  }

  List<ReceptionSpeakerModel> _extractSpeakers(List<SessionDayModel> dayList) {
    final seenIds = <int>{};
    final list = <ReceptionSpeakerModel>[];
    for (final day in dayList) {
      for (final session in day.schedules) {
        for (final sp in session.speakers) {
          if (!seenIds.contains(sp.id)) {
            seenIds.add(sp.id);
            list.add(sp);
          }
        }
      }
    }
    return list;
  }

  // Get filtered schedules for the active day
  List<SessionModel> get activeDaySchedules {
    final day = activeDay;
    if (day == null) return [];
    
    return day.schedules;
  }

  // ── API Call ──────────────────────────────────────────────────────────────
  Future<void> fetchSessions() async {
    await handleApiClient(
      onStateChanged: (state) => dataStatus(state),
      handleApiCall: () async {
        String? resolvedTimezone = selectedTimezone.value;
        if (resolvedTimezone != null) {
          resolvedTimezone = resolvedTimezone;
        }

        final response = await _service.getSessions(
          trackId: selectedTrackId.value,
          tag: selectedTag.value,
          timezone: resolvedTimezone,
          speakerId: selectedSpeakerId.value,
          s: searchQuery.value,
        );
        if (response.data is Map) {
          final data = Map<String, dynamic>.from(response.data as Map);

          if (data['timezone_data'] is Map) {
            final tzMap = Map<String, dynamic>.from(data['timezone_data'] as Map);
            timezoneData.assignAll(
              tzMap.map((key, value) => MapEntry(key, value?.toString() ?? '')),
            );
          }

          if (data['days'] is List) {
            final parsedDays = (data['days'] as List)
                .map((e) => SessionDayModel.fromJson(Map<String, dynamic>.from(e)))
                .toList();
            days.assignAll(parsedDays);

            // Populate or refresh master lists when request is unfiltered or master list is empty
            final isUnfiltered = selectedTrackId.value == null &&
                selectedTag.value == null &&
                selectedTimezone.value == null &&
                selectedSpeakerId.value == null;

            if (isUnfiltered || masterTracks.isEmpty) {
              masterTracks.assignAll(_extractTracks(parsedDays));
            }
            if (isUnfiltered || masterTags.isEmpty) {
              masterTags.assignAll(_extractTags(parsedDays));
            }
            if (isUnfiltered || masterSpeakers.isEmpty) {
              masterSpeakers.assignAll(_extractSpeakers(parsedDays));
            }
          }

          // Optionally match day_active if present
          if (data['day_active'] is Map) {
            final activeDayMap = Map<String, dynamic>.from(data['day_active']);
            final activeDayId = activeDayMap['id'];
            if (activeDayId != null) {
              final activeIdx = days.indexWhere((d) => d.id == activeDayId);
              if (activeIdx != -1) {
                activeDayIndex(activeIdx);
              }
            }
          }
        }
      },
    );
  }

  void setActiveDayIndex(int index) {
    if (index >= 0 && index < days.length) {
      activeDayIndex(index);
    }
  }

  void selectTrack(int? trackId) {
    selectedTrackId(trackId);
    fetchSessions();
  }

  void selectTag(String? tag) {
    selectedTag(tag);
    fetchSessions();
  }

  void selectTimezone(String? tz) {
    selectedTimezone(tz);
    fetchSessions();
  }

  void selectSpeaker(int? speakerId) {
    selectedSpeakerId(speakerId);
    fetchSessions();
  }

  void clearSearch() {
    searchController.clear();
    searchQuery("");
  }

  // ── API: fetch session details ────────────────────────────────────────────
  Future<void> fetchSessionDetails(int scheduleId) async {
    sessionDetail.value = null;
    await handleApiClient(
      onStateChanged: (state) => detailStatus(state),
      handleApiCall: () async {
        final response = await _service.getSessionDetails(scheduleId);
        if (response.data is Map) {
          final data = Map<String, dynamic>.from(response.data as Map);
          final scheduleMap = data['schedule'];
          if (scheduleMap is Map) {
            sessionDetail.value = SessionDetailModel.fromJson(
              Map<String, dynamic>.from(scheduleMap),
            );
          }
        }
      },
    );
  }

  // ── API: add/update session note ──────────────────────────────────────────
  Future<bool> addOrUpdateSessionNote(int scheduleId, String noteText) async {
    bool success = false;
    await handleApiClient(
      onStateChanged: (state) {
        // No explicit detailStatus state change needed as we refresh details below
      },
      handleApiCall: () async {
        final response = await _service.addOrUpdateSessionNote(scheduleId, noteText);
        if (response.data is Map) {
          final raw = Map<String, dynamic>.from(response.data as Map);
          if (raw['status'] == 'success') {
            success = true;
            await fetchSessionDetails(scheduleId);
            await fetchSessions();
          }
        }
      },
    );
    return success;
  }

  // ── API: toggle session bookmark ──────────────────────────────────────────
  Future<bool> toggleBookmark(int scheduleId) async {
    bool success = false;
    await handleApiClient(
      onStateChanged: (state) {},
      handleApiCall: () async {
        final response = await _service.toggleSessionBookmark(scheduleId);
        if (response.data is Map) {
          final raw = Map<String, dynamic>.from(response.data as Map);
          if (raw['status'] == 'success' || raw['status'] == 1 || raw['success'] == true) {
            success = true;
            await fetchSessions();
            if (sessionDetail.value?.id == scheduleId) {
              await fetchSessionDetails(scheduleId);
            }
            if (Get.isRegistered<BookmarkController>()) {
              Get.find<BookmarkController>().fetchBookmarks();
            }
          }
        }
      },
    );
    return success;
  }
}