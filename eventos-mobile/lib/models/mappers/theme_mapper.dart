import '../../models/theme_config_model.dart';

/// Maps EventOS `/public/site` into the existing [ThemeConfig] shape.
///
/// Bottom nav prefers **Mobile App › Manage Tabs** (`navigation.mobile_tabs`)
/// when the organizer configured them; otherwise falls back to Web App Tabs
/// (`navigation.tabs`), then a built-in default list.
class ThemeMapper {
  ThemeMapper._();

  static const _defaultTabs = <Map<String, dynamic>>[
    {'key': 'home', 'label': 'Home'},
    {'key': 'feed', 'label': 'Feed'},
    {'key': 'agenda', 'label': 'Agenda'},
    {'key': 'speakers', 'label': 'Speakers'},
  ];

  static ThemeConfigResponse fromSite(Map<String, dynamic> data) {
    final branding = data['branding'] is Map
        ? Map<String, dynamic>.from(data['branding'] as Map)
        : <String, dynamic>{};
    final colors = branding['colors'] is Map
        ? Map<String, dynamic>.from(branding['colors'] as Map)
        : <String, dynamic>{};
    final navigation = data['navigation'] is Map
        ? Map<String, dynamic>.from(data['navigation'] as Map)
        : <String, dynamic>{};

    // Prefer resolved mobile brand primary (platform or organizer) over web branding.
    String? mobilePrimary;
    final mobileBranding = data['mobile_branding'];
    if (mobileBranding is Map) {
      final brand = mobileBranding['brand'];
      if (brand is Map && brand['primary_color'] != null) {
        mobilePrimary = brand['primary_color'].toString();
      }
    }

    final themeColor = (mobilePrimary ??
            branding['primary'] ??
            colors['primary_button'] ??
            colors['primary'])
        ?.toString();

    final modulesMap = navigation['modules'] is Map
        ? Map<String, dynamic>.from(navigation['modules'] as Map)
        : <String, dynamic>{};
    // Normalize module keys so the header can check either API or legacy names.
    final themeModules = <String>{};
    for (final e in modulesMap.entries) {
      if (e.value != true) continue;
      themeModules.add(e.key);
      themeModules.addAll(_moduleAliases(e.key));
    }

    // Prefer Mobile App › Manage Tabs when present.
    final mobileTabs = navigation['mobile_tabs'];
    final webTabs = navigation['tabs'];
    final List<Map<String, dynamic>> tabItems;
    if (mobileTabs is List && mobileTabs.isNotEmpty) {
      tabItems = mobileTabs
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList();
    } else if (webTabs is List && webTabs.isNotEmpty) {
      tabItems = webTabs
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList();
    } else {
      tabItems = _defaultTabs;
    }

    final webAppTabs = <WebAppTab>[];
    for (var i = 0; i < tabItems.length; i++) {
      final item = tabItems[i];
      final key = (item['key'] ?? '').toString();
      if (key.isEmpty) continue;
      final mapped = _mapTab(key, (item['label'] ?? key).toString(), i);
      if (mapped != null) webAppTabs.add(mapped);
    }

    if (webAppTabs.isEmpty) {
      for (var i = 0; i < _defaultTabs.length; i++) {
        final item = _defaultTabs[i];
        final mapped = _mapTab(
          item['key'] as String,
          item['label'] as String,
          i,
        );
        if (mapped != null) webAppTabs.add(mapped);
      }
    }

    return ThemeConfigResponse(
      theme: ThemeConfig(
        themeColor: themeColor,
        themeModules: themeModules.toList(),
        webAppTabs: webAppTabs,
      ),
    );
  }

  static Iterable<String> _moduleAliases(String key) {
    return switch (key) {
      'event_logo' => const ['logo'],
      'logo' => const ['event_logo'],
      'notifications' => const ['notification'],
      'notification' => const ['notifications'],
      'event_title' => const ['title'],
      _ => const <String>[],
    };
  }

  static WebAppTab? _mapTab(String key, String label, int order) {
    final route = _routeForKey(key);
    if (route == null) return null;
    return WebAppTab(
      name: key,
      customName: label,
      status: true,
      order: order,
      url: '',
      route: route,
      icon: _iconForKey(key),
    );
  }

  /// Maps admin Manage Tabs / Web App Tabs keys → app route ids.
  static String? _routeForKey(String key) {
    switch (key) {
      case 'reception':
      case 'home':
        return 'event.home';
      case 'feed':
      case 'event_feed':
        return 'event.feed';
      case 'sessions':
      case 'agenda':
      case 'schedule':
        return 'event.sessions';
      case 'speakers':
        return 'event.speakers';
      case 'delegates':
      case 'attendees':
        return 'event.delegates';
      case 'exhibitors':
      case 'sponsors':
        return 'event.exhibitors';
      case 'my_badge':
      case 'my_badges':
      case 'badges':
        return 'event.badges';
      case 'notifications':
      case 'notification':
        return 'event.notifications';
      case 'profile':
        return 'event.profile';
      case 'floor_plan':
      case 'map':
        return 'event.floor_plan';
      case 'lounge':
        return 'event.lounge';
      case 'meetings':
        return 'event.meetings';
      case 'rooms':
        return 'event.rooms';
      case 'contests':
        return 'event.contests';
      case 'expolens':
        return 'event.expolens';
      default:
        // Still show as a placeholder tab in More menu.
        return 'event.$key';
    }
  }

  static String _iconForKey(String key) {
    switch (key) {
      case 'reception':
      case 'home':
        return 'home.svg';
      case 'feed':
      case 'event_feed':
        return 'feed.svg';
      case 'sessions':
      case 'agenda':
      case 'schedule':
        return 'session.svg';
      case 'speakers':
        return 'speaker.svg';
      case 'delegates':
      case 'attendees':
        return 'delegates.svg';
      case 'exhibitors':
      case 'sponsors':
        return 'exhibitors.svg';
      case 'meetings':
        return 'meetings.svg';
      case 'lounge':
        return 'lounge.svg';
      case 'rooms':
        return 'rooms.svg';
      case 'contests':
        return 'contests.svg';
      case 'my_badge':
      case 'my_badges':
      case 'badges':
        return 'badges.svg';
      case 'notifications':
      case 'notification':
        return 'bell.svg';
      case 'profile':
        return 'delegates.svg';
      case 'floor_plan':
      case 'map':
        return 'map.svg';
      case 'expolens':
        return 'expolens.svg';
      default:
        return 'more.svg';
    }
  }
}
