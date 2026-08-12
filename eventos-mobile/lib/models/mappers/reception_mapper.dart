import '../ads_model.dart';
import '../banner_model.dart';
import '../exhibitor_model.dart';
import '../reception_event_model.dart';
import '../reception_model.dart';
import '../reception_speaker_model.dart';
import '../session_day_model.dart';
import '../session_model.dart';
import '../social_links_model.dart';
import '../welcome_video_model.dart';
import '../../utils/helpers/type_helper.dart';

/// Maps EventOS `/api/v1/public/reception` (+ optional site welcome video)
/// into the existing [ReceptionModel] shape so the home UI stays unchanged.
class ReceptionMapper {
  ReceptionMapper._();

  static ReceptionModel fromV1({
    required Map<String, dynamic> data,
    Map<String, dynamic>? welcomeVideo,
  }) {
    final about = data['about'] is Map
        ? Map<String, dynamic>.from(data['about'] as Map)
        : <String, dynamic>{};
    final eventMeta = data['event'] is Map
        ? Map<String, dynamic>.from(data['event'] as Map)
        : <String, dynamic>{};

    final rawSessions = _asMapList(data['sessions']);
    final split = _splitSessions(rawSessions);

    return ReceptionModel(
      event: _eventFromAbout(about, eventMeta, data['banners']),
      ads: _adsFromV1(data['ads']),
      welcomeVideo: _welcomeFromV1(welcomeVideo),
      currentSessions: split.ongoing,
      featuredSessions: split.featured,
      featuredSpeakers: _asMapList(data['speakers'])
          .map((e) => ReceptionSpeakerModel.fromJson(e))
          .toList(),
      featuredExhibitors: _asMapList(data['exhibitors'])
          .map(_exhibitorFromV1)
          .toList(),
      featuredSponsors: _asMapList(data['sponsors'])
          .map(_exhibitorFromV1)
          .toList(),
    );
  }

  /// Accept a JSON list of objects; ignore corrupted PHP Incomplete_Class maps.
  static List<Map<String, dynamic>> _asMapList(dynamic raw) {
    if (raw is! List) return const [];
    return raw
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .where((m) => !m.containsKey('__PHP_Incomplete_Class_Name'))
        .toList();
  }

  static ReceptionEventModel _eventFromAbout(
    Map<String, dynamic> about,
    Map<String, dynamic> eventMeta,
    dynamic bannersRaw,
  ) {
    final startsAt = about['starts_at']?.toString() ?? '';
    final endsAt = about['ends_at']?.toString() ?? '';
    final location = _parseLocation(about['location']);
    final social = about['social'];

    return ReceptionEventModel(
      title: about['name']?.toString() ??
          eventMeta['name']?.toString() ??
          '',
      description: about['description']?.toString() ?? '',
      logoUrl: about['logo_url']?.toString() ?? '',
      timezone: about['timezone']?.toString() ?? '',
      startDate: startsAt,
      endDate: endsAt,
      formatedDate: _formatDateRange(startsAt, endsAt),
      formatedTime:
          _formatTimeRange(startsAt, endsAt, about['timezone']?.toString()),
      isOnline: (about['format']?.toString().toLowerCase() ?? '') == 'online',
      address1: location['address'] ?? '',
      city: location['city'] ?? '',
      country: location['country'] ?? '',
      state: location['state'] ?? '',
      socialLinks: social is Map
          ? _socialFromV1(Map<String, dynamic>.from(social))
          : const SocialLinksModel(),
      communityBanners: _bannersFromV1(bannersRaw),
    );
  }

  static Map<String, String> _parseLocation(dynamic location) {
    if (location == null) return {};
    if (location is String) {
      return {'address': location};
    }
    if (location is Map) {
      final m = Map<String, dynamic>.from(location);
      return {
        'address': (m['address'] ?? m['address_1'] ?? m['name'] ?? '')
            .toString(),
        'city': (m['city'] ?? '').toString(),
        'state': (m['state'] ?? '').toString(),
        'country': (m['country'] ?? '').toString(),
      };
    }
    return {};
  }

  static SocialLinksModel _socialFromV1(Map<String, dynamic> json) {
    return SocialLinksModel(
      hashtagLink: (json['hashtag'] ?? json['hashtag_link'] ?? '').toString(),
      facebookLink: (json['facebook'] ??
              json['facebook_link'] ??
              json['fadebook_link'] ??
              '')
          .toString(),
      twitterLink:
          (json['twitter'] ?? json['x'] ?? json['twitter_link'] ?? '')
              .toString(),
      linkedinLink:
          (json['linkedin'] ?? json['linkedin_link'] ?? '').toString(),
      youtubeLink: (json['youtube'] ?? json['youtube_link'] ?? '').toString(),
      instagramLink:
          (json['instagram'] ?? json['instagram_link'] ?? '').toString(),
    );
  }

  static List<BannerModel> _bannersFromV1(dynamic raw) {
    if (raw is! List) return const [];
    final out = <BannerModel>[];
    for (var i = 0; i < raw.length; i++) {
      final item = raw[i];
      if (item is String && item.isNotEmpty) {
        out.add(BannerModel(id: i + 1, imageUrl: item));
      } else if (item is Map) {
        final m = Map<String, dynamic>.from(item);
        final url = (m['image_url'] ?? m['image'] ?? m['url'] ?? '').toString();
        if (url.isEmpty) continue;
        out.add(
          BannerModel(
            id: i + 1,
            title: (m['title'] ?? '').toString(),
            url: (m['url'] ?? m['link'] ?? '').toString(),
            imageUrl: url,
            status: m['active'] != false && m['status'] != false,
          ),
        );
      }
    }
    return out;
  }

