class ReceptionPayload {
  const ReceptionPayload({
    required this.about,
    required this.event,
    required this.banners,
    required this.ads,
    required this.sessions,
    required this.speakers,
    required this.exhibitors,
    required this.sponsors,
  });

  factory ReceptionPayload.fromJson(Map<String, dynamic> json) {
    final ads = json['ads'];
    return ReceptionPayload(
      about: ReceptionAbout.fromJson(
        (json['about'] as Map?)?.cast<String, dynamic>() ?? const {},
      ),
      event: ReceptionEvent.fromJson(
        (json['event'] as Map?)?.cast<String, dynamic>() ?? const {},
      ),
      banners: (json['banners'] as List?)
              ?.map((e) => e?.toString() ?? '')
              .where((e) => e.isNotEmpty)
              .toList() ??
          const [],
      ads: ReceptionAds.fromJson(
        ads is Map ? ads.cast<String, dynamic>() : const {},
      ),
      sessions: (json['sessions'] as List?)
              ?.whereType<Map>()
              .map((e) => ReceptionSession.fromJson(e.cast<String, dynamic>()))
              .toList() ??
          const [],
      speakers: (json['speakers'] as List?)
              ?.whereType<Map>()
              .map((e) => ReceptionSpeaker.fromJson(e.cast<String, dynamic>()))
              .toList() ??
          const [],
      exhibitors: (json['exhibitors'] as List?)
              ?.whereType<Map>()
              .map((e) => ReceptionPartner.fromJson(e.cast<String, dynamic>()))
              .toList() ??
          const [],
      sponsors: (json['sponsors'] as List?)
              ?.whereType<Map>()
              .map((e) => ReceptionPartner.fromJson(e.cast<String, dynamic>()))
              .toList() ??
          const [],
    );
  }

  final ReceptionAbout about;
  final ReceptionEvent event;
  final List<String> banners;
  final ReceptionAds ads;
  final List<ReceptionSession> sessions;
  final List<ReceptionSpeaker> speakers;
  final List<ReceptionPartner> exhibitors;
  final List<ReceptionPartner> sponsors;
}

class ReceptionAbout {
  const ReceptionAbout({
    required this.name,
    this.description,
    this.format,
    this.startsAt,
    this.endsAt,
    this.timezone,
    this.location,
    this.logoUrl,
    this.coverUrl,
    this.social = const {},
  });

  factory ReceptionAbout.fromJson(Map<String, dynamic> json) {
    final social = json['social'];
    final socialMap = <String, String>{};
    if (social is Map) {
      for (final entry in social.entries) {
        final value = entry.value?.toString();
        if (value != null && value.isNotEmpty) {
          socialMap[entry.key.toString()] = value;
        }
      }
    }

    return ReceptionAbout(
      name: json['name'] as String? ?? '',
      description: json['description'] as String?,
      format: json['format'] as String?,
      startsAt: json['starts_at'] as String?,
      endsAt: json['ends_at'] as String?,
      timezone: json['timezone'] as String?,
      location: json['location'],
      logoUrl: json['logo_url'] as String?,
      coverUrl: json['cover_url'] as String?,
      social: socialMap,
    );
  }

  final String name;
  final String? description;
  final String? format;
  final String? startsAt;
  final String? endsAt;
  final String? timezone;
  final dynamic location;
  final String? logoUrl;
  final String? coverUrl;
  final Map<String, String> social;

  String get locationText {
    final loc = location;
    if (loc == null) return '';
    if (loc is String) return loc;
    if (loc is Map) {
      return (loc['address'] ?? loc['url'] ?? '').toString();
    }
    return '';
  }
}

class ReceptionEvent {
  const ReceptionEvent({
    required this.uuid,
    required this.name,
    required this.slug,
  });

  factory ReceptionEvent.fromJson(Map<String, dynamic> json) {
    return ReceptionEvent(
      uuid: json['uuid']?.toString() ?? '',
      name: json['name'] as String? ?? '',
      slug: json['slug'] as String? ?? '',
    );
  }

  final String uuid;
  final String name;
  final String slug;
}

class ReceptionAds {
  const ReceptionAds({this.strip = const [], this.sidebar = const []});

  factory ReceptionAds.fromJson(Map<String, dynamic> json) {
    return ReceptionAds(
      strip: (json['strip'] as List?)
              ?.whereType<Map>()
              .map((e) => ReceptionAd.fromJson(e.cast<String, dynamic>()))
              .toList() ??
          const [],
      sidebar: (json['sidebar'] as List?)
              ?.whereType<Map>()
              .map((e) => ReceptionAd.fromJson(e.cast<String, dynamic>()))
              .toList() ??
          const [],
    );
  }

