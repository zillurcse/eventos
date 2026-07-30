import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

import 'package:get/get.dart';

import '../../../models/delegate_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_image.dart';
import '../delegate_controller.dart';

class DelegateCardActions extends StatelessWidget {
  final DelegateItemModel? delegate;

  const DelegateCardActions({super.key, this.delegate});

  @override
  Widget build(BuildContext context) {
    final isFavorite = delegate?.isFavorite ?? false;

    return Padding(
      padding: EdgeInsets.only(top: 12.h),
      child: Row(
        children: [
          // Bookmark / favourite toggle
          GestureDetector(
            onTap: () {
              if (delegate != null) {
                Get.find<DelegateController>().toggleBookmark(delegate!.id);
              }
            },
            child: Container(
              padding: EdgeInsets.symmetric(
                horizontal: 8.sp,
                vertical: 6.sp,
              ),
              decoration: BoxDecoration(
                color: context.tertiaryText,
                borderRadius: BorderRadius.circular(8.r),
                border: Border.all(
                  color: context.primaryTheme,
                ),
              ),
              child: Icon(
                isFavorite ? Icons.bookmark : Icons.bookmark_border,
                color: context.primaryTheme,
                size: 26.sp,
              ),
            ),
          ),
          SizedBox(width: 8.w),
          // Connect button
          Expanded(
            child: Container(
              height: 42.sp,
              decoration: BoxDecoration(
                color: context.tertiaryText,
                borderRadius: BorderRadius.circular(8.r),
                border: Border.all(color: context.primaryTheme),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  CustomImage(
                    "assets/svg/icons/chat.svg",
                    color: context.primaryTheme,
                  ),
                  SizedBox(width: 8.w),
                  Text(
                    "Chat",
                    style: context.buttonMediumBold?.copyWith(
                      color: context.primaryTheme,
                    ),
                  ),
                ],
              ),
            ),
          ),
          SizedBox(width: 8.w),
          // Chat button
          Expanded(
            child: Container(
              height: 42.sp,
              decoration: BoxDecoration(
                color: context.tertiaryText,
                borderRadius: BorderRadius.circular(8.r),
                border: Border.all(color: context.primaryTheme),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  CustomImage(
                    "assets/svg/icons/video.svg",
                    color: context.primaryTheme,
                  ),
                  SizedBox(width: 8.w),
                  Text(
                    "Meet",
                    style: context.buttonMediumBold?.copyWith(
                      color: context.primaryTheme,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
