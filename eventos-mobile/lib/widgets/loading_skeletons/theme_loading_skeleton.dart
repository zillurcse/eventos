import 'package:expouse/utils/extension/theme_ext.dart';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../custom_image.dart';
import '../shimmer_box.dart';

class ThemeLoadingSkeleton extends StatelessWidget {
  const ThemeLoadingSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: context.backgroundColor,
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            ShimmerBox(
              width: 120.w,
              height: 120.h,
              topRadius: 20.r,
            ),
            SizedBox(height: 32.h),
            ShimmerBox(
              width: 180.w,
              height: 14.h,
              topRadius: 4.r,
            ),
            SizedBox(height: 8.h),
            ShimmerBox(
              width: 120.w,
              height: 14.h,
              topRadius: 4.r,
            ),
            SizedBox(height: 16.h),
            Text(
              "Loading theme...",
              style: context.bodyRegular?.copyWith(
                color: context.tertiaryText,
                fontSize: 14.sp,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
