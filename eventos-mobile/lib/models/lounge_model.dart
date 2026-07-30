enum LoungeTableKind { attendee, exhibitor, sponsor }

enum LoungeTableDesign { round, boardroom, lounge }

class LoungeOccupant {
  final String identity;
  final String name;
  final String? avatarUrl;
  final int? seat;

  const LoungeOccupant({
    required this.identity,
    required this.name,
    this.avatarUrl,
    this.seat,
  });

  factory LoungeOccupant.fromJson(Map<String, dynamic> json) {
    return LoungeOccupant(
      identity: (json['identity'] ?? '').toString(),
      name: (json['name'] ?? '').toString(),
      avatarUrl: json['avatar_url']?.toString(),
      seat: json['seat'] is num ? (json['seat'] as num).toInt() : null,
    );
  }
}

class LoungeTable {
  final String id;
  final LoungeTableKind kind;
  final String name;
  final int capacity;
  final String? imageUrl;
  final LoungeTableDesign design;
  final String? accent;
  final List<LoungeOccupant> occupants;
  final int occupied;
  final bool live;
  final bool full;

  const LoungeTable({
    required this.id,
    required this.kind,
    required this.name,
    required this.capacity,
    this.imageUrl,
    this.design = LoungeTableDesign.round,
    this.accent,
    this.occupants = const [],
    this.occupied = 0,
    this.live = false,
    this.full = false,
  });

  factory LoungeTable.fromJson(Map<String, dynamic> json) {
    final kindRaw = (json['kind'] ?? 'attendee').toString();
    final designRaw = (json['design'] ?? 'round').toString();
    return LoungeTable(
      id: (json['id'] ?? '').toString(),
      kind: switch (kindRaw) {
        'exhibitor' => LoungeTableKind.exhibitor,
        'sponsor' => LoungeTableKind.sponsor,
        _ => LoungeTableKind.attendee,
      },
      name: (json['name'] ?? 'Table').toString(),
      capacity: json['capacity'] is num ? (json['capacity'] as num).toInt() : 4,
      imageUrl: json['image_url']?.toString(),
      design: switch (designRaw) {
        'boardroom' => LoungeTableDesign.boardroom,
        'lounge' => LoungeTableDesign.lounge,
        _ => LoungeTableDesign.round,
      },
      accent: json['accent']?.toString(),
      occupants: (json['occupants'] as List? ?? [])
          .whereType<Map>()
          .map((e) => LoungeOccupant.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
      occupied: json['occupied'] is num ? (json['occupied'] as num).toInt() : 0,
      live: json['live'] == true,
      full: json['full'] == true,
    );
  }
}

class LoungeTabs {
  final List<LoungeTable> attendees;
  final List<LoungeTable> exhibitors;
  final List<LoungeTable> sponsors;

  const LoungeTabs({
    this.attendees = const [],
    this.exhibitors = const [],
    this.sponsors = const [],
  });

  factory LoungeTabs.fromJson(Map<String, dynamic> json) {
    List<LoungeTable> parse(String key) => (json[key] as List? ?? [])
        .whereType<Map>()
        .map((e) => LoungeTable.fromJson(Map<String, dynamic>.from(e)))
        .toList();

    return LoungeTabs(
      attendees: parse('attendees'),
      exhibitors: parse('exhibitors'),
      sponsors: parse('sponsors'),
    );
  }

  List<LoungeTable> forKind(LoungeTableKind kind) => switch (kind) {
        LoungeTableKind.attendee => attendees,
        LoungeTableKind.exhibitor => exhibitors,
        LoungeTableKind.sponsor => sponsors,
      };

  List<LoungeTable> get all => [...attendees, ...exhibitors, ...sponsors];
}

/// LiveKit join payload from POST /lounge/tables/{id}/join.
class LoungeJoinConfig {
  final String provider;
  final String url;
  final String room;
  final String token;
  final String title;
  final int? seat;

  const LoungeJoinConfig({
    required this.provider,
    required this.url,
    required this.room,
    required this.token,
    required this.title,
    this.seat,
  });

  factory LoungeJoinConfig.fromJson(Map<String, dynamic> json) {
    return LoungeJoinConfig(
      provider: (json['provider'] ?? 'webrtc').toString(),
      url: (json['url'] ?? '').toString(),
      room: (json['room'] ?? '').toString(),
      token: (json['token'] ?? '').toString(),
      title: (json['title'] ?? 'Table').toString(),
      seat: json['seat'] is num ? (json['seat'] as num).toInt() : null,
    );
  }
}
