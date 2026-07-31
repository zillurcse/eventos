import 'package:expouse/features/session/session_controller.dart';
import 'package:get/get.dart';
import 'package:get_storage/get_storage.dart';

import '../../models/theme_config_model.dart';
import '../../models/mappers/theme_mapper.dart';
import '../../models/user.dart';
import '../../utils/enum/enums.dart';
import '../../utils/helpers/app_data_provider.dart';
import '../../utils/helpers/local_key.dart';
import '../../utils/helpers/secure_auth_storage.dart';
import '../../utils/theme/app_colors.dart';
import '../badges/badges_controller.dart';
import '../contests/contests_controller.dart';
import '../delegate/delegate_controller.dart';
import '../event_feed/event_feed_controller.dart';
import '../exhibitors/exhibitor_controller.dart';
import '../home/home_controller.dart';
import '../lounge/lounge_controller.dart';
import '../meetings/meetings_controller.dart';
import '../profile/profile_service.dart';
import '../rooms/rooms_controller.dart';
import '../notifications/push_notification_service.dart';
import '../speaker/speaker_controller.dart';
import 'theme_service.dart';

class RootController extends GetxController {
  var selectedIndex = 0.obs;
  var headerTitle = ''.obs;

  /// Reactive identity for drawer / header / greeting (hydrated from event profile).
  final profilePhotoUrl = ''.obs;
  final userName = ''.obs;
  final userEmail = ''.obs;

  final ThemeService _themeService = ThemeService();
  final RxList<String> themeModules = <String>[].obs;
  final RxList<WebAppTab> activeTabs = <WebAppTab>[].obs;
  final Rx<ApiState> themeStatus = ApiState.initial.obs;

  /// Tracks which tab indexes have already had their data loaded at least once.
  final Set<int> _loadedTabs = {};

  @override
  void onInit() {
    super.onInit();
    _seedUserFromStorage();
    // Only bootstrap event theme/reception when already signed in.
    // Otherwise we'd hit /public/reception with the default subdomain and 404
    // before the user logs in and picks their event.
    if (SecureAuthStorage.instance.hasToken) {
      fetchThemeConfig();
    } else {
      themeStatus.value = ApiState.loaded;
    }
  }

  void _seedUserFromStorage() {
    final raw = GetStorage().read(LocalKeyHelper.userInfo);
    if (raw is! Map) return;
    final user = User.fromJson(Map<String, dynamic>.from(raw));
    if (user.profilePhotoUrl.isNotEmpty) {
      profilePhotoUrl.value = user.profilePhotoUrl;
    }
    final name = _resolveDisplayName(
      name: user.name,
      firstName: user.firstName,
      lastName: user.lastName,
    );
    if (name.isNotEmpty) userName.value = name;
    if (user.email.isNotEmpty) userEmail.value = user.email;
  }

  /// Re-load site config + home for the currently selected event subdomain.
  Future<void> reloadForCurrentEvent() async {
    _loadedTabs.clear();
    selectedIndex.value = 0;
    _deleteEventScopedControllers();
    await fetchThemeConfig();
  }

  void _deleteEventScopedControllers() {
    void drop<T>() {
      if (Get.isRegistered<T>()) {
        Get.delete<T>(force: true);
      }
    }

    drop<HomeController>();
    drop<EventFeedController>();
    drop<SessionController>();
    drop<SpeakerController>();
    drop<DelegateController>();
    drop<ExhibitorController>();
    drop<LoungeController>();
    drop<RoomsController>();
    drop<ContestsController>();
    drop<MeetingsController>();
    drop<BadgesController>();
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

        // Reuse site payload for home welcome video (avoids duplicate GET).
        final navigation = data['navigation'];
        if (navigation is Map && navigation['welcome_video'] is Map) {
          AppDataProvider.obj.cachedWelcomeVideo = Map<String, dynamic>.from(
            navigation['welcome_video'] as Map,
          );
        } else {
          AppDataProvider.obj.cachedWelcomeVideo = null;
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
      // Cold-start notification taps land after the shell is ready.
      PushNotificationService.instance.consumePendingOpen();
      // Event UUID is set — pull name / email / avatar for drawer / header.
      _hydrateEventProfile();
    } catch (e) {
      // Fallback so reception still opens if site bootstrap fails.
      final fallback = ThemeMapper.fromSite({});
      activeTabs.assignAll(fallback.theme?.webAppTabs ?? []);
      if (activeTabs.isNotEmpty) {
        headerTitle.value = activeTabs.first.customName;
        _activateTab(0);
      }
      themeStatus.value = ApiState.loaded;
    }
  }

  /// Loads GET /events/{event}/profile and syncs identity into local user + UI.
  Future<void> _hydrateEventProfile() async {
    final uuid = AppDataProvider.obj.eventUuid;
    if (uuid == null || uuid.isEmpty) return;
    try {
      final response = await ProfileService().getProfile();
      final body = response.data;
      if (body is! Map) return;
      final raw = body['data'] is Map
          ? Map<String, dynamic>.from(body['data'] as Map)
          : Map<String, dynamic>.from(body);
      await applyEventProfile(
        name: raw['name']?.toString(),
        firstName: raw['first_name']?.toString(),
        lastName: raw['last_name']?.toString(),
        email: raw['email']?.toString(),
        avatarUrl:
            (raw['avatar_url'] ?? raw['profile_photo_url'])?.toString(),
      );
    } catch (_) {
      // Non-fatal — shell still works with placeholder.
    }
  }

