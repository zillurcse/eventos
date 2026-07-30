import 'package:expouse/features/session/session_controller.dart';
import 'package:get/get.dart';

import '../../models/theme_config_model.dart';
import '../../models/mappers/theme_mapper.dart';
import '../../utils/enum/enums.dart';
import '../../utils/helpers/app_data_provider.dart';
import '../../utils/theme/app_colors.dart';
import '../delegate/delegate_controller.dart';
import '../event_feed/event_feed_controller.dart';
import '../exhibitors/exhibitor_controller.dart';
import '../home/home_controller.dart';
import '../lounge/lounge_controller.dart';
import '../rooms/rooms_controller.dart';
import '../notifications/push_notification_service.dart';
import '../speaker/speaker_controller.dart';
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
        final body = response.data;
        final data = body is Map && body['data'] is Map
            ? Map<String, dynamic>.from(body['data'] as Map)
            : Map<String, dynamic>.from(body as Map);

        final subdomain = data['subdomain']?.toString();
        if (subdomain != null && subdomain.isNotEmpty) {
          AppDataProvider.obj.setSubDomain = subdomain;
        }
        final event = data['event'];
        if (event is Map) {
          final uuid = event['uuid']?.toString();
          if (uuid != null && uuid.isNotEmpty) {
            AppDataProvider.obj.eventUuid = uuid;
          }
        }

        final configResponse = ThemeMapper.fromSite(data);
        final theme = configResponse.theme;

        if (theme != null) {
          if (theme.themeColor != null && theme.themeColor!.isNotEmpty) {
            updateAppColors(theme.themeColor!);
          }

          themeModules.assignAll(theme.themeModules);

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
      // Cold-start notification taps land after the shell is ready.
      PushNotificationService.instance.consumePendingOpen();
    } catch (e) {
      // Fallback so reception still opens if site bootstrap fails.
      final fallback = ThemeMapper.fromSite({});
      activeTabs.assignAll(fallback.theme?.webAppTabs ?? []);
      if (activeTabs.isNotEmpty) {
        headerTitle.value = activeTabs.first.customName;
        _activateTab(0);
      }
      themeStatus.value = ApiState.loaded;
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

    switch (route) {
      case 'event.home':
        Get.put(HomeController()).fetchHomeData();
      case 'event.feed':
        Get.put(EventFeedController()).fetchFeed();
      case 'event.sessions':
        Get.put(SessionController()).fetchSessions();
      case 'event.speakers':
        Get.put(SpeakerController()).fetchSpeakers();
      case 'event.delegates':
        Get.put(DelegateController()).fetchDelegates();
      case 'event.exhibitors':
        Get.put(ExhibitorController()).fetchExhibitors();
      case 'event.lounge':
        Get.put(LoungeController()).fetchTables();
      case 'event.rooms':
        Get.put(RoomsController()).fetchRooms();
      default:
        break;
    }
    _loadedTabs.add(index);
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

