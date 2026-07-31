class MeetingPerson {
  final String name;
  final String company;
  final String jobTitle;
  final String? avatarUrl;

  const MeetingPerson({
    required this.name,
    this.company = '',
    this.jobTitle = '',
    this.avatarUrl,
  });

  factory MeetingPerson.fromJson(Map<String, dynamic> json) {
    return MeetingPerson(
      name: (json['name'] ?? '').toString(),
      company: (json['company'] ?? '').toString(),
      jobTitle: (json['job_title'] ?? '').toString(),
      avatarUrl: json['avatar_url']?.toString(),
    );
  }

  String get subtitle {
    return [jobTitle, company].where((s) => s.isNotEmpty).join(', ');
  }
}

class MeetingAllocatedTable {
  final String id;
  final String name;
  final int capacity;
  final String design;
  final String? imageUrl;
  final String? accent;

  const MeetingAllocatedTable({
    required this.id,
    required this.name,
    this.capacity = 0,
    this.design = 'round',
    this.imageUrl,
    this.accent,
  });

  factory MeetingAllocatedTable.fromJson(Map<String, dynamic> json) {
    return MeetingAllocatedTable(
      id: (json['id'] ?? '').toString(),
      name: (json['name'] ?? '').toString(),
      capacity: json['capacity'] is num ? (json['capacity'] as num).toInt() : 0,
      design: (json['design'] ?? 'round').toString(),
      imageUrl: json['image_url']?.toString(),
      accent: json['accent']?.toString(),
    );
  }
}

class Meeting {
  final String id;
  final String? title;
  final String? agenda;
  final String? location;
  final String type;
  final String status;
  final String direction;
  final String myRsvp;
  final bool canRespond;
  final String? startsAt;
  final String? endsAt;
  final String? date;
  final String? slot;
  final MeetingAllocatedTable? allocatedTable;
  final MeetingPerson? counterpart;
  final String source;
  final String? exhibitor;
  final String? createdAt;

  const Meeting({
    required this.id,
    this.title,
    this.agenda,
    this.location,
    this.type = 'one_on_one',
    required this.status,
    required this.direction,
    this.myRsvp = 'pending',
    this.canRespond = false,
    this.startsAt,
    this.endsAt,
    this.date,
    this.slot,
    this.allocatedTable,
    this.counterpart,
    this.source = 'delegate',
    this.exhibitor,
    this.createdAt,
  });

  factory Meeting.fromJson(Map<String, dynamic> json) {
    MeetingAllocatedTable? table;
    final rawTable = json['allocated_table'];
    if (rawTable is Map) {
      table = MeetingAllocatedTable.fromJson(
        Map<String, dynamic>.from(rawTable),
      );
    }

    MeetingPerson? person;
    final rawPerson = json['counterpart'];
    if (rawPerson is Map) {
      person = MeetingPerson.fromJson(Map<String, dynamic>.from(rawPerson));
    }

    return Meeting(
      id: (json['id'] ?? '').toString(),
      title: json['title']?.toString(),
      agenda: json['agenda']?.toString(),
      location: json['location']?.toString(),
      type: (json['type'] ?? 'one_on_one').toString(),
      status: (json['status'] ?? 'requested').toString(),
      direction: (json['direction'] ?? 'outgoing').toString(),
      myRsvp: (json['my_rsvp'] ?? 'pending').toString(),
      canRespond: json['can_respond'] == true,
      startsAt: json['starts_at']?.toString(),
      endsAt: json['ends_at']?.toString(),
      date: json['date']?.toString(),
      slot: json['slot']?.toString(),
      allocatedTable: table,
      counterpart: person,
      source: (json['source'] ?? 'delegate').toString(),
      exhibitor: json['exhibitor']?.toString(),
      createdAt: json['created_at']?.toString(),
    );
  }

  Meeting copyWith({
    String? status,
    bool? canRespond,
    String? myRsvp,
    MeetingAllocatedTable? allocatedTable,
  }) {
    return Meeting(
      id: id,
      title: title,
      agenda: agenda,
      location: location,
      type: type,
      status: status ?? this.status,
      direction: direction,
      myRsvp: myRsvp ?? this.myRsvp,
      canRespond: canRespond ?? this.canRespond,
      startsAt: startsAt,
      endsAt: endsAt,
      date: date,
      slot: slot,
      allocatedTable: allocatedTable ?? this.allocatedTable,
      counterpart: counterpart,
      source: source,
      exhibitor: exhibitor,
      createdAt: createdAt,
    );
  }

  String get displayLocation {
    if (allocatedTable != null) {
      return location?.isNotEmpty == true
          ? location!
          : allocatedTable!.name;
    }
    if (location != null && location!.isNotEmpty) return location!;
    return '';
  }

  bool get hasLocation => displayLocation.isNotEmpty;
}

class MeetingPartner {
  final String id;
  final String name;
  final String role;
  final String company;
  final String jobTitle;
  final String? avatarUrl;

  const MeetingPartner({
    required this.id,
    required this.name,
    this.role = 'attendee',
    this.company = '',
    this.jobTitle = '',
    this.avatarUrl,
  });

