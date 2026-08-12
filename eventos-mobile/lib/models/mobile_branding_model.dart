import '../../utils/helpers/type_helper.dart';

/// Resolved mobile brand from `/public/mobile-branding` or `/public/site`.
class MobileBranding {
  final String source; // platform | organizer
  final MobileBrand brand;

  const MobileBranding({
    required this.source,
    required this.brand,
  });

  bool get isOrganizer => source == 'organizer';

  factory MobileBranding.fromJson(Map<String, dynamic> json) {
    final brandRaw = json['brand'] is Map
        ? Map<String, dynamic>.from(json['brand'] as Map)
        : <String, dynamic>{};
    return MobileBranding(
      source: (json['source'] ?? 'platform').toString(),
      brand: MobileBrand.fromJson(brandRaw),
    );
  }

  Map<String, dynamic> toJson() => {
        'source': source,
        'brand': brand.toJson(),
      };
}

class MobileBrand {
  final bool enabled;
  final String? appName;
  final String? tagline;
  final String? iconUrl;
  final String? splashUrl;
  final String primaryColor;
  final String? iosUrl;
  final String? androidUrl;

  const MobileBrand({
    this.enabled = false,
    this.appName,
    this.tagline,
    this.iconUrl,
    this.splashUrl,
    this.primaryColor = '#6352e7',
    this.iosUrl,
    this.androidUrl,
  });

  factory MobileBrand.fromJson(Map<String, dynamic> json) {
    return MobileBrand(
      enabled: TypeHelper.toBool(json['enabled']),
      appName: _str(json['app_name']),
      tagline: _str(json['tagline']),
      iconUrl: _str(json['icon_url']),
      splashUrl: _str(json['splash_url']),
      primaryColor: _str(json['primary_color']) ?? '#6352e7',
      iosUrl: _str(json['ios_url']),
      androidUrl: _str(json['android_url']),
    );
  }

  Map<String, dynamic> toJson() => {
        'enabled': enabled,
        'app_name': appName,
        'tagline': tagline,
        'icon_url': iconUrl,
        'splash_url': splashUrl,
        'primary_color': primaryColor,
        'ios_url': iosUrl,
        'android_url': androidUrl,
      };

  static String? _str(dynamic v) {
    if (v == null) return null;
    final s = v.toString().trim();
    return s.isEmpty ? null : s;
  }
}
