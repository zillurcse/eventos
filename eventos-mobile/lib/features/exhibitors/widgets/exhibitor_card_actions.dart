import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_image.dart';

class ExhibitorCardActions extends StatelessWidget {
  const ExhibitorCardActions({super.key});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(top: 8.h, bottom: 8.h),
      child: Row(
        children: [
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
          SizedBox(width: 12.w),
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
          SizedBox(width: 12.w),
          Container(
            padding: EdgeInsets.symmetric(horizontal: 14.sp, vertical: 10.sp),
            decoration: BoxDecoration(
              color: context.tertiaryText,
              borderRadius: BorderRadius.circular(8.r),
              border: Border.all(color: context.primaryTheme),
            ),
            child: CustomImage(
              "assets/svg/icons/share.svg",
              height: 20.sp,
              width: 14.sp,
              color: context.primaryTheme,
            ),
          ),
        ],
      ),
    );
  }
}
