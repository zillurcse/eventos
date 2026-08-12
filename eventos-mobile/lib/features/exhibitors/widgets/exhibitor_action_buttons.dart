import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../widgets/custom_image.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../bookmarks/bookmark_controller.dart';
import '../exhibitor_controller.dart';

class ExhibitorActionButtons extends StatelessWidget {
  final int exhibitorId;

  const ExhibitorActionButtons({super.key, required this.exhibitorId});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        // Bookmark
        Obx(() {
          final ctrl = Get.find<ExhibitorController>();
          final isBookmarked = () {
            if (Get.isRegistered<BookmarkController>()) {
              final bm = Get.find<BookmarkController>();
              bm.bookmarkedExhibitors.length; // Touch to trigger Obx
              return bm.isOnHashed('exhibitor', exhibitorId);
            }
            return ctrl.exhibitorPage.value.bookmarkedExhibitors.contains(exhibitorId);
          }();
          return GestureDetector(
            onTap: () => ctrl.toggleBookmark(exhibitorId),
            child: Container(
              padding: EdgeInsets.all(10.sp),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(8.r),
                border: Border.all(color: context.primaryTheme),
              ),
              child: Icon(
                isBookmarked ? Icons.bookmark : Icons.bookmark_border,
                color: context.primaryTheme,
                size: 26.sp,
              ),
            ),
          );
        }),
        SizedBox(width: 12.w),
        // Chat
        Expanded(
          child: Container(
            height: 44.sp,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(8.r),
              border: Border.all(color: context.primaryTheme),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                CustomImage("assets/svg/icons/chat.svg", color: context.primaryTheme),
                SizedBox(width: 8.w),
                Text("Chat", style: context.buttonMediumBold?.copyWith(color: context.primaryTheme)),
              ],
            ),
          ),
        ),
        SizedBox(width: 12.w),
        // Meet
        Expanded(
          child: Container(
            height: 44.sp,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(8.r),
              border: Border.all(color: context.primaryTheme),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                CustomImage("assets/svg/icons/video.svg", color: context.primaryTheme),
                SizedBox(width: 8.w),
                Text("Meet", style: context.buttonMediumBold?.copyWith(color: context.primaryTheme)),
              ],
            ),
          ),
        ),
      ],
    );
  }
}
