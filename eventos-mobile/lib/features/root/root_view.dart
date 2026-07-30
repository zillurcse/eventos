import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import '../event_feed/event_feed_view.dart';
import '../session/session_view.dart';
import '../speaker/speaker_view.dart';
import '../delegate/delegate_view.dart';
import '../exhibitors/exhibitors_view.dart';
import 'root_controller.dart';
import 'widgets/root_header.dart';
import 'widgets/nav_item.dart';
import 'widgets/home_drawer.dart';
import 'widgets/feature_placeholder_view.dart';
import '../home/home_view.dart';
import '../../widgets/dialogs/exit_dialog.dart';
import '../../models/theme_config_model.dart';
import '../../utils/enum/enums.dart';

class RootView extends StatelessWidget {
  const RootView({super.key});

  Widget _getPageForRoute(String route, String customName) {
    switch (route) {
      case 'event.home': return const HomeView();
      case 'event.feed': return const EventFeedView();
      case 'event.sessions': return const SessionView();
      case 'event.speakers': return const SpeakerView();
      case 'event.delegates': return const DelegateView();
      case 'event.exhibitors': return const ExhibitorsView();
      default: return FeaturePlaceholderView(title: customName);
    }
  }

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<RootController>();

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, result) async {
        if (didPop) return;
        final navigator = Navigator.of(context);
        if (navigator.canPop()) {
          navigator.pop();
        } else {
          if (controller.selectedIndex.value != 0) {
            controller.changeIndex(0); // Better to return home
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

                final pages = controller.activeTabs
                    .map((tab) => _getPageForRoute(tab.route, tab.customName))
                    .toList();
                
                return IndexedStack(
                  index: controller.selectedIndex.value,
                  children: pages,
                );
              }),
            ),
          ],
        ),
        bottomNavigationBar: Obx(() {
          if (controller.themeStatus.value == ApiState.loading || controller.activeTabs.isEmpty) {
            return const SizedBox.shrink();
          }

          final tabs = controller.activeTabs;
          final showMore = tabs.length > 4;
          final bottomNavTabs = showMore ? tabs.sublist(0, 4) : tabs;
          
          final navItems = bottomNavTabs.map((tab) => (
            title: tab.customName,
            icon: 'assets/svg/icons/${tab.icon}', 
            // the icon field is like 'reception.svg', we can prepend 'assets/svg/icons/'
          )).toList();

          if (showMore) {
            navItems.add((title: 'More', icon: 'assets/svg/icons/more.svg'));
          }

          return Container(
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
                (index) => NavItem(
                  index: index,
                  title: navItems[index].title,
                  icon: navItems[index].icon,
                  controller: controller,
                  isMoreTab: showMore && index == 4,
                ),
              ),
            ),
          );
        }),
      ),
    );
  }
}