  factory MeetingPartner.fromJson(Map<String, dynamic> json) {
    return MeetingPartner(
      id: (json['id'] ?? '').toString(),
      name: (json['name'] ?? '').toString(),
      role: (json['role'] ?? 'attendee').toString(),
      company: (json['company'] ?? '').toString(),
      jobTitle: (json['job_title'] ?? '').toString(),
      avatarUrl: json['avatar_url']?.toString(),
    );
  }

  String get subtitle {
    return [jobTitle, company].where((s) => s.isNotEmpty).join(', ');
  }
}

class MeetingCapabilities {
  final bool enabled;
  final String role;
  final List<String> allowedRoles;
  final int? requestsMax;
  final int? confirmedMax;
  final int requestsUsed;
  final int confirmedUsed;
  final int slotDuration;
  final bool intelligent;
  final List<String> locations;
  final bool canRequest;

  const MeetingCapabilities({
    this.enabled = true,
    this.role = '',
    this.allowedRoles = const [],
    this.requestsMax,
    this.confirmedMax,
    this.requestsUsed = 0,
    this.confirmedUsed = 0,
    this.slotDuration = 30,
    this.intelligent = false,
    this.locations = const [],
    this.canRequest = true,
  });

  factory MeetingCapabilities.fromJson(Map<String, dynamic> json) {
    final restrictions = json['restrictions'] is Map
        ? Map<String, dynamic>.from(json['restrictions'] as Map)
        : <String, dynamic>{};
    final roles = json['allowed_roles'];
    final locs = json['locations'];

    return MeetingCapabilities(
      enabled: json['enabled'] != false,
      role: (json['role'] ?? '').toString(),
      allowedRoles: roles is List
          ? roles.map((e) => e.toString()).toList()
          : const [],
      requestsMax: restrictions['requests'] is num
          ? (restrictions['requests'] as num).toInt()
          : null,
      confirmedMax: restrictions['confirmed'] is num
          ? (restrictions['confirmed'] as num).toInt()
          : null,
      requestsUsed: restrictions['requests_used'] is num
          ? (restrictions['requests_used'] as num).toInt()
          : 0,
      confirmedUsed: restrictions['confirmed_used'] is num
          ? (restrictions['confirmed_used'] as num).toInt()
          : 0,
      slotDuration: json['slot_duration'] is num
          ? (json['slot_duration'] as num).toInt()
          : 30,
      intelligent: json['intelligent'] == true,
      locations: locs is List
          ? locs.map((e) => e.toString()).toList()
          : const [],
      canRequest: json['can_request'] != false,
    );
  }

  MeetingCapabilities copyWith({
    List<String>? allowedRoles,
    int? requestsUsed,
    bool? canRequest,
  }) {
    return MeetingCapabilities(
      enabled: enabled,
      role: role,
      allowedRoles: allowedRoles ?? this.allowedRoles,
      requestsMax: requestsMax,
      confirmedMax: confirmedMax,
      requestsUsed: requestsUsed ?? this.requestsUsed,
      confirmedUsed: confirmedUsed,
      slotDuration: slotDuration,
      intelligent: intelligent,
      locations: locations,
      canRequest: canRequest ?? this.canRequest,
    );
  }
}

class LoungeBusySlot {
  final String date;
  final String slot;

  const LoungeBusySlot({required this.date, required this.slot});

  factory LoungeBusySlot.fromJson(Map<String, dynamic> json) {
    return LoungeBusySlot(
      date: (json['date'] ?? '').toString(),
      slot: (json['slot'] ?? '').toString(),
    );
  }
}

class LoungeAvailability {
  final bool enabled;
  final bool intelligent;
  final bool slotsOpenAll;
  final String timezone;
  final List<String> dates;
  final Map<String, List<String>> slots;
  final List<LoungeBusySlot> busy;
  final String format;
  final bool locationRequired;
  final List<String> locations;

  const LoungeAvailability({
    this.enabled = false,
    this.intelligent = false,
    this.slotsOpenAll = false,
    this.timezone = 'UTC',
    this.dates = const [],
    this.slots = const {},
    this.busy = const [],
    this.format = 'online',
    this.locationRequired = false,
    this.locations = const [],
  });

  factory LoungeAvailability.fromJson(Map<String, dynamic> json) {
    final rawSlots = json['slots'];
    final parsedSlots = <String, List<String>>{};
    if (rawSlots is Map) {
      for (final entry in rawSlots.entries) {
        final value = entry.value;
        parsedSlots[entry.key.toString()] = value is List
            ? value.map((e) => e.toString()).toList()
            : const [];
      }
    }

    final rawBusy = json['busy'];
    final dates = json['dates'];
    final locs = json['locations'];

    return LoungeAvailability(
      enabled: json['enabled'] == true,
      intelligent: json['intelligent'] == true,
      slotsOpenAll: json['slots_open_all'] == true,
      timezone: (json['timezone'] ?? 'UTC').toString(),
      dates: dates is List
          ? dates.map((e) => e.toString()).toList()
          : const [],
      slots: parsedSlots,
      busy: rawBusy is List
          ? rawBusy
              .whereType<Map>()
              .map((e) => LoungeBusySlot.fromJson(Map<String, dynamic>.from(e)))
              .toList()
          : const [],
      format: (json['format'] ?? 'online').toString(),
      locationRequired: json['location_required'] == true,
      locations: locs is List
          ? locs.map((e) => e.toString()).toList()
          : const [],
    );
  }
}