  /// Collects active ad images from `ads.strip` / `ads.sidebar`.
  /// Only images with `is_active != false` are included (matches web AdSidebar).
  static AdsModel _adsFromV1(dynamic raw) {
    if (raw is! Map) return const AdsModel();
    final map = Map<String, dynamic>.from(raw);
    var index = 0;

    List<BannerModel> collect(dynamic list) {
      final images = <BannerModel>[];
      if (list is! List) return images;
      for (final ad in list) {
        if (ad is! Map) continue;
        final adMap = Map<String, dynamic>.from(ad);
        final adImages = adMap['images'];
        if (adImages is! List) continue;
        for (final img in adImages) {
          index++;
          if (img is String && img.isNotEmpty) {
            images.add(
              BannerModel(
                id: index,
                title: (adMap['title'] ?? '').toString(),
                imageUrl: img,
              ),
            );
          } else if (img is Map) {
            final m = Map<String, dynamic>.from(img);
            // Default active when flag is omitted (web: `is_active ?? true`).
            final active = m.containsKey('is_active')
                ? TypeHelper.toBool(m['is_active'])
                : true;
            if (!active) continue;
            final url =
                (m['image_url'] ?? m['url'] ?? m['image'] ?? '').toString();
            if (url.isEmpty) continue;
            images.add(
              BannerModel(
                id: index,
                title: (m['title'] ?? adMap['title'] ?? '').toString(),
                url: (m['redirect_url'] ?? m['link'] ?? m['url'] ?? '')
                    .toString(),
                imageUrl: url,
                status: true,
              ),
            );
          }
        }
      }
      return images;
    }

    return AdsModel(
      sidebar: collect(map['sidebar']),
      strip: collect(map['strip']),
    );
  }

  static WelcomeVideoModel _welcomeFromV1(Map<String, dynamic>? video) {
    if (video == null) return const WelcomeVideoModel();
    final showOnHome = video['show_on_home'] == true;
    final url = (video['url'] ?? video['embed_url'] ?? '').toString();
    if (!showOnHome || url.isEmpty) return const WelcomeVideoModel();
    return WelcomeVideoModel(
      videoType: (video['type'] ?? '').toString(),
      videoUrl: url,
      showOnHomeScreen: true,
    );
  }

  static ExhibitorModel _exhibitorFromV1(Map<String, dynamic> json) {
    return ExhibitorModel.fromJson({
      ...json,
      'stall_no': json['booth'] ?? json['stall_no'] ?? '',
      'exhibitor_type': json['type'] ?? json['exhibitor_type'] ?? '',
      'logo_url': json['logo_url'] ?? '',
      'website': json['website'] ?? '',
    });
  }

  static ({List<SessionModel> ongoing, List<SessionModel> featured})
      _splitSessions(List<Map<String, dynamic>> raw) {
    final now = DateTime.now();
    final ongoing = <SessionModel>[];
    final featured = <SessionModel>[];

    for (final json in raw) {
      final model = _sessionFromV1(json);
      final start = DateTime.tryParse(json['starts_at']?.toString() ?? '');
      final end = DateTime.tryParse(json['ends_at']?.toString() ?? '');
      final isNow = start != null &&
          end != null &&
          !now.isBefore(start.toLocal()) &&
          !now.isAfter(end.toLocal());
      // Ongoing = live now; featured = organizer flag (independent, like web).
      if (isNow) ongoing.add(model);
      if (TypeHelper.toBool(json['is_featured'])) featured.add(model);
    }

    return (ongoing: ongoing, featured: featured);
  }

  static SessionModel _sessionFromV1(Map<String, dynamic> json) {
    final startsAt = json['starts_at']?.toString() ?? '';
    final endsAt = json['ends_at']?.toString() ?? '';
    final start = DateTime.tryParse(startsAt);
    final dayLabel = start != null
        ? '${_weekday(start.toLocal())}, ${_month(start.toLocal())} ${start.toLocal().day}'
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

    return SessionModel(
      id: TypeHelper.toInt(json['id']),
      uuid: (json['id'] ?? '').toString(),
      title: json['title']?.toString() ?? '',
      startTime: _formatClock(startsAt),
      endTime: _formatClock(endsAt),
      startsAt: startsAt.isEmpty ? null : startsAt,
      endsAt: endsAt.isEmpty ? null : endsAt,
      status: json['status']?.toString(),
      logoUrl: json['logo_url']?.toString() ?? '',
      day: SessionDayModel(title: dayLabel, date: dayLabel),
      speakers: speakers,
      tags: (json['tags'] as List? ?? []).map((e) => e.toString()).toList(),
      sessionPlace: place,
    );
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

  static String _formatDateRange(String startIso, String endIso) {
    final start = DateTime.tryParse(startIso);
    final end = DateTime.tryParse(endIso);
    if (start == null) return '';
    final s = start.toLocal();
    if (end == null) {
      return '${_month(s)} ${s.day}, ${s.year}';
    }
    final e = end.toLocal();
    if (s.year == e.year && s.month == e.month && s.day == e.day) {
      return '${_month(s)} ${s.day}, ${s.year}';
    }
    if (s.year == e.year && s.month == e.month) {
      return '${_month(s)} ${s.day}–${e.day}, ${s.year}';
    }
    return '${_month(s)} ${s.day}, ${s.year} – ${_month(e)} ${e.day}, ${e.year}';
  }

  static String _formatTimeRange(String startIso, String endIso, String? tz) {
    final start = _formatClock(startIso);
    final end = _formatClock(endIso);
    if (start.isEmpty) return '';
    final range = end.isEmpty ? start : '$start – $end';
    if (tz == null || tz.isEmpty) return range;
    return '$range ($tz)';
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
