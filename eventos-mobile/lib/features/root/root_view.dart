import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import '../event_feed/event_feed_view.dart';
import '../session/session_view.dart';
import '../speaker/speaker_view.dart';
import '../delegate/delegate_view.dart';
import '../exhibitors/exhibitors_view.dart';
import '../lounge/lounge_view.dart';
import '../rooms/rooms_view.dart';
import '../contests/contests_view.dart';
import '../meetings/meetings_view.dart';
import '../badges/badges_view.dart';
import '../notifications/notifications_view.dart';
import '../profile/profile_view.dart';
import 'root_controller.dart';
import 'widgets/root_header.dart';
import 'widgets/nav_item.dart';
import 'widgets/home_drawer.dart';
import 'widgets/feature_placeholder_view.dart';
import '../home/home_view.dart';
import '../../widgets/dialogs/exit_dialog.dart';
import '../../utils/enum/enums.dart';

class RootView extends StatefulWidget {
  const RootView({super.key});

  @override
  State<RootView> createState() => _RootViewState();
}

class _RootViewState extends State<RootView> {
  /// Only instantiate tab pages after the user visits them so polling,
  /// fetches, and heavy widgets (lounge/rooms/profile) stay idle.
  final Map<int, Widget> _pageCache = {};
  int _tabsSignature = 0;

  PageController? _pageController;
  int _pageCount = 0;
  int _lastIndex = 0;
  Worker? _indexWorker;
  bool _listenerBound = false;

  /// Ignore [onPageChanged] while we drive the page from a nav tap.
  bool _animatingFromNav = false;
  int _navAnimToken = 0;

  Widget _getPageForRoute(String route, String customName) {
    switch (route) {
      case 'event.home':
        return const HomeView();
      case 'event.feed':
        return const EventFeedView();
      case 'event.sessions':
        return const SessionView();
      case 'event.speakers':
        return const SpeakerView();
      case 'event.delegates':
        return const DelegateView();
      case 'event.exhibitors':
        return const ExhibitorsView();
      case 'event.lounge':
        return const LoungeView();
      case 'event.rooms':
        return const RoomsView();
      case 'event.contests':
        return const ContestsView();
      case 'event.meetings':
        return const MeetingsView();
      case 'event.badges':
        return const BadgesView();
      case 'event.notifications':
        return const NotificationsView();
      case 'event.profile':
        return const ProfileView();
      default:
        return FeaturePlaceholderView(title: customName);
    }
  }

  void _ensurePage(RootController controller, int index) {
    if (_pageCache.containsKey(index)) return;
    if (index < 0 || index >= controller.activeTabs.length) return;
    final tab = controller.activeTabs[index];
    _pageCache[index] = _getPageForRoute(tab.route, tab.customName);
  }

  void _syncCacheWithTabs(RootController controller) {
    final signature = Object.hashAll(
      controller.activeTabs.map((t) => '${t.route}:${t.customName}'),
    );
    if (signature != _tabsSignature) {
      _tabsSignature = signature;
      _pageCache.clear();
    }
  }

  void _syncPageController(int count) {
    if (_pageController != null && _pageCount == count) return;

    final initial = _lastIndex.clamp(0, count > 0 ? count - 1 : 0);
    _pageController?.dispose();
    _pageController = PageController(initialPage: initial);
    _pageCount = count;
  }

  void _bindIndexListener(RootController controller) {
    if (_listenerBound) return;
    _listenerBound = true;
    _lastIndex = controller.selectedIndex.value;

    _indexWorker = ever<int>(controller.selectedIndex, (index) {
      _lastIndex = index;

      final pc = _pageController;
      if (pc == null || !pc.hasClients) return;

      final current = pc.page?.round() ?? pc.initialPage;
      if (current == index) return;

      _animatingFromNav = true;
      final token = ++_navAnimToken;
      pc
          .animateToPage(
            index,
            duration: const Duration(milliseconds: 320),
            curve: Curves.easeOutCubic,
          )
          .whenComplete(() {
            if (token == _navAnimToken) _animatingFromNav = false;
          });
    });
  }

