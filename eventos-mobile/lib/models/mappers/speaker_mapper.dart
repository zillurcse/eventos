import '../session_model.dart';
import '../speaker_detail_model.dart';
import '../speaker_item_model.dart';
import '../speaker_page_model.dart';
import 'session_mapper.dart';
import '../../utils/helpers/type_helper.dart';

/// Maps EventOS `GET /api/v1/public/speakers` into existing speaker UI models.
class SpeakerMapper {
  SpeakerMapper._();

  static SpeakerPageModel pageFromV1(Map<String, dynamic> data) {
    final list = (data['speakers'] as List? ?? [])
        .whereType<Map>()
        .map((e) => itemFromV1(Map<String, dynamic>.from(e)))
        .toList();
    return SpeakerPageModel(speakers: list);
  }

  static SpeakerItemModel itemFromV1(Map<String, dynamic> json) {
    return SpeakerItemModel.fromJson({
      'id': json['id'],
      'name': json['name'] ?? '',
      'image_url': json['image_url'],
      'designation': json['designation'] ?? '',
      'category': json['category'],
      'is_loved': json['is_loved'],
      'haveNotes': false,
      'notes': [],
    });
  }

  static SpeakerDetailModel detailFromV1(
    Map<String, dynamic> json, {
    List<SessionModel> sessions = const [],
  }) {
    final social = json['social'] is Map
        ? Map<String, dynamic>.from(json['social'] as Map)
        : <String, dynamic>{};

    return SpeakerDetailModel(
      id: TypeHelper.toInt(json['id']),
      name: json['name']?.toString() ?? '',
      image: () {
        final raw = (json['image_url'] ?? json['image'] ?? '').toString();
        if (raw.isEmpty) return null;
        return raw.startsWith('http')
            ? raw
            : 'https://admin.expouse.com/storage/$raw';
      }(),
      designation: json['designation']?.toString() ?? '',
      category: json['category']?.toString(),
      bio: json['bio']?.toString(),
      facebook: social['facebook']?.toString(),
      linkedin: social['linkedin']?.toString(),
      twitter: social['twitter']?.toString(),
      instagram: social['instagram']?.toString(),
      allowRating: TypeHelper.toBool(json['can_rate']),
      isFeatured: TypeHelper.toBool(json['is_featured']),
      sessions: sessions,
    );
  }

  static List<Map<String, dynamic>> filterRaw({
    required List<Map<String, dynamic>> speakers,
    String? search,
    String? sortBy,
  }) {
    var list = List<Map<String, dynamic>>.from(speakers);
    final q = search?.trim().toLowerCase() ?? '';
    if (q.isNotEmpty) {
      list = list.where((s) {
        final name = (s['name'] ?? '').toString().toLowerCase();
        final des = (s['designation'] ?? '').toString().toLowerCase();
        final company = (s['company'] ?? '').toString().toLowerCase();
        return name.contains(q) || des.contains(q) || company.contains(q);
      }).toList();
    }

    if (sortBy == 'name_desc') {
      list.sort((a, b) =>
          (b['name'] ?? '').toString().compareTo((a['name'] ?? '').toString()));
    } else if (sortBy == 'name_asc' || sortBy == 'name') {
      list.sort((a, b) =>
          (a['name'] ?? '').toString().compareTo((b['name'] ?? '').toString()));
    }
    return list;
  }

  static List<SessionModel> sessionsForSpeaker({
    required List<Map<String, dynamic>> allSessions,
    required int speakerId,
  }) {
    final out = <SessionModel>[];
    for (final raw in allSessions) {
      final speakers = raw['speakers'];
      if (speakers is! List) continue;
      final match = speakers.any((s) {
        if (s is! Map) return false;
        return TypeHelper.toInt(s['id']) == speakerId;
      });
      if (match) out.add(SessionMapper.sessionFromV1(raw));
    }
    return out;
  }
}
