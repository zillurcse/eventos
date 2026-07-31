import '../exhibitor_model.dart';
import '../reception_speaker_model.dart';
import '../session_day_model.dart';
import '../session_detail_response_model.dart';
import '../session_document_model.dart';
import '../session_model.dart';
import '../session_sponsor_model.dart';
import '../session_track_model.dart';
import '../../utils/helpers/type_helper.dart';

/// Maps EventOS `GET /api/v1/public/sessions` into the existing session UI models.
class SessionMapper {
  SessionMapper._();

  /// Group flat sessions into day tabs + facet lists for filters.
  static ({
    List<SessionDayModel> days,
    List<SessionTrackModel> tracks,
    List<String> tags,
    List<ReceptionSpeakerModel> speakers,
    Map<String, String> timezoneData,
    Map<int, Map<String, dynamic>> rawById,
  }) fromV1(Map<String, dynamic> data) {
    final event = data['event'] is Map
        ? Map<String, dynamic>.from(data['event'] as Map)
        : <String, dynamic>{};
    final tz = event['timezone']?.toString() ?? '';

    final tracks = (data['tracks'] as List? ?? [])
        .whereType<Map>()
        .map((e) {
          final m = Map<String, dynamic>.from(e);
          return SessionTrackModel(
            id: TypeHelper.toInt(m['id']),
            title: (m['name'] ?? m['title'] ?? '').toString(),
          );
        })
        .toList();

    final tags = (data['tags'] as List? ?? [])
        .map((e) => e.toString())
        .where((t) => t.isNotEmpty)
        .toList();

    final speakers = (data['speakers'] as List? ?? [])
        .whereType<Map>()
        .map((e) => ReceptionSpeakerModel.fromJson(Map<String, dynamic>.from(e)))
        .toList();

    final sessionsRaw = data['sessions'];
    final sessionList = sessionsRaw is Map && sessionsRaw['data'] is List
        ? sessionsRaw['data'] as List
        : (sessionsRaw as List? ?? []);

    final rawSessions = sessionList
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .toList();

    final eventStartsAt = event['starts_at']?.toString() ?? '';
    final eventEndsAt = event['ends_at']?.toString() ?? '';

    final rawById = <int, Map<String, dynamic>>{};
    final byDayKey = <String, List<SessionModel>>{};

    for (final raw in rawSessions) {
      final model = sessionFromV1(raw);
      rawById[model.id] = raw;
      final start = DateTime.tryParse(raw['starts_at']?.toString() ?? '');
      final local = start?.toLocal();
      final key = local != null ? _dateKey(local) : 'unscheduled';
      byDayKey.putIfAbsent(key, () => []);
      byDayKey[key]!.add(model);
    }

    // Match web/admin: fill every calendar day from event start → end so the
    // date strip does not skip empty days (e.g. Jul 25 → Jul 30).
    final rangeKeys = _dayKeysFromEventRange(eventStartsAt, eventEndsAt);
    final sortedKeys = <String>[];
    if (rangeKeys.isNotEmpty) {
      sortedKeys.addAll(rangeKeys);
      for (final key in byDayKey.keys) {
        if (key != 'unscheduled' && !sortedKeys.contains(key)) {
          sortedKeys.add(key);
        }
      }
      sortedKeys.sort((a, b) {
        if (a == 'unscheduled') return 1;
        if (b == 'unscheduled') return -1;
        return a.compareTo(b);
      });
      if (byDayKey.containsKey('unscheduled')) {
        sortedKeys.add('unscheduled');
      }
    } else {
      sortedKeys
        ..addAll(byDayKey.keys)
        ..sort((a, b) {
          if (a == 'unscheduled') return 1;
          if (b == 'unscheduled') return -1;
          return a.compareTo(b);
        });
    }

    final days = <SessionDayModel>[];
    for (final key in sortedKeys) {
      final meta = _dayMetaForKey(key, tracks);
      days.add(
        SessionDayModel(
          id: meta.id,
          title: meta.title,
          date: meta.date,
          dateLabel: meta.dateLabel,
          dayName: meta.dayName,
          schedules: byDayKey[key] ?? const [],
          tracks: tracks,
        ),
      );
    }

    return (
      days: days,
      tracks: tracks,
      tags: tags,
      speakers: speakers,
      timezoneData: {
        'event_timezone': tz,
        'current_timezone': tz,
        'user_timezone': tz,
        'event_starts_at': eventStartsAt,
        'event_ends_at': eventEndsAt,
      },
      rawById: rawById,
    );
  }

  /// Every YYYY-MM-DD from event starts_at → ends_at (local calendar days).
  static List<String> _dayKeysFromEventRange(String startsAt, String endsAt) {
    final start = DateTime.tryParse(startsAt);
    if (start == null) return const [];
    final end = DateTime.tryParse(endsAt) ?? start;
    var cur = DateTime(start.toLocal().year, start.toLocal().month, start.toLocal().day);
    final last = DateTime(end.toLocal().year, end.toLocal().month, end.toLocal().day);
    final keys = <String>[];
    while (!cur.isAfter(last) && keys.length < 60) {
      keys.add(_dateKey(cur));
      cur = DateTime(cur.year, cur.month, cur.day + 1);
    }
    return keys;
  }