  Future<void> applyProfilePhoto(String avatarUrl) async {
    await applyEventProfile(avatarUrl: avatarUrl);
  }

  /// Syncs event-participant name / email / avatar into reactive UI + storage.
  Future<void> applyEventProfile({
    String? name,
    String? firstName,
    String? lastName,
    String? email,
    String? avatarUrl,
  }) async {
    final displayName = _resolveDisplayName(
      name: name,
      firstName: firstName,
      lastName: lastName,
    );

    if (displayName.isNotEmpty) userName.value = displayName;
    if (email != null && email.isNotEmpty) userEmail.value = email;
    if (avatarUrl != null && avatarUrl.isNotEmpty) {
      // Keep the API URL as-is; CustomImage rewrites localhost → LAN for devices.
      profilePhotoUrl.value = avatarUrl;
    }

    if (displayName.isEmpty &&
        (email == null || email.isEmpty) &&
        (avatarUrl == null || avatarUrl.isEmpty)) {
      return;
    }

    final storage = GetStorage();
    final raw = storage.read(LocalKeyHelper.userInfo);
    final map = raw is Map
        ? Map<String, dynamic>.from(raw)
        : <String, dynamic>{};
    if (displayName.isNotEmpty) {
      map['name'] = displayName;
      if (firstName != null && firstName.isNotEmpty) {
        map['first_name'] = firstName;
      }
      if (lastName != null && lastName.isNotEmpty) {
        map['last_name'] = lastName;
      }
    }
    if (email != null && email.isNotEmpty) map['email'] = email;
    if (avatarUrl != null && avatarUrl.isNotEmpty) {
      map['avatar_url'] = avatarUrl;
      map['profile_photo_url'] = avatarUrl;
    }
    await storage.write(LocalKeyHelper.userInfo, map);
  }

  static String _resolveDisplayName({
    String? name,
    String? firstName,
    String? lastName,
  }) {
    final full = name?.trim() ?? '';
    if (full.isNotEmpty) return full;
    final composed = '${firstName?.trim() ?? ''} ${lastName?.trim() ?? ''}'
        .trim();
    return composed;
  }

  void changeIndex(int index) {
    if (index >= activeTabs.length) return;

    _pauseBackgroundPolling();
    selectedIndex.value = index;
    headerTitle.value = activeTabs[index].customName;
    _activateTab(index);
    _resumePollingForTab(index);
  }

  void _pauseBackgroundPolling() {
    if (Get.isRegistered<LoungeController>()) {
      Get.find<LoungeController>().stopPolling();
    }
    if (Get.isRegistered<RoomsController>()) {
      Get.find<RoomsController>().stopPolling();
    }
  }

  void _resumePollingForTab(int index) {
    if (index >= activeTabs.length) return;
    final route = activeTabs[index].route;
    if (route == 'event.lounge' && Get.isRegistered<LoungeController>()) {
      Get.find<LoungeController>().startPolling();
    } else if (route == 'event.rooms' && Get.isRegistered<RoomsController>()) {
      Get.find<RoomsController>().startPolling();
    }
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
        break;
      case 'event.feed':
        Get.put(EventFeedController()).fetchFeed();
        break;
      case 'event.sessions':
        Get.put(SessionController()).fetchSessions();
        break;
      case 'event.speakers':
        Get.put(SpeakerController()).fetchSpeakers();
        break;
      case 'event.delegates':
        Get.put(DelegateController()).fetchDelegates();
        break;
      case 'event.exhibitors':
        Get.put(ExhibitorController()).fetchExhibitors();
        break;
      case 'event.lounge':
        Get.put(LoungeController()).fetchTables();
        break;
      case 'event.rooms':
        Get.put(RoomsController()).fetchRooms();
        break;
      case 'event.contests':
        Get.put(ContestsController()).fetchContests();
        break;
      case 'event.meetings':
        Get.put(MeetingsController()).fetchMeetings();
        break;
      case 'event.badges':
        Get.put(BadgesController()).fetchBadges();
        break;
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
      'event.home' => Get.isRegistered<HomeController>()
          ? Get.find<HomeController>().dataStatus.value == ApiState.loading
          : false,
      'event.feed' => Get.isRegistered<EventFeedController>()
          ? Get.find<EventFeedController>().feedStatus.value == ApiState.loading
          : false,
      'event.sessions' => Get.isRegistered<SessionController>()
          ? Get.find<SessionController>().dataStatus.value == ApiState.loading
          : false,
      'event.speakers' => Get.isRegistered<SpeakerController>()
          ? Get.find<SpeakerController>().dataStatus.value == ApiState.loading
          : false,
      _ => false,
    };
  }
}
