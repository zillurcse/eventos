/// One badge pass for the signed-in attendee at the current event.
///
/// Mirrors `GET /events/{uuid}/my/badges` - one entry per participation, not
/// per person (speaker + exhibitor team = two passes).
class ParticipantBadge {
  final String participationId;
  final String roleLabel;
  final BadgeDesign design;
  final Map<String, String> data;

  const ParticipantBadge({
    required this.participationId,
    required this.roleLabel,
    required this.design,
    required this.data,
  });

  factory ParticipantBadge.fromJson(Map<String, dynamic> json) {
    final designRaw = json['design'];
    final dataRaw = json['data'];
    return ParticipantBadge(
      participationId: (json['participation_id'] ?? '').toString(),
      roleLabel: (json['role_label'] ?? '').toString(),
      design: designRaw is Map
          ? BadgeDesign.fromJson(Map<String, dynamic>.from(designRaw))
          : const BadgeDesign(),
      data: dataRaw is Map
          ? dataRaw.map((k, v) => MapEntry(k.toString(), v?.toString() ?? ''))
          : const {},
    );
  }

  String get fullName => data['full_name'] ?? '';
  String get qrcode => data['qrcode'] ?? participationId;
  String get eventName => data['event_name'] ?? '';

  bool get hasBack {
    final boxes = design.badgeJson['backBoxes'];
    return boxes is List && boxes.isNotEmpty;
  }
}

class BadgeDesign {
  final int? id;
  final String name;
  final Map<String, dynamic> badgeJson;

  const BadgeDesign({
    this.id,
    this.name = '',
    this.badgeJson = const {},
  });

  factory BadgeDesign.fromJson(Map<String, dynamic> json) {
    final raw = json['badge_json'];
    return BadgeDesign(
      id: json['id'] is int
          ? json['id'] as int
          : int.tryParse('${json['id'] ?? ''}'),
      name: (json['name'] ?? '').toString(),
      badgeJson: raw is Map
          ? Map<String, dynamic>.from(raw)
          : const <String, dynamic>{},
    );
  }
}
