import 'package:expouse/utils/extension/theme_ext.dart';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../utils/theme/app_colors.dart';
import '../../../widgets/custom_image.dart';
import '../root_controller.dart';
import 'nav_skeleton_dot.dart';
import '../../../utils/bottom_sheets/more_bottom_sheet.dart';
import '../../../utils/helpers/bottom_sheets.dart';

/// A single bottom-nav item that rebuilds independently via its own Obx.
/// Shows a shimmer loading dot under the icon while the tab's data is loading.
class NavItem extends StatelessWidget {
  final int index;
  final String title;
  final String icon;
  final RootController controller;
  final bool isMoreTab;

  const NavItem({
    super.key,
    required this.index,
    required this.title,
    required this.icon,
    required this.controller,
    this.isMoreTab = false,
  });

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      final isSelected = isMoreTab 
          ? controller.selectedIndex.value >= 4 
          : controller.selectedIndex.value == index;
          
      final isLoading = isMoreTab 
          ? false 
          : controller.isTabLoading(index);

      return GestureDetector(
        onTap: () {
          if (isMoreTab) {
            showMoreBottomSheet(child: const MoreBottomSheet());
          } else {
            controller.changeIndex(index);
          }
        },
        behavior: HitTestBehavior.opaque,
        child: AnimatedScale(
          scale: isSelected ? 1.08 : 1.0,
          duration: const Duration(milliseconds: 240),
          curve: Curves.easeOutCubic,
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              CustomImage(
                icon,
                height: 18.h,
                width: 18.w,
                color: isSelected ? primaryTheme : ghost,
              ),
              if (title.isNotEmpty) ...[
                SizedBox(height: 3.h),
                // Loading skeleton bar replaces the label while fetching
                if (isLoading)
                  const NavSkeletonDot()
                else
                  AnimatedDefaultTextStyle(
                    duration: const Duration(milliseconds: 240),
                    curve: Curves.easeOutCubic,
                    style: (context.specialLabelCapital ?? const TextStyle()).copyWith(
                      color: isSelected ? primaryTheme : ghost,
                      fontSize: 12.sp,
                      fontWeight:
                          isSelected ? FontWeight.w600 : FontWeight.w400,
                    ),
                    child: Text(
                      title,
                      textAlign: TextAlign.center,
                    ),
                  ),
              ],
            ],
          ),
        ),
      );
    });
  }
}
