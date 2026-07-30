import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../shimmer_box.dart';
import '../../utils/extension/theme_ext.dart';

class ExhibitorDetailsSkeleton extends StatelessWidget {
  const ExhibitorDetailsSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      physics: const NeverScrollableScrollPhysics(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Cover Image Shimmer
          ShimmerBox(
            width: double.infinity,
            height: 150.h,
            topRadius: 0,
          ),
          Container(
            color: context.tertiaryText,
            padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 16.h),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Logo and Info Row
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    ShimmerBox(width: 85.sp, height: 85.sp, topRadius: 12.r),
                    SizedBox(width: 16.w),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          SizedBox(height: 4.h),
                          ShimmerBox(width: 140.w, height: 18.h, topRadius: 4.r),
                          SizedBox(height: 12.h),
                          ShimmerBox(width: 100.w, height: 12.h, topRadius: 4.r),
                          SizedBox(height: 6.h),
                          ShimmerBox(width: 80.w, height: 12.h, topRadius: 4.r),
                        ],
                      ),
                    )
                  ],
                ),
                SizedBox(height: 24.h),

                // Action Buttons
                Row(
                  children: [
                    ShimmerBox(width: 44.sp, height: 44.sp, topRadius: 8.r),
                    SizedBox(width: 12.w),
                    Expanded(child: ShimmerBox(width: double.infinity, height: 44.sp, topRadius: 8.r)),
                    SizedBox(width: 12.w),
                    Expanded(child: ShimmerBox(width: double.infinity, height: 44.sp, topRadius: 8.r)),
                  ],
                ),
                SizedBox(height: 24.h),
                Divider(color: context.strokeLight),
                SizedBox(height: 20.h),

                // Rate Us
                Center(
                  child: Column(
                    children: [
                      ShimmerBox(width: 80.w, height: 16.h, topRadius: 4.r),
                      SizedBox(height: 12.h),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: List.generate(5, (_) => Padding(
                          padding: EdgeInsets.symmetric(horizontal: 4.w),
                          child: ShimmerBox(width: 28.sp, height: 28.sp, topRadius: 14.r),
                        )),
                      )
                    ],
                  ),
                ),
                SizedBox(height: 20.h),
                Divider(color: context.strokeLight),
                SizedBox(height: 20.h),

                // About
                ShimmerBox(width: 60.w, height: 16.h, topRadius: 4.r),
                SizedBox(height: 16.h),
                ShimmerBox(width: double.infinity, height: 12.h, topRadius: 4.r),
                SizedBox(height: 6.h),
                ShimmerBox(width: double.infinity, height: 12.h, topRadius: 4.r),
                SizedBox(height: 6.h),
                ShimmerBox(width: 200.w, height: 12.h, topRadius: 4.r),
                SizedBox(height: 32.h),

                // Get in Touch
                ShimmerBox(width: 100.w, height: 16.h, topRadius: 4.r),
                SizedBox(height: 16.h),
                Row(
                  children: [
                    ShimmerBox(width: 20.sp, height: 20.sp, topRadius: 4.r),
                    SizedBox(width: 12.w),
                    ShimmerBox(width: 120.w, height: 14.h, topRadius: 4.r),
                  ],
                ),
                SizedBox(height: 16.h),
                Row(
                  children: [
                    ShimmerBox(width: 20.sp, height: 20.sp, topRadius: 4.r),
                    SizedBox(width: 12.w),
                    ShimmerBox(width: 140.w, height: 14.h, topRadius: 4.r),
                  ],
                ),
                SizedBox(height: 24.h),

                // Socials
                Row(
                  spacing: 12.w,
                  children: List.generate(6, (_) => ShimmerBox(width: 44.sp, height: 44.sp, topRadius: 8.r)),
                ),
                SizedBox(height: 40.h),
              ],
            ),
          )
        ],
      ),
    );
  }
}
