import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../models/session_day_model.dart';
import '../../models/session_model.dart';
import '../../models/session_track_model.dart';
import '../../models/reception_speaker_model.dart';
import '../../models/session_detail_response_model.dart';
import '../../models/mappers/session_mapper.dart';
import '../../utils/enum/enums.dart';
import '../../utils/helpers/helper_functions.dart';
import '../../utils/helpers/type_helper.dart';
import '../bookmarks/bookmark_controller.dart';
import 'session_phase.dart';
import 'session_service.dart';

class SessionController extends GetxController {
  final _service = SessionService();

  final dataStatus = ApiState.initial.obs;
  final RxList<SessionDayModel> days = <SessionDayModel>[].obs;
  final activeDayIndex = 0.obs;

  final detailStatus = ApiState.initial.obs;
  final Rxn<SessionDetailModel> sessionDetail = Rxn<SessionDetailModel>();
  final sessionRating = 0.obs;
  final ratingSaving = false.obs;

  final searchQuery = "".obs;
  late final TextEditingController searchController;

  final selectedTrackId = RxnInt();
  final selectedTag = RxnString();
  final selectedTimezone = RxnString();
  final selectedSpeakerId = RxnInt();
  final savedOnly = false.obs;

  final RxMap<String, String> timezoneData = <String, String>{
    "event_timezone": "",
    "current_timezone": "",
    "user_timezone": "",
  }.obs;

  final RxList<SessionTrackModel> masterTracks = <SessionTrackModel>[].obs;
  final RxList<String> masterTags = <String>[].obs;
  final RxList<ReceptionSpeakerModel> masterSpeakers =
      <ReceptionSpeakerModel>[].obs;

  /// Full unfiltered agenda from last successful fetch (for client filters + detail).
  List<Map<String, dynamic>> _allRawSessions = [];
  final Map<int, Map<String, dynamic>> _rawById = {};
  /// Session UUIDs bookmarked by the current participant.
  final Set<String> _bookmarkedUuids = {};
  /// Bumped whenever bookmarks change so Obx widgets rebuild.
  final bookmarkRevision = 0.obs;

  @override
  void onInit() {
    super.onInit();
    searchController = TextEditingController();
    searchController.addListener(() {
      searchQuery(searchController.text);
    });

    debounce(
      searchQuery,
      (_) => _applyFilters(),
      time: const Duration(milliseconds: 500),
    );
  }

  @override
  void onClose() {
    searchController.dispose();
    super.onClose();
  }

  SessionDayModel? get activeDay {
    if (days.isEmpty || activeDayIndex.value >= days.length) return null;
    return days[activeDayIndex.value];
  }

  List<SessionTrackModel> get tracksList =>
      masterTracks.isNotEmpty ? masterTracks : _extractTracks(days);
  List<String> get tagsList =>
      masterTags.isNotEmpty ? masterTags : _extractTags(days);
  List<ReceptionSpeakerModel> get speakersList =>
      masterSpeakers.isNotEmpty ? masterSpeakers : _extractSpeakers(days);

  int get bookmarkedSessionCount {
    bookmarkRevision.value;
    return _bookmarkedUuids.length;
  }

  bool isBookmarked(String uuid) {
    bookmarkRevision.value;
    return _bookmarkedUuids.contains(uuid);
  }

  bool isBookmarkedById(int scheduleId) {
    bookmarkRevision.value;
    final uuid = _uuidFor(scheduleId);
    return uuid != null && _bookmarkedUuids.contains(uuid);
  }

  /// LIVE sessions from the full agenda (pre/post-roll + status), not home reception.
  List<SessionModel> get liveSessions {
    final now = DateTime.now();
    final result = <SessionModel>[];
    for (final raw in _allRawSessions) {
      final model = SessionMapper.sessionFromV1(raw);
      if (SessionPhaseHelper.isLiveNow(
        status: model.status,
        startsAt: model.startsAt,
        endsAt: model.endsAt,
        now: now,
      )) {
        result.add(model);
      }
    }
    return result;
  }

  final List<String> timezonesList = const [
    "GST",
    "UTC",
    "GMT",
    "EST",
    "PST",
    "IST",
  ];

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
          if (tag.isNotEmpty) set.add(tag);
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

  List<SessionModel> get activeDaySchedules {
    final day = activeDay;
    if (day == null) return [];
    return day.schedules;
  }