  static String _dateKey(DateTime d) =>
      '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  static SessionDayModel _dayMetaForKey(
    String key,
    List<SessionTrackModel> tracks,
  ) {
    if (key == 'unscheduled') {
      return SessionDayModel(
        id: key.hashCode & 0x7fffffff,
        title: 'Sessions',
        date: '',
        dateLabel: '',
        dayName: '',
        tracks: tracks,
      );
    }
    final parts = key.split('-');
    if (parts.length != 3) {
      return SessionDayModel(
        id: key.hashCode & 0x7fffffff,
        title: 'Sessions',
        date: key,
        tracks: tracks,
      );
    }
    final local = DateTime(
      int.parse(parts[0]),
      int.parse(parts[1]),
      int.parse(parts[2]),
    );
    return SessionDayModel(
      id: key.hashCode & 0x7fffffff,
      title: '${_weekday(local)}, ${_month(local)} ${local.day}',
      date: key,
      dateLabel: '${local.day.toString().padLeft(2, '0')} ${_month(local)}',
      dayName: _weekday(local),
      tracks: tracks,
    );
  }

  static SessionModel sessionFromV1(Map<String, dynamic> json) {
    final uuid = (json['id'] ?? '').toString();
    final startsAt = json['starts_at']?.toString() ?? '';
    final endsAt = json['ends_at']?.toString() ?? '';
    final start = DateTime.tryParse(startsAt);
    final local = start?.toLocal();
    final dayLabel = local != null
        ? '${_weekday(local)}, ${_month(local)} ${local.day}'
        : '';

    final speakers = <ReceptionSpeakerModel>[];
    final rawSpeakers = json['speakers'];
    if (rawSpeakers is List) {
      for (final s in rawSpeakers) {
        if (s is! Map) continue;
        final m = Map<String, dynamic>.from(s);
        final profile = m['profile'] is Map
            ? Map<String, dynamic>.from(m['profile'] as Map)
            : <String, dynamic>{};
        speakers.add(
          ReceptionSpeakerModel.fromJson({
            'id': m['id'],
            'name': m['name'] ?? '',
            'image_url': profile['image_url'] ?? m['image_url'],
            'designation': profile['designation'] ?? m['designation'] ?? '',
            'company': profile['company'] ?? m['company'] ?? '',
            'is_featured': profile['is_featured'] ?? m['is_featured'],
          }),
        );
      }
    }

    final sponsors = <SessionSponsorModel>[];
    final rawSponsors = json['sponsors'];
    if (rawSponsors is List) {
      for (final s in rawSponsors) {
        if (s is! Map) continue;
        sponsors.add(
          SessionSponsorModel.fromJson(Map<String, dynamic>.from(s)),
        );
      }
    }

    final room = json['room'];
    var place = json['session_place']?.toString() ?? '';
    if (place.isEmpty && room is Map) {
      place = (room['name'] ?? '').toString();
    }

    final track = json['track'];
    final trackName = track is Map ? (track['name'] ?? '').toString() : '';

    return SessionModel(
      id: TypeHelper.toInt(json['id']),
      uuid: uuid,
      title: json['title']?.toString() ?? '',
      startTime: _formatClock(startsAt),
      endTime: _formatClock(endsAt),
      startsAt: startsAt.isEmpty ? null : startsAt,
      endsAt: endsAt.isEmpty ? null : endsAt,
      status: json['status']?.toString(),
      logoUrl: (json['logo_url'] ?? json['icon_url'] ?? '').toString(),
      day: SessionDayModel(title: dayLabel, date: dayLabel),
      speakers: speakers,
      sponsors: sponsors,
      tags: [
        ...((json['tags'] as List? ?? []).map((e) => e.toString())),
        if (trackName.isNotEmpty) trackName,
      ],
      sessionPlace: place,
      isFavorite: TypeHelper.toBool(json['is_favorite']),
      inMySchedule: TypeHelper.toBool(json['in_my_schedule']),
    );
  }

  static SessionDetailModel detailFromV1(Map<String, dynamic> json) {
    final model = sessionFromV1(json);
    final track = json['track'];
    final trackName = track is Map
        ? (track['name'] ?? '').toString()
        : (json['track']?.toString() ?? '');

    final documents = <SessionDocumentModel>[];
    final rawDocs = json['documents'];
    if (rawDocs is List) {
      for (final d in rawDocs) {
        if (d is! Map) continue;
        documents.add(
          SessionDocumentModel.fromJson(Map<String, dynamic>.from(d)),
        );
      }
    }

    final sponsors = <ExhibitorModel>[];
    final rawSponsors = json['sponsors'];
    if (rawSponsors is List) {
      for (final s in rawSponsors) {
        if (s is! Map) continue;
        final m = Map<String, dynamic>.from(s);
        sponsors.add(
          ExhibitorModel.fromJson({
            'id': m['id'],
            'name': m['name'] ?? '',
            'logo_url': m['logo_url'] ?? m['logo'] ?? '',
            'company_name': m['name'] ?? '',
          }),
        );
      }
    }

    return SessionDetailModel(
      id: model.id,
      uuid: model.uuid,
      startTime: model.startTime,
      endTime: model.endTime,
      startsAt: model.startsAt,
      endsAt: model.endsAt,
      status: model.status,
      timezone: json['timezone']?.toString(),
      title: model.title,
      sessionPlace: model.sessionPlace,
      description: json['description']?.toString() ?? '',
      logo: model.logoUrl,
      documents: documents,
      file: documents.isNotEmpty ? documents.first.url : null,
      tags: model.tags,
      isFeatured: TypeHelper.toBool(json['is_featured']),
      isAllowedToRate: TypeHelper.toBool(json['is_allowed_to_rate']),
      isStream: TypeHelper.toBool(json['is_stream']),
      streamLink: (json['stream_link'] ?? json['stream_url'])?.toString(),
      whoWillHost: json['who_will_host']?.toString(),
      vimeoLiveId: json['vimeo_live_id']?.toString(),
      onDemandRecordingLink: json['on_demand_recording_link']?.toString(),
      canLiveChat: TypeHelper.toBool(json['can_live_chat']),
      canQa: TypeHelper.toBool(json['can_qa']),
      canLivePolls: TypeHelper.toBool(json['can_live_polls']),
      canAttendeeList: TypeHelper.toBool(json['can_attendee_list']),
      speakers: model.speakers,
      sponsors: sponsors,
      day: model.day,
      track: trackName,
    );
  }

  /// Client-side filter matching the web agenda filters.
  static List<Map<String, dynamic>> filterRaw({
    required List<Map<String, dynamic>> sessions,
    int? trackId,
    String? tag,
    int? speakerId,
    String? search,
    bool savedOnly = false,
    Set<String> bookmarkedUuids = const {},
  }) {
    final q = search?.trim().toLowerCase() ?? '';
    return sessions.where((json) {
      final uuid = (json['id'] ?? '').toString();
      if (savedOnly && !bookmarkedUuids.contains(uuid)) return false;

      if (trackId != null) {
        final track = json['track'];
        final tid = track is Map ? TypeHelper.toInt(track['id']) : 0;
        if (tid != trackId) return false;
      }
      if (tag != null && tag.isNotEmpty) {
        final tags = (json['tags'] as List? ?? []).map((e) => e.toString());
        if (!tags.contains(tag)) return false;
      }
      if (speakerId != null) {
        final speakers = json['speakers'];
        if (speakers is! List) return false;
        final match = speakers.any((s) {
          if (s is! Map) return false;
          return TypeHelper.toInt(s['id']) == speakerId;
        });
        if (!match) return false;
      }
      if (q.isNotEmpty) {
        final title = (json['title'] ?? '').toString().toLowerCase();
        final desc = (json['description'] ?? '').toString().toLowerCase();
        if (!title.contains(q) && !desc.contains(q)) return false;
      }
      return true;
    }).toList();
  }

  static String googleCalendarUrl({
    required String title,
    required String? startsAt,
    required String? endsAt,
    String? description,
  }) {
    if (startsAt == null || startsAt.isEmpty) return '';
    String fmt(String iso) {
      final dt = DateTime.tryParse(iso)?.toUtc();
      if (dt == null) return '';
      final y = dt.year.toString().padLeft(4, '0');
      final m = dt.month.toString().padLeft(2, '0');
      final d = dt.day.toString().padLeft(2, '0');
      final h = dt.hour.toString().padLeft(2, '0');
      final min = dt.minute.toString().padLeft(2, '0');
      final s = dt.second.toString().padLeft(2, '0');
      return '$y$m${d}T$h$min${s}Z';
    }

    final start = fmt(startsAt);
    final end = fmt(endsAt ?? startsAt);
    if (start.isEmpty) return '';

    final details = (description ?? '')
        .replaceAll(RegExp(r'<[^>]+>'), '')
        .replaceAll(RegExp(r'\s+'), ' ')
        .trim();
    final clipped =
        details.length > 500 ? details.substring(0, 500) : details;

    final params = {
      'action': 'TEMPLATE',
      'text': title,
      'dates': '$start/$end',
      if (clipped.isNotEmpty) 'details': clipped,
    };
    return Uri.https(
      'calendar.google.com',
      '/calendar/render',
      params,
    ).toString();
  }

  static String _formatClock(String iso) {
    final dt = DateTime.tryParse(iso);
    if (dt == null) return '';
    final local = dt.toLocal();
    final h = local.hour % 12 == 0 ? 12 : local.hour % 12;
    final m = local.minute.toString().padLeft(2, '0');
    final ampm = local.hour >= 12 ? 'PM' : 'AM';
    return '$h:$m $ampm';
  }

  static String _month(DateTime d) {
    const months = [
      'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
      'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
    ];
    return months[d.month - 1];
  }

  static String _weekday(DateTime d) {
    const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    return days[d.weekday - 1];
  }
}
