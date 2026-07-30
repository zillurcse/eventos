import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import 'package:get_storage/get_storage.dart';

import '../../../models/user.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../utils/helpers/local_key.dart';
import '../../../widgets/custom_image.dart';
import '../../../features/auth/auth_view.dart';
import '../../../utils/bindings/auth_binding.dart';
import '../../../features/notifications/push_notification_service.dart';
import '../../exhibitors/exhibitor_controller.dart';
import '../../exhibitors/exhibitors_view.dart';
import '../../delegate/delegate_view.dart';
import '../../profile/profile_view.dart';
import '../root_controller.dart';
import '../../leaderboard/leaderboard_view.dart';

class HomeDrawer extends StatelessWidget {
  const HomeDrawer({super.key});

  @override
  Widget build(BuildContext context) {
    final rootCtrl = Get.find<RootController>();
    final rawUser = GetStorage().read(LocalKeyHelper.userInfo);
    final user = rawUser is Map
        ? User.fromJson(Map<String, dynamic>.from(rawUser))
        : null;

    return Drawer(
      backgroundColor: context.tertiaryText,
      width: context.width * .85,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.zero,
      ),
      child: Column(
        children: [
          // ── Header Section ──
          GestureDetector(
            onTap: () {
              Get.to(()=> ProfileView());
            },
            child: Container(
              color: context.primaryFocused,
              padding: EdgeInsets.only(
                top: MediaQuery.of(context).padding.top + 12.h,
                bottom: 24.h,
                left: 20.w,
                right: 12.w,
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Align(
                    alignment: Alignment.centerRight,
                    child: IconButton(
                      icon: Icon(
                        Icons.close,
                        color: context.caption,
                        size: 24.sp,
                      ),
                      onPressed: () => Scaffold.of(context).closeDrawer(),
                    ),
                  ),
                  SizedBox(height: 8.h),
                  Row(
                    children: [
                      CustomImage(
                        user?.profilePhotoUrl ?? "",
                        radius: 12.r,
                        height: 48.sp,
                        width: 48.sp,
                      ),
                      SizedBox(width: 12.w),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              user?.name ?? "",
                              style: context.titleRegular?.copyWith(
                                color: context.heading,
                                fontWeight: FontWeight.bold,
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                            SizedBox(height: 2.h),
                            Text(
                              user?.email ?? "",
                              style: context.bodyLarge?.copyWith(
                                color: context.caption,
                                fontSize: 12.sp,
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),

          // ── Navigation List ──
          Expanded(
            child: ListView(
              padding: EdgeInsets.symmetric(horizontal: 12.w, vertical: 16.h),
              children: [
                _buildDrawerItem(
                  context: context,
                  iconPath: "assets/svg/icons/home.svg",
                  title: "Home",
                  onTap: () {
                    Scaffold.of(context).closeDrawer();
                    rootCtrl.changeIndex(0);
                  },
                ),
                _buildDrawerItem(
                  context: context,
                  iconPath: "assets/svg/icons/feed.svg",
                  title: "Feed",
                  onTap: () {
                    Scaffold.of(context).closeDrawer();
                    rootCtrl.changeIndex(1);
                  },
                ),
                _buildDrawerItem(
                  context: context,
                  iconPath: "assets/svg/icons/session.svg",
                  title: "Sessions",
                  onTap: () {
                    Scaffold.of(context).closeDrawer();
                    rootCtrl.changeIndex(2);
                  },
                ),
                _buildDrawerItem(
                  context: context,
                  iconPath: "assets/svg/icons/speaker.svg",
                  title: "Speakers",
                  onTap: () {
                    Scaffold.of(context).closeDrawer();
                    rootCtrl.changeIndex(3);
                  },
                ),
                _buildDrawerItem(
                  context: context,
                  iconPath: "assets/svg/icons/exhibitors.svg",
                  title: "Exhibitors",
                  onTap: () {
                    Scaffold.of(context).closeDrawer();
                    final exhibitorCtrl = Get.find<ExhibitorController>();
                    exhibitorCtrl.selectedType.value = null;
                    exhibitorCtrl.fetchExhibitors();
                    Get.to(() => const ExhibitorsView());
                  },
                ),
                _buildDrawerItem(
                  context: context,
                  iconPath: "assets/svg/icons/badges.svg",
                  title: "Sponsors",
                  onTap: () {
                    Scaffold.of(context).closeDrawer();
                    final exhibitorCtrl = Get.find<ExhibitorController>();
                    exhibitorCtrl.selectedType.value = 'sponsor';
                    exhibitorCtrl.fetchExhibitors();
                    Get.to(() => const ExhibitorsView());
                  },
                ),
                _buildDrawerItem(
                  context: context,
                  iconPath: "assets/svg/icons/delegates.svg",
                  title: "Delegates",
                  onTap: () {
                    Scaffold.of(context).closeDrawer();
                    Get.to(() => const DelegateView());
                  },
                ),
                _buildDrawerItem(
                  context: context,
                  iconPath: "assets/svg/icons/trophy.svg",
                  title: "Leaderboard",
                  onTap: () {
                    Scaffold.of(context).closeDrawer();
                    Get.to(() => const LeaderboardView());
                  },
                ),
              ],
            ),
          ),

          // ── Footer: Log out Button ──
          SafeArea(
            top: false,
            child: Container(
              padding: EdgeInsets.symmetric(horizontal: 20.w, vertical: 16.h),
              child: InkWell(
                onTap: () async {
                  await PushNotificationService.instance.unregisterOnLogout();
                  final localDb = GetStorage();
                  await localDb.erase();
                  Get.offAll(
                    () => const AuthView(),
                    binding: AuthBinding(),
                  );
                },
                borderRadius: BorderRadius.circular(8.r),
                child: Container(
                  width: double.infinity,
                  height: 44.h,
                  decoration: BoxDecoration(
                    color: context.redErrorLight,
                    borderRadius: BorderRadius.circular(8.r),
                  ),
                  child: Center(
                    child: Text(
                      "Log out",
                      style: context.buttonMediumBold?.copyWith(
                        color: context.redError,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildDrawerItem({
    required BuildContext context,
    required String iconPath,
    required String title,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8.r),
      child: Padding(
        padding: EdgeInsets.symmetric(horizontal: 8.w, vertical: 12.h),
        child: Row(
          children: [
            CustomImage(
              iconPath,
              height: 20.sp,
              width: 20.sp,
              color: context.body,
            ),
            SizedBox(width: 16.w),
            Text(
              title,
              style: context.titleRegular?.copyWith(
                color: context.body,
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