  @override
  void dispose() {
    _indexWorker?.dispose();
    _pageController?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<RootController>();
    _bindIndexListener(controller);

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, result) async {
        if (didPop) return;
        final navigator = Navigator.of(context);
        if (navigator.canPop()) {
          navigator.pop();
        } else {
          if (controller.selectedIndex.value != 0) {
            controller.changeIndex(0);
          } else {
            final shouldExit = await showExitDialog(context);
            if (shouldExit == true) {
              SystemNavigator.pop();
            }
          }
        }
      },
      child: Scaffold(
        drawer: const HomeDrawer(),
        body: Column(
          children: [
            const RootHeader(),
            Expanded(
              child: Obx(() {
                if (controller.themeStatus.value == ApiState.loading) {
                  return const Center(child: CircularProgressIndicator());
                }

                if (controller.activeTabs.isEmpty) {
                  return const Center(child: Text('No active modules found.'));
                }

                _syncCacheWithTabs(controller);
                final tabCount = controller.activeTabs.length;
                _syncPageController(tabCount);

                return PageView.builder(
                  controller: _pageController,
                  itemCount: tabCount,
                  allowImplicitScrolling: true,
                  physics: const BouncingScrollPhysics(
                    parent: PageScrollPhysics(),
                  ),
                  onPageChanged: (i) {
                    _ensurePage(controller, i);
                    if (i > 0) _ensurePage(controller, i - 1);
                    if (i < tabCount - 1) _ensurePage(controller, i + 1);

                    if (_animatingFromNav) return;
                    if (controller.selectedIndex.value != i) {
                      controller.changeIndex(i);
                    }
                  },
                  itemBuilder: (context, i) {
                    _ensurePage(controller, i);
                    return _pageCache[i] ?? const SizedBox.shrink();
                  },
                );
              }),
            ),
          ],
        ),
        bottomNavigationBar: Obx(() {
          if (controller.themeStatus.value == ApiState.loading ||
              controller.activeTabs.isEmpty) {
            return const SizedBox.shrink();
          }

          final tabs = controller.activeTabs;
          final showMore = tabs.length > 4;
          final bottomNavTabs = showMore ? tabs.sublist(0, 4) : tabs;
          
          final navItems = <({String title, String icon, int actualIndex, bool isMore})>[];
          
          for (int i = 0; i < bottomNavTabs.length; i++) {
            navItems.add((
              title: bottomNavTabs[i].customName,
              icon: 'assets/svg/icons/${bottomNavTabs[i].icon}',
              actualIndex: i,
              isMore: false,
            ));
          }

          if (showMore) {
            navItems.add((title: 'More', icon: 'assets/svg/icons/more.svg', actualIndex: -1, isMore: true));
          }

          return GestureDetector(
            onHorizontalDragEnd: (details) {
              final velocity = details.primaryVelocity ?? 0;
              if (velocity.abs() < 100) return;

              final currentIndex = controller.selectedIndex.value;
              int newIndex = currentIndex;
              if (velocity < 0) {
                newIndex++; // swipe left -> next tab
              } else if (velocity > 0) {
                newIndex--; // swipe right -> previous tab
              }

              if (newIndex >= 0 && newIndex < controller.activeTabs.length) {
                controller.changeIndex(newIndex);
              }
            },
            child: Container(
              height: 60.h,
              decoration: BoxDecoration(
                color: Colors.white,
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.05),
                    blurRadius: 10,
                    offset: const Offset(0, -5),
                  ),
                ],
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceAround,
                children: List.generate(
                  navItems.length,
                  (index) {
                    final item = navItems[index];
                    return NavItem(
                      index: item.actualIndex,
                      title: item.title,
                      icon: item.icon,
                      controller: controller,
                      isMoreTab: item.isMore,
                    );
                  },
                ),
              ),
            ),
          );
        }),
      ),
    );
  }
}
