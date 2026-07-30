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
    final chatCtrl = Get.find<ChatController>();

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
                Obx(() => rootCtrl.themeModules.contains('logo')
                    ? CustomImage('assets/svg/img/logo.svg', height: 26.h)
                    : const SizedBox.shrink()),
                GestureDetector(
                  onTap: () {
                    Get.to(()=> ProfileView());
                  },
                  child: CustomImage(
                    user?.profilePhotoUrl ?? "",
                    radius: 8.r,
                    height: 28.sp,
                    width: 28.sp,
                  ),
                ),
              ],
            ),
            Row(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Expanded(
                  child: Obx(
                    () => Text(
                      rootCtrl.headerTitle.value,
                      style: context.titleLarge?.copyWith(
                        color: context.tertiaryText,
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
                Obx(() => rootCtrl.themeModules.contains('notification')
                    ? GestureDetector(
                        onTap: () => Get.to(() => const NotificationsView()),
                        child: HeaderIcon('assets/svg/icons/bell.svg', height: 19.sp),
                      )
                    : const SizedBox.shrink()),
                Obx(() {
                  if (!rootCtrl.themeModules.contains('chat')) {
                    return const SizedBox.shrink();
                  }
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
}
