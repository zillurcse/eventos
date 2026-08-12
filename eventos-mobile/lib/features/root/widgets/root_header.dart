import 'package:expouse/utils/extension/theme_ext.dart';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import 'package:get_storage/get_storage.dart';
import '../../../models/user.dart';
import '../../../utils/helpers/local_key.dart';
import '../../../widgets/custom_image.dart';
import '../../chat/chat_controller.dart';
import '../../chat/chat_view.dart';
import '../../notifications/notifications_view.dart';
import '../../profile/profile_view.dart';
import '../root_controller.dart';
import 'header_icon.dart';
import '../../leaderboard/leaderboard_view.dart';
import '../../bookmarks/bookmarks_view.dart';
import '../../briefcase/briefcase_view.dart';

class RootHeader extends StatelessWidget {
  const RootHeader({super.key});

  @override
  Widget build(BuildContext context) {
    final rootCtrl = Get.find<RootController>();
    // Do NOT Get.find<ChatController>() here - that eagerly starts Pusher +
    // room polling for every shell paint. Resolve only when chat is enabled.

    final rawUser = GetStorage().read(LocalKeyHelper.userInfo);
    final user = rawUser is Map
        ? User.fromJson(Map<String, dynamic>.from(rawUser))
        : null;

    return Container(
      width: double.infinity,
      color: context.primaryTheme,
      padding: EdgeInsets.only(
        top: MediaQuery.of(context).viewPadding.top + 12.h,
        bottom: 16.h,
      ),
      child: Padding(
        padding: EdgeInsets.symmetric(horizontal: 16.w),
        child: Column(
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                GestureDetector(
                  onTap: () => Scaffold.of(context).openDrawer(),
                  child: CustomImage(
                    'assets/svg/icons/menu.svg',
                    height: 14.sp,
                  ),
                ),
                Expanded(
                  child: Obx(() => _buildEventBrand(context, rootCtrl)),
                ),
                GestureDetector(
                  onTap: () {
                    Get.to(()=> ProfileView());
                  },
                  child: Obx(
                    () => CustomImage(
                      rootCtrl.profilePhotoUrl.value.isNotEmpty
                          ? rootCtrl.profilePhotoUrl.value
                          : (user?.profilePhotoUrl ?? ''),
                      radius: 8.r,
                      height: 28.sp,
                      width: 28.sp,
                      avatar: true,
                    ),
                  ),
                ),
              ],
            ),
            Row(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Expanded(
                  child: Obx(
                    () => AnimatedSwitcher(
                      duration: const Duration(milliseconds: 280),
                      switchInCurve: Curves.easeOutCubic,
                      switchOutCurve: Curves.easeInCubic,
                      transitionBuilder: (child, animation) {
                        final offset = Tween<Offset>(
                          begin: const Offset(0, 0.25),
                          end: Offset.zero,
                        ).animate(animation);
                        return FadeTransition(
                          opacity: animation,
                          child: SlideTransition(
                            position: offset,
                            child: child,
                          ),
                        );
                      },
                      layoutBuilder: (currentChild, previousChildren) {
                        return Stack(
                          alignment: Alignment.centerLeft,
                          children: <Widget>[
                            ...previousChildren,
                            if (currentChild != null) currentChild,
                          ],
                        );
                      },
                      child: Text(
                        rootCtrl.headerTitle.value,
                        key: ValueKey(rootCtrl.headerTitle.value),
                        textAlign: TextAlign.left,
                        style: context.titleLarge?.copyWith(
                          color: context.tertiaryText,
                        ),
                      ),
                    ),
                  ),
                ),
                Obx(() => rootCtrl.themeModules.contains('bookmark')
                    ? Padding(
                        padding: EdgeInsets.only(right: 6.sp),
                        child: GestureDetector(
                          onTap: () => Get.to(() => const BookmarksView()),
                          child: HeaderIcon(
                            'assets/svg/icons/bookmark.svg',
                            height: 17.sp,
                          ),
                        ),
                      )
                    : const SizedBox.shrink()),
                Obx(() => rootCtrl.themeModules.contains('briefcase')
                    ? GestureDetector(
                        onTap: () => Get.to(() => const BriefcaseView()),
                        child: HeaderIcon('assets/svg/icons/bag.svg', height: 18.sp),
                      )
                    : const SizedBox.shrink()),
                Obx(() => rootCtrl.themeModules.contains('leaderboard')
                    ? GestureDetector(
                        onTap: () => Get.to(() => const LeaderboardView()),
                        child: HeaderIcon(
                          'assets/svg/icons/trophy.svg',
                          height: 19.sp,
                        ),
                      )
                    : const SizedBox.shrink()),
                Obx(() => (rootCtrl.themeModules.contains('notifications') ||
                        rootCtrl.themeModules.contains('notification'))
                    ? GestureDetector(
                        onTap: () => Get.to(() => const NotificationsView()),
                        child: HeaderIcon('assets/svg/icons/bell.svg', height: 19.sp),
                      )
                    : const SizedBox.shrink()),
                Obx(() {
                  if (!rootCtrl.themeModules.contains('chat')) {
                    return const SizedBox.shrink();
                  }
                  // Lazy: first access creates ChatController after chat is enabled.
                  final chatCtrl = Get.find<ChatController>();
                  return GestureDetector(
                    onTap: () => Get.to(() => const ChatView()),
                    child: Container(
                      color: Colors.transparent,
                      padding: EdgeInsets.only(left: 20.w, top: 20.sp),
                      child: Obx(() {
                        final unread = chatCtrl.totalUnread;
                        return Stack(
                          clipBehavior: Clip.none,
                          children: [
                            CustomImage(
                              'assets/svg/icons/chat.svg',
                              height: 17.sp,
                            ),
                            if (unread > 0)
                              Positioned(
                                top: -6.sp,
                                right: -8.sp,
                                child: Container(
                                  padding: EdgeInsets.symmetric(
                                    horizontal: 4.w,
                                    vertical: 1.h,
                                  ),
                                  decoration: BoxDecoration(
                                    color: const Color(0xffE53935),
                                    borderRadius: BorderRadius.circular(10.r),
                                  ),
                                  constraints: BoxConstraints(
                                    minWidth: 16.sp,
                                    minHeight: 16.sp,
                                  ),
                                  child: Center(
                                    child: Text(
                                      unread > 99 ? '99+' : '$unread',
                                      style: TextStyle(
                                        color: Colors.white,
                                        fontSize: 9.sp,
                                        fontWeight: FontWeight.w700,
                                        height: 1,
                                      ),
                                    ),
                                  ),
                                ),
                              ),
                          ],
                        );
                      }),
                    ),
                  );
                }),
              ],
            ),
          ],
        ),
      ),
    );
  }

  /// Center brand: event logo + name (parity with web EventHeader modules).
  Widget _buildEventBrand(BuildContext context, RootController rootCtrl) {
    final modules = rootCtrl.themeModules;
    final showLogo =
        modules.contains('event_logo') || modules.contains('logo');
    final titleModuleOn =
        modules.contains('event_title') || modules.contains('title');
    if (!showLogo && !titleModuleOn) return const SizedBox.shrink();

    final logoUrl = rootCtrl.eventLogoUrl.value.trim();
    final name = rootCtrl.eventName.value.trim();
    // Show the name whenever the title module is on, or alongside the logo
    // so the Expouse wordmark is replaced by the event name.
    final showTitle = titleModuleOn || (showLogo && name.isNotEmpty);
    final displayName =
        name.length > 19 ? '${name.substring(0, 19)}…' : name;
    final initialsSource = name.isNotEmpty ? name : 'EV';
    final initials = initialsSource
        .substring(0, initialsSource.length.clamp(0, 3))
        .toUpperCase();

    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      mainAxisSize: MainAxisSize.max,
      children: [
        if (showLogo) ...[
          if (logoUrl.isNotEmpty)
            ConstrainedBox(
              constraints: BoxConstraints(maxHeight: 26.h, maxWidth: 110.w),
              child: CustomImage(
                logoUrl,
                height: 26.h,
                fit: BoxFit.contain,
                radius: 6.r,
              ),
            )
          else if (!showTitle || name.isEmpty)
            CustomImage('assets/svg/img/logo.svg', height: 26.h)
          else
            Container(
              height: 26.h,
              width: 26.h,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.18),
                borderRadius: BorderRadius.circular(6.r),
              ),
              child: Text(
                initials,
                style: TextStyle(
                  color: context.tertiaryText,
                  fontSize: 9.sp,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          if (showTitle && name.isNotEmpty) SizedBox(width: 8.w),
        ],
        if (showTitle && name.isNotEmpty)
          Flexible(
            child: Text(
              displayName,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              textAlign: TextAlign.center,
              style: context.titleRegular?.copyWith(
                color: context.tertiaryText,
                fontWeight: FontWeight.w700,
                fontSize: 13.sp,
              ),
            ),
          ),
      ],
    );
  }
}
