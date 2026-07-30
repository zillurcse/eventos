import '../exhibitor_model.dart';
import '../exhibitor_page_model.dart';
import '../../utils/helpers/type_helper.dart';

/// Maps EventOS public exhibitor payloads into existing exhibitor UI models.
class ExhibitorMapper {
  ExhibitorMapper._();

  static ExhibitorPageModel pageFromV1(
    Map<String, dynamic> data, {
    String? typeFilter,
    String? search,
  }) {
    final exhibitors = (data['exhibitors'] as List? ?? [])
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e));
    final sponsors = (data['sponsors'] as List? ?? [])
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e));

    var combined = <Map<String, dynamic>>[
      ...exhibitors,
      ...sponsors,
    ];

    if (typeFilter != null && typeFilter.isNotEmpty) {
      final t = typeFilter.toLowerCase();
      combined = combined
          .where((e) => (e['type'] ?? '').toString().toLowerCase() == t)
          .toList();
    }

    final q = search?.trim().toLowerCase() ?? '';
    if (q.isNotEmpty) {
      combined = combined.where((e) {
        final name = (e['name'] ?? '').toString().toLowerCase();
        final cat = (e['category'] ?? '').toString().toLowerCase();
        final desc = (e['description'] ?? '').toString().toLowerCase();
        return name.contains(q) || cat.contains(q) || desc.contains(q);
      }).toList();
    }

    return ExhibitorPageModel(
      exhibitors: combined.map(fromV1).toList(),
    );
  }

  static ExhibitorModel fromV1(Map<String, dynamic> json) {
    final uuid = (json['id'] ?? json['uuid'] ?? '').toString();
    final social = json['social'] is Map
        ? Map<String, dynamic>.from(json['social'] as Map)
        : <String, dynamic>{};
    final contact = json['contact'] is Map
        ? Map<String, dynamic>.from(json['contact'] as Map)
        : <String, dynamic>{};
    final spotlight = json['spotlight'] is Map
        ? Map<String, dynamic>.from(json['spotlight'] as Map)
        : <String, dynamic>{};
    final location = json['location'] is Map
        ? Map<String, dynamic>.from(json['location'] as Map)
        : <String, dynamic>{};

    final products = (json['products'] as List? ?? [])
        .whereType<Map>()
        .map((p) => Map<String, dynamic>.from(p))
        .toList();
    final documents = (json['documents'] as List? ?? [])
        .whereType<Map>()
        .map((d) => Map<String, dynamic>.from(d))
        .toList();
    final members = (json['members'] as List? ?? [])
        .whereType<Map>()
        .map((m) => Map<String, dynamic>.from(m))
        .toList();

    return ExhibitorModel.fromJson({
      'id': uuid,
      'slug': uuid,
      'name': json['name'] ?? '',
      'description': json['about'] ?? json['description'] ?? '',
      'website': json['website'] ?? '',
      'logo_url': json['logo_url'] ?? '',
      'stall_no': json['booth'] ?? '',
      'exhibitor_type': json['type'] ?? '',
      'is_featured': json['is_featured'],
      'email': contact['email'],
      'mobile_number': contact['phone'],
      'facebook': social['facebook'],
      'instagram': social['instagram'],
      'whatsapp': social['whatsapp'],
      'twitter': social['twitter'],
      'linkedin': social['linkedin'],
      'youtube': social['youtube'],
      'address': location['address'],
      'location': location['url'] ?? location['address'],
      'spotlight_banner_url': spotlight['type'] == 'image'
          ? spotlight['url']
          : null,
      'spotlight_banner_video_youtube': spotlight['type'] == 'video'
          ? spotlight['url']
          : null,
      'spotlight_type': spotlight['type'],
      'allow_reating': TypeHelper.toBool(json['can_rate']) ? 1 : 0,
      'products': products,
      'peoples': members
          .map((m) => {
                'name': m['name'],
                'designation': m['designation'],
                'company': m['company'],
                'image': m['avatar_url'],
                'image_url': m['avatar_url'],
              })
          .toList(),
      'brochure': documents.isNotEmpty
          ? (documents.first['url'] ?? documents.first['title'])
          : null,
      'videos_data': const [],
      'ctas': json['cta'] ?? [],
      'about_title': 'About',
      'product_title': 'Products',
      'representatives_title': 'Team',
      'is_active': true,
    });
  }

  /// Detail endpoint returns `{ data: {...} }` (booth object, not wrapped in exhibitor).
  static ExhibitorModel detailFromV1Response(Map<String, dynamic> body) {
    final data = body['data'] is Map
        ? Map<String, dynamic>.from(body['data'] as Map)
        : (body['exhibitor'] is Map
            ? Map<String, dynamic>.from(body['exhibitor'] as Map)
            : body);
    return fromV1(data);
  }
}