  Future<void> fetchSessions() async {
    await handleApiClient(
      onStateChanged: (state) => dataStatus(state),
      handleApiCall: () async {
        final response = await _service.getSessions();
        final body = response.data;
        if (body is! Map) return;

        final payload = body['data'] is Map
            ? Map<String, dynamic>.from(body['data'] as Map)
            : Map<String, dynamic>.from(body);

        _allRawSessions = () {
          final sessionsRaw = payload['sessions'];
          final list = sessionsRaw is Map && sessionsRaw['data'] is List
              ? sessionsRaw['data'] as List
              : (sessionsRaw as List? ?? []);
          return list
              .whereType<Map>()
              .map((e) => Map<String, dynamic>.from(e))
              .toList();
        }();

        await _loadBookmarks();
        _stampFavoritesOnRaw();

        final mapped = SessionMapper.fromV1(payload);
        timezoneData.assignAll(mapped.timezoneData);
        masterTracks.assignAll(mapped.tracks);
        masterTags.assignAll(mapped.tags);
        masterSpeakers.assignAll(mapped.speakers);
        _rawById
          ..clear()
          ..addAll(mapped.rawById);

        _applyFilters(rebuildFromCache: true);
      },
    );
  }

  Future<void> _loadBookmarks() async {
    try {
      final response = await _service.getBookmarks();
      final body = response.data;
      if (body is! Map) return;
      final data = body['data'] is Map
          ? Map<String, dynamic>.from(body['data'] as Map)
          : <String, dynamic>{};
      final sessions = data['session'];
      _bookmarkedUuids
        ..clear()
        ..addAll(
          (sessions is List ? sessions : const [])
              .map((e) => e.toString())
              .where((e) => e.isNotEmpty),
        );
      bookmarkRevision.value++;
    } catch (_) {
      // Bookmarks require auth — agenda still works without them.
    }
  }

  void _stampFavoritesOnRaw() {
    for (final raw in _allRawSessions) {
      final uuid = (raw['id'] ?? '').toString();
      raw['is_favorite'] = _bookmarkedUuids.contains(uuid);
    }
  }

  void _applyFilters({bool rebuildFromCache = false}) {
    if (_allRawSessions.isEmpty && !rebuildFromCache) return;

    _stampFavoritesOnRaw();

    final filtered = SessionMapper.filterRaw(
      sessions: _allRawSessions,
      trackId: selectedTrackId.value,
      tag: selectedTag.value,
      speakerId: selectedSpeakerId.value,
      search: searchQuery.value,
      savedOnly: savedOnly.value,
      bookmarkedUuids: _bookmarkedUuids,
    );

    final payload = {
      'event': {
        'timezone': timezoneData['event_timezone'] ?? '',
        'starts_at': timezoneData['event_starts_at'] ?? '',
        'ends_at': timezoneData['event_ends_at'] ?? '',
      },
      'tracks': masterTracks
          .map((t) => {'id': t.id, 'name': t.title})
          .toList(),
      'tags': masterTags.toList(),
      'speakers': masterSpeakers
          .map((s) => {
                'id': s.id,
                'name': s.name,
                'image_url': s.imageUrl,
                'designation': s.designation,
                'company': s.company,
              })
          .toList(),
      'sessions': filtered,
    };

    final mapped = SessionMapper.fromV1(payload);
    _rawById
      ..clear()
      ..addAll(mapped.rawById);
    for (final raw in _allRawSessions) {
      final id = SessionMapper.sessionFromV1(raw).id;
      _rawById.putIfAbsent(id, () => raw);
    }

    days.assignAll(mapped.days);
    // Default to today when in range; otherwise the first day (matches web).
    if (rebuildFromCache || activeDayIndex.value >= days.length) {
      activeDayIndex(_indexOfTodayOrFirst());
    }
  }

  /// Prefer today's date in the strip; fall back to the first day.
  int _indexOfTodayOrFirst() {
    if (days.isEmpty) return 0;
    final now = DateTime.now();
    final todayKey =
        '${now.year}-${now.month.toString().padLeft(2, '0')}-${now.day.toString().padLeft(2, '0')}';
    final idx = days.indexWhere((d) => d.date == todayKey);
    return idx >= 0 ? idx : 0;
  }

  void setActiveDayIndex(int index) {
    if (index >= 0 && index < days.length) {
      activeDayIndex(index);
    }
  }

  void selectTrack(int? trackId) {
    selectedTrackId(trackId);
    _applyFilters();
  }

  void selectTag(String? tag) {
    selectedTag(tag);
    _applyFilters();
  }

  void selectTimezone(String? tz) {
    selectedTimezone(tz);
  }

  void selectSpeaker(int? speakerId) {
    selectedSpeakerId(speakerId);
    _applyFilters();
  }

