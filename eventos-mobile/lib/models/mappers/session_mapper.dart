import '../reception_speaker_model.dart';
import '../session_day_model.dart';
import '../session_detail_response_model.dart';
import '../session_model.dart';
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

    final rawById = <int, Map<String, dynamic>>{};
    final byDayKey = <String, List<SessionModel>>{};
    final dayMeta = <String, SessionDayModel>{};

    for (final raw in rawSessions) {
      final model = sessionFromV1(raw);
      rawById[model.id] = raw;
      final start = DateTime.tryParse(raw['starts_at']?.toString() ?? '');
      final local = start?.toLocal();
      final key = local != null
          ? '${local.year}-${local.month.toString().padLeft(2, '0')}-${local.day.toString().padLeft(2, '0')}'
          : 'unscheduled';
      byDayKey.putIfAbsent(key, () => []);
      byDayKey[key]!.add(model);
      dayMeta.putIfAbsent(
        key,
        () => SessionDayModel(
          id: key.hashCode & 0x7fffffff,
          title: local != null
              ? '${_weekday(local)}, ${_month(local)} ${local.day}'
              : 'Sessions',
          date: key == 'unscheduled' ? '' : key,
          dateLabel: local != null
              ? '${local.day.toString().padLeft(2, '0')} ${_month(local)}'
              : '',
          dayName: local != null ? _weekday(local) : '',
          tracks: tracks,
        ),
      );
    }

    // Preserve chronological day order.
    final sortedKeys = byDayKey.keys.toList()
      ..sort((a, b) {
        if (a == 'unscheduled') return 1;
        if (b == 'unscheduled') return -1;
        return a.compareTo(b);
      });

    final days = <SessionDayModel>[];
    for (final key in sortedKeys) {
      final meta = dayMeta[key]!;
      days.add(
        SessionDayModel(
          id: meta.id,
          title: meta.title,
          date: meta.date,
          dateLabel: meta.dateLabel,
          dayName: meta.dayName,
          schedules: byDayKey[key]!,
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
      },
      rawById: rawById,
    );
  }

  static SessionModel sessionFromV1(Map<String, dynamic> json) {
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

    final room = json['room'];
    var place = json['session_place']?.toString() ?? '';
    if (place.isEmpty && room is Map) {
      place = (room['name'] ?? '').toString();
    }

    final track = json['track'];
    final trackName = track is Map ? (track['name'] ?? '').toString() : '';

    return SessionModel(
      id: TypeHelper.toInt(json['id']),
      title: json['title']?.toString() ?? '',
      startTime: _formatClock(startsAt),
      endTime: _formatClock(endsAt),
      logoUrl: (json['logo_url'] ?? json['icon_url'] ?? '').toString(),
      day: SessionDayModel(title: dayLabel, date: dayLabel),
      speakers: speakers,
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

    return SessionDetailModel(
      id: model.id,
      startTime: model.startTime,
      endTime: model.endTime,
      title: model.title,
      sessionPlace: model.sessionPlace,
      description: json['description']?.toString() ?? '',
      logo: model.logoUrl,
      tags: model.tags,
      isFeatured: TypeHelper.toBool(json['is_featured']),
      isAllowedToRate: TypeHelper.toBool(json['is_allowed_to_rate']),
      isStream: TypeHelper.toBool(json['is_stream']),
      streamLink: (json['stream_link'] ?? json['stream_url'])?.toString(),
      speakers: model.speakers,
      day: model.day,
      track: trackName,
    );
  }

  /// Client-side filter matching the old mobile payload filters.
  static List<Map<String, dynamic>> filterRaw({
    required List<Map<String, dynamic>> sessions,
    int? trackId,
    String? tag,
    int? speakerId,
    String? search,
  }) {
    final q = search?.trim().toLowerCase() ?? '';
    return sessions.where((json) {
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