  final List<ReceptionAd> strip;
  final List<ReceptionAd> sidebar;
}

class ReceptionAd {
  const ReceptionAd({
    required this.id,
    required this.title,
    required this.placement,
    this.images = const [],
  });

  factory ReceptionAd.fromJson(Map<String, dynamic> json) {
    return ReceptionAd(
      id: json['id']?.toString() ?? '',
      title: json['title'] as String? ?? '',
      placement: json['placement'] as String? ?? '',
      images: (json['images'] as List?)
              ?.whereType<Map>()
              .map((e) => e.cast<String, dynamic>())
              .toList() ??
          const [],
    );
  }

  final String id;
  final String title;
  final String placement;
  final List<Map<String, dynamic>> images;

  String? get imageUrl {
    for (final image in images) {
      final url = image['url']?.toString();
      if (url != null && url.isNotEmpty) return url;
    }
    return null;
  }
}

class ReceptionSession {
  const ReceptionSession({
    required this.id,
    required this.title,
    this.description,
    this.startsAt,
    this.endsAt,
    this.timezone,
    this.status,
    this.isFeatured = false,
    this.iconUrl,
    this.logoUrl,
    this.sessionPlace,
    this.streamUrl,
    this.speakers = const [],
  });

  factory ReceptionSession.fromJson(Map<String, dynamic> json) {
    return ReceptionSession(
      id: json['id']?.toString() ?? '',
      title: json['title'] as String? ?? '',
      description: json['description'] as String?,
      startsAt: json['starts_at'] as String?,
      endsAt: json['ends_at'] as String?,
      timezone: json['timezone'] as String?,
      status: json['status'] as String?,
      isFeatured: json['is_featured'] == true,
      iconUrl: json['icon_url'] as String?,
      logoUrl: json['logo_url'] as String?,
      sessionPlace: json['session_place'] as String?,
      streamUrl: json['stream_url'] as String? ?? json['stream_link'] as String?,
      speakers: (json['speakers'] as List?)
              ?.whereType<Map>()
              .map((e) => ReceptionSessionSpeaker.fromJson(e.cast<String, dynamic>()))
              .toList() ??
          const [],
    );
  }

  final String id;
  final String title;
  final String? description;
  final String? startsAt;
  final String? endsAt;
  final String? timezone;
  final String? status;
  final bool isFeatured;
  final String? iconUrl;
  final String? logoUrl;
  final String? sessionPlace;
  final String? streamUrl;
  final List<ReceptionSessionSpeaker> speakers;

  String? get imageUrl => logoUrl ?? iconUrl;
}

class ReceptionSessionSpeaker {
  const ReceptionSessionSpeaker({
    required this.id,
    this.name,
    this.imageUrl,
  });

  factory ReceptionSessionSpeaker.fromJson(Map<String, dynamic> json) {
    final profile = json['profile'];
    String? image;
    if (profile is Map) {
      image = profile['image_url']?.toString();
    }
    return ReceptionSessionSpeaker(
      id: json['id']?.toString() ?? '',
      name: json['name'] as String?,
      imageUrl: image,
    );
  }

  final String id;
  final String? name;
  final String? imageUrl;
}

class ReceptionSpeaker {
  const ReceptionSpeaker({
    required this.id,
    this.name,
    this.designation = '',
    this.company = '',
    this.category = '',
    this.imageUrl,
  });

  factory ReceptionSpeaker.fromJson(Map<String, dynamic> json) {
    return ReceptionSpeaker(
      id: json['id']?.toString() ?? '',
      name: json['name'] as String?,
      designation: json['designation'] as String? ?? '',
      company: json['company'] as String? ?? '',
      category: json['category'] as String? ?? '',
      imageUrl: json['image_url'] as String?,
    );
  }

  final String id;
  final String? name;
  final String designation;
  final String company;
  final String category;
  final String? imageUrl;

  String get subtitle {
    final parts = [designation, company].where((e) => e.trim().isNotEmpty);
    return parts.join(' · ');
  }
}

class ReceptionPartner {
  const ReceptionPartner({
    required this.id,
    required this.name,
    required this.type,
    this.website,
    this.booth,
    this.logoUrl,
  });

  factory ReceptionPartner.fromJson(Map<String, dynamic> json) {
    return ReceptionPartner(
      id: json['id']?.toString() ?? '',
      name: json['name'] as String? ?? '',
      type: json['type'] as String? ?? '',
      website: json['website'] as String?,
      booth: json['booth'] as String?,
      logoUrl: json['logo_url'] as String?,
    );
  }

  final String id;
  final String name;
  final String type;
  final String? website;
  final String? booth;
  final String? logoUrl;
}