  void toggleSavedOnly() {
    savedOnly(!savedOnly.value);
    _applyFilters();
  }

  void clearSearch() {
    searchController.clear();
    searchQuery("");
  }

  Future<void> fetchSessionDetails(int scheduleId) async {
    sessionDetail.value = null;
    sessionRating.value = 0;
    await handleApiClient(
      onStateChanged: (state) => detailStatus(state),
      handleApiCall: () async {
        var raw = _rawById[scheduleId];
        if (raw == null) {
          for (final s in _allRawSessions) {
            if (SessionMapper.sessionFromV1(s).id == scheduleId) {
              raw = s;
              break;
            }
          }
        }
        if (raw == null && _allRawSessions.isEmpty) {
          final response = await _service.getSessions();
          final body = response.data;
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
            for (final s in _allRawSessions) {
              final model = SessionMapper.sessionFromV1(s);
              _rawById[model.id] = s;
              if (model.id == scheduleId) raw = s;
            }
          }
        }
        if (raw == null) {
          throw Exception('Session not found');
        }
        final detail = SessionMapper.detailFromV1(raw);
        sessionDetail.value = detail;
        if (detail.isAllowedToRate && detail.uuid.isNotEmpty) {
          await _loadRating(detail.uuid);
        }
      },
    );
  }

  Future<void> _loadRating(String sessionUuid) async {
    try {
      final response = await _service.getSessionRating(sessionUuid);
      final body = response.data;
      if (body is! Map) return;
      final data = body['data'] is Map
          ? Map<String, dynamic>.from(body['data'] as Map)
          : body;
      sessionRating.value = TypeHelper.toInt(data['score']);
    } catch (_) {
      sessionRating.value = 0;
    }
  }

  Future<void> submitRating(int score) async {
    final detail = sessionDetail.value;
    if (detail == null || !detail.isAllowedToRate || detail.uuid.isEmpty) {
      return;
    }
    if (score < 1 || score > 5) return;
    final previous = sessionRating.value;
    sessionRating.value = score;
    ratingSaving.value = true;
    try {
      await _service.submitSessionRating(detail.uuid, score);
    } catch (_) {
      sessionRating.value = previous;
    } finally {
      ratingSaving.value = false;
    }
  }

  String? calendarUrlFor(SessionDetailModel detail) {
    final url = SessionMapper.googleCalendarUrl(
      title: detail.title,
      startsAt: detail.startsAt,
      endsAt: detail.endsAt,
      description: detail.description,
    );
    return url.isEmpty ? null : url;
  }

  String? _uuidFor(int scheduleId) {
    final raw = _rawById[scheduleId];
    if (raw != null) {
      final uuid = (raw['id'] ?? '').toString();
      if (uuid.isNotEmpty) return uuid;
    }
    for (final s in _allRawSessions) {
      if (SessionMapper.sessionFromV1(s).id == scheduleId) {
        final uuid = (s['id'] ?? '').toString();
        if (uuid.isNotEmpty) return uuid;
      }
    }
    return null;
  }

  Future<bool> addOrUpdateSessionNote(int scheduleId, String noteText) async {
    final uuid = _uuidFor(scheduleId);
    if (uuid == null || noteText.trim().isEmpty) return false;
    try {
      await _service.addOrUpdateSessionNote(uuid, noteText.trim());
      return true;
    } catch (_) {
      return false;
    }
  }

  Future<bool> toggleBookmark(int scheduleId) async {
    final uuid = _uuidFor(scheduleId);
    if (uuid == null) return false;

    final on = !_bookmarkedUuids.contains(uuid);
    if (on) {
      _bookmarkedUuids.add(uuid);
    } else {
      _bookmarkedUuids.remove(uuid);
    }
    bookmarkRevision.value++;
    _applyFilters();
    // Refresh detail favorite state if open.
    final detail = sessionDetail.value;
    if (detail != null && detail.id == scheduleId) {
      sessionDetail.refresh();
    }

    try {
      await _service.toggleSessionBookmark(uuid, on);
      if (Get.isRegistered<BookmarkController>()) {
        final session = days
            .expand((d) => d.schedules)
            .toList()
            .firstWhereOrNull((s) => s.id == scheduleId);
        Get.find<BookmarkController>().syncLocal(
          type: 'session',
          uuid: uuid,
          on: on,
          session: session,
        );
      }
      return true;
    } catch (_) {
      if (on) {
        _bookmarkedUuids.remove(uuid);
      } else {
        _bookmarkedUuids.add(uuid);
      }
      bookmarkRevision.value++;
      _applyFilters();
      return false;
    }
  }
}
