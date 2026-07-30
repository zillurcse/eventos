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
import '../bookmarks/bookmark_controller.dart';
import 'session_service.dart';

class SessionController extends GetxController {
  final _service = SessionService();

  final dataStatus = ApiState.initial.obs;
  final RxList<SessionDayModel> days = <SessionDayModel>[].obs;
  final activeDayIndex = 0.obs;

  final detailStatus = ApiState.initial.obs;
  final Rxn<SessionDetailModel> sessionDetail = Rxn<SessionDetailModel>();
  final searchQuery = "".obs;
  late final TextEditingController searchController;

  final selectedTrackId = RxnInt();
  final selectedTag = RxnString();
  final selectedTimezone = RxnString();
  final selectedSpeakerId = RxnInt();

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
    );

    final payload = {
      'event': {
        'timezone': timezoneData['event_timezone'] ?? '',
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
    // Keep unfiltered raw ids for detail lookup.
    for (final raw in _allRawSessions) {
      final id = SessionMapper.sessionFromV1(raw).id;
      _rawById.putIfAbsent(id, () => raw);
    }

    days.assignAll(mapped.days);
    if (activeDayIndex.value >= days.length) {
      activeDayIndex(0);
    }
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
    // Timezone display-only for now; times already localised in mapper.
  }

  void selectSpeaker(int? speakerId) {
    selectedSpeakerId(speakerId);
    _applyFilters();
  }

  void clearSearch() {
    searchController.clear();
    searchQuery("");
  }

  Future<void> fetchSessionDetails(int scheduleId) async {
    sessionDetail.value = null;
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
        sessionDetail.value = SessionMapper.detailFromV1(raw);
      },
    );
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
    _applyFilters();

    try {
      await _service.toggleSessionBookmark(uuid, on);
      if (Get.isRegistered<BookmarkController>()) {
        // Briefcase / bookmarks tab can refresh independently.
      }
      return true;
    } catch (_) {
      if (on) {
        _bookmarkedUuids.remove(uuid);
      } else {
        _bookmarkedUuids.add(uuid);
      }
      _applyFilters();
      return false;
    }
  }
}
