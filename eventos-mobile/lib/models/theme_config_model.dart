class ThemeConfigResponse {
  final ThemeConfig? theme;

  ThemeConfigResponse({this.theme});

  factory ThemeConfigResponse.fromJson(Map<String, dynamic> json) {
    return ThemeConfigResponse(
      theme: json['theme'] != null ? ThemeConfig.fromJson(Map<String, dynamic>.from(json['theme'])) : null,
    );
  }
}

class ThemeConfig {
  final String? themeColor;
  final List<String> themeModules;
  final List<WebAppTab> webAppTabs;
  // Other fields like page_design_type, etc. can be added if needed

  ThemeConfig({
    this.themeColor,
    required this.themeModules,
    required this.webAppTabs,
  });

  factory ThemeConfig.fromJson(Map<String, dynamic> json) {
    return ThemeConfig(
      themeColor: json['theme_color'] as String?,
      themeModules: (json['theme_modules'] as List?)?.map((e) => e.toString()).toList() ?? [],
      webAppTabs: (json['web_app_tabs'] as List?)?.map((e) => WebAppTab.fromJson(Map<String, dynamic>.from(e))).toList() ?? [],
    );
  }
}

class WebAppTab {
  final String name;
  final String customName;
  final bool status;
  final int order;
  final String url;
  final String route;
  final String icon;

  WebAppTab({
    required this.name,
    required this.customName,
    required this.status,
    required this.order,
    required this.url,
    required this.route,
    required this.icon,
  });

  factory WebAppTab.fromJson(Map<String, dynamic> json) {
    return WebAppTab(
      name: json['name']?.toString() ?? '',
      customName: json['custom_name']?.toString() ?? '',
      status: json['status'] == true || json['status'] == 1 || json['status'] == '1',
      order: json['order'] is int ? json['order'] as int : int.tryParse(json['order']?.toString() ?? '99') ?? 99,
      url: json['url']?.toString() ?? '',
      route: json['route']?.toString() ?? '',
      icon: _mapIconName(json['icon']?.toString() ?? ''),
    );
  }

  static String _mapIconName(String apiIcon) {
    switch (apiIcon) {
      case 'reception.svg': return 'home.svg';
      case 'sessions.svg': return 'session.svg';
      case 'speakers.svg': return 'speaker.svg';
      default: return apiIcon;
    }
  }
}
