import 'package:intl/intl.dart';

class RoomOccupant {
  final String identity;
  final String name;
  final String? avatarUrl;
  final int? seat;

  const RoomOccupant({
    required this.identity,
    required this.name,
    this.avatarUrl,
    this.seat,
  });

  factory RoomOccupant.fromJson(Map<String, dynamic> json) {
    return RoomOccupant(
      identity: (json['identity'] ?? '').toString(),
      name: (json['name'] ?? '').toString(),
      avatarUrl: json['avatar_url']?.toString(),
      seat: json['seat'] is num ? (json['seat'] as num).toInt() : null,
    );
  }
}

class BreakoutRoom {
  final int id;
  final String uuid;
  final String name;
  final String? description;
  final String purpose;
  final String type;
  final String accessType;
  final bool hasAccessCode;
  final int? capacity;
  final String? posterUrl;
  final String provider;
  final String? meetingUrl;
  final DateTime? startsAt;
  final DateTime? endsAt;
  final int occupied;
  final List<RoomOccupant> occupants;

  const BreakoutRoom({
    required this.id,
    required this.uuid,
    required this.name,
    this.description,
    this.purpose = '',
    this.type = 'custom',
    this.accessType = 'anyone',
    this.hasAccessCode = false,
    this.capacity,
    this.posterUrl,
    this.provider = 'webrtc',
    this.meetingUrl,
    this.startsAt,
    this.endsAt,
    this.occupied = 0,
    this.occupants = const [],
  });

  bool get isPrivate => accessType == 'coded';

  bool get isFull =>
      capacity != null && capacity! > 0 && occupied >= capacity!;

  factory BreakoutRoom.fromJson(Map<String, dynamic> json) {
    return BreakoutRoom(
      id: json['id'] is num ? (json['id'] as num).toInt() : 0,
      uuid: (json['uuid'] ?? '').toString(),
      name: (json['name'] ?? 'Room').toString(),
      description: json['description']?.toString(),
      purpose: (json['purpose'] ?? '').toString(),
      type: (json['type'] ?? 'custom').toString(),
      accessType: (json['access_type'] ?? 'anyone').toString(),
      hasAccessCode: json['has_access_code'] == true,
      capacity: json['capacity'] is num ? (json['capacity'] as num).toInt() : null,
      posterUrl: json['poster_url']?.toString(),
      provider: (json['provider'] ?? 'webrtc').toString(),
      meetingUrl: json['meeting_url']?.toString(),
      startsAt: _parseDate(json['starts_at']),
      endsAt: _parseDate(json['ends_at']),
      occupied: json['occupied'] is num ? (json['occupied'] as num).toInt() : 0,
      occupants: (json['occupants'] as List? ?? [])
          .whereType<Map>()
          .map((e) => RoomOccupant.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
    );
  }

  static DateTime? _parseDate(dynamic raw) {
    if (raw == null) return null;
    return DateTime.tryParse(raw.toString())?.toLocal();
  }

  /// Card subtitle: "Started at 10:10 AM" / "Starts at 12:00 pm".
  String? get startedLabel {
    final d = startsAt;
    if (d == null) return null;
    final time = DateFormat('h:mm a').format(d);
    if (d.isBefore(DateTime.now()) || d.isAtSameMomentAs(DateTime.now())) {
      return 'Started at $time';
    }
    return 'Starts at $time';
  }

  /// Detail sheet: "Starts on 2nd July at 12:00 pm".
  String? get startsOnLabel {
    final d = startsAt;
    if (d == null) return null;
    final day = _ordinal(d.day);
    final month = DateFormat('MMMM').format(d);
    final time = DateFormat('h:mm a').format(d).toLowerCase();
    return 'Starts on $day $month at $time';
  }

  static String _ordinal(int day) {
    if (day >= 11 && day <= 13) return '${day}th';
    return switch (day % 10) {
      1 => '${day}st',
      2 => '${day}nd',
      3 => '${day}rd',
      _ => '${day}th',
    };
  }

  static const typeLabels = <String, String>{
    'workshop': 'Workshop',
    'networking': 'Networking',
    'round_table': 'Round Table',
    'sponsor_demo': 'Sponsor Demo',
    'team': 'Team',
    'private': 'Private',
    'vip': 'VIP',
    'interview': 'Interview',
    'panel': 'Panel',
    'ama': 'AMA',
    'custom': 'Custom',
  };

  String get typeLabel => typeLabels[type] ?? type;
}
