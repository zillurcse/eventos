import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../utils/bindings/auth_binding.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../utils/helpers/session_manager.dart';
import '../../../widgets/custom_image.dart';
import '../../../features/auth/pages/select_event_view.dart';
import '../../profile/profile_view.dart';
import '../root_controller.dart';

class HomeDrawer extends StatelessWidget {
  const HomeDrawer({super.key});

  @override
  Widget build(BuildContext context) {
    final rootCtrl = Get.find<RootController>();

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
              Get.to(() => ProfileView());
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
                  Obx(
                    () => Row(
                      children: [
                        CustomImage(
                          rootCtrl.profilePhotoUrl.value,
                          radius: 12.r,
                          height: 48.sp,
                          width: 48.sp,
                          avatar: true,
                        ),
                        SizedBox(width: 12.w),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                rootCtrl.userName.value,
                                style: context.titleRegular?.copyWith(
                                  color: context.heading,
                                  fontWeight: FontWeight.bold,
                                ),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ),
                              SizedBox(height: 2.h),
                              Text(
                                rootCtrl.userEmail.value,
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
                  ),
                ],
              ),
            ),
          ),

          // ── Navigation List (from Manage Tabs) ──
          Expanded(
            child: Obx(() {
              final tabs = rootCtrl.activeTabs;
              return ListView(
                padding: EdgeInsets.symmetric(horizontal: 12.w, vertical: 16.h),
                children: [
                  ...tabs.asMap().entries.map((entry) {
                    final index = entry.key;
                    final tab = entry.value;
                    return _buildDrawerItem(
                      context: context,
                      iconPath: 'assets/svg/icons/${tab.icon}',
                      title: tab.customName,
                      onTap: () {
                        Scaffold.of(context).closeDrawer();
                        rootCtrl.changeIndex(index);
                      },
                    );
                  }),
                  _buildDrawerItem(
                    context: context,
                    iconPath: 'assets/svg/icons/calender.svg',
                    title: 'Switch event',
                    onTap: () {
                      Scaffold.of(context).closeDrawer();
                      Get.to(
                        () => const SelectEventView(isSwitching: true),
                        binding: AuthBinding(),
                      );
                    },
                  ),
                ],
              );
            }),
          ),

          // ── Footer: Log out Button ──
          SafeArea(
            top: false,
            child: Container(
              padding: EdgeInsets.symmetric(horizontal: 20.w, vertical: 16.h),
              child: InkWell(
                onTap: () async {
                  await SessionManager.logout();
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
