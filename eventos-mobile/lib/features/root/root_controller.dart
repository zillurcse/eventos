import 'package:expouse/features/session/session_controller.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../models/theme_config_model.dart';
import '../../utils/enum/enums.dart';
import '../../utils/theme/app_colors.dart';
import '../event_feed/event_feed_controller.dart';
import '../home/home_controller.dart';
import '../speaker/speaker_controller.dart';
import '../../utils/bottom_sheets/more_bottom_sheet.dart';
import '../../utils/helpers/bottom_sheets.dart';
import 'theme_service.dart';

class RootController extends GetxController {
  var selectedIndex = 0.obs;
  var headerTitle = ''.obs;

  final ThemeService _themeService = ThemeService();
  final RxList<String> themeModules = <String>[].obs;
  final RxList<WebAppTab> activeTabs = <WebAppTab>[].obs;
  final Rx<ApiState> themeStatus = ApiState.initial.obs;

  /// Tracks which tab indexes have already had their data loaded at least once.
  final Set<int> _loadedTabs = {};

  @override
  void onInit() {
    super.onInit();
    fetchThemeConfig();
  }

  Future<void> fetchThemeConfig() async {
    themeStatus.value = ApiState.loading;
    try {
      final response = await _themeService.getThemeConfiguration();
      if (response.statusCode == 200 && response.data != null) {
        final configResponse = ThemeConfigResponse.fromJson(Map<String, dynamic>.from(response.data));
        final theme = configResponse.theme;
        
        if (theme != null) {
          // Update global app colors
          if (theme.themeColor != null && theme.themeColor!.isNotEmpty) {
            updateAppColors(theme.themeColor!);
          }
          
          // Update modules (e.g. logo, chat, briefcase)
          themeModules.assignAll(theme.themeModules);
          
          // Sort active tabs by order
          final tabs = theme.webAppTabs.where((t) => t.status).toList();
          tabs.sort((a, b) => a.order.compareTo(b.order));
          activeTabs.assignAll(tabs);

          if (activeTabs.isNotEmpty) {
            headerTitle.value = activeTabs.first.customName;
            _activateTab(0);
          }
        }
      }
      themeStatus.value = ApiState.loaded;
      update();
    } catch (e) {
      themeStatus.value = ApiState.error;
      update();
    }
  }

  void changeIndex(int index) {
    if (index >= activeTabs.length) return;
    
    selectedIndex.value = index;
    headerTitle.value = activeTabs[index].customName;
    _activateTab(index);
  }

  void updateHeaderTitle(String title) {
    headerTitle.value = title;
  }

  /// Triggers fetch logic for mapped routes if needed
  void _activateTab(int index) {
    if (_loadedTabs.contains(index)) return;
    if (index >= activeTabs.length) return;
    
    final route = activeTabs[index].route;
    
    // Check which controllers to trigger based on the route
    if (route == 'event.home') {
      Get.put(HomeController()).fetchHomeData();
      _loadedTabs.add(index);
    } else if (route == 'event.feed') {
      Get.put(EventFeedController()).fetchFeed();
      _loadedTabs.add(index);
    } else if (route == 'event.sessions') {
      Get.put(SessionController()).fetchSessions();
      _loadedTabs.add(index);
    } else if (route == 'event.speakers') {
      Get.put(SpeakerController()).fetchSpeakers();
      _loadedTabs.add(index);
    } else {
      // Other tabs don't require pre-fetching via controller yet
      _loadedTabs.add(index);
    }
  }

  /// Returns the loading state for a given tab index (for skeleton nav).
  bool isTabLoading(int index) {
    if (_loadedTabs.contains(index) == false) return false;
    if (index >= activeTabs.length) return false;
    
    final route = activeTabs[index].route;
    return switch (route) {
      'event.home' => Get.isRegistered<HomeController>() ? Get.find<HomeController>().dataStatus.value == ApiState.loading : false,
      'event.feed' => Get.isRegistered<EventFeedController>() ? Get.find<EventFeedController>().feedStatus.value == ApiState.loading : false,
      'event.sessions' => Get.isRegistered<SessionController>() ? Get.find<SessionController>().dataStatus.value == ApiState.loading : false,
      'event.speakers' => Get.isRegistered<SpeakerController>() ? Get.find<SpeakerController>().dataStatus.value == ApiState.loading : false,
      _ => false,
    };
  }
}

