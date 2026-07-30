import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../shimmer_box.dart';
import '../../utils/extension/theme_ext.dart';

class SpeakerDetailsSkeleton extends StatelessWidget {
  const SpeakerDetailsSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScrollView(
      physics: const NeverScrollableScrollPhysics(),
      slivers: [
        SliverToBoxAdapter(
          child: Container(
            color: context.tertiaryText,
            padding: EdgeInsets.fromLTRB(16.w, 16.h, 16.w, 16.h),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Avatar + Header
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    ShimmerBox(width: 100.sp, height: 100.sp, topRadius: 10.r),
                    SizedBox(width: 12.w),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          ShimmerBox(width: 150.w, height: 16.h, topRadius: 4.r),
                          SizedBox(height: 6.h),
                          ShimmerBox(width: 110.w, height: 12.h, topRadius: 4.r),
                          SizedBox(height: 6.h),
                          ShimmerBox(width: 80.w, height: 12.h, topRadius: 4.r),
                        ],
                      ),
                    ),
                  ],
                ),
                SizedBox(height: 12.h),
                // Action Buttons Mock
                Row(
                  children: [
                    Expanded(child: ShimmerBox(width: double.infinity, height: 44.sp, topRadius: 8.r)),
                    SizedBox(width: 12.w),
                    Expanded(child: ShimmerBox(width: double.infinity, height: 44.sp, topRadius: 8.r)),
                  ],
                ),
                SizedBox(height: 16.h),
                Divider(color: context.strokeLight),
                SizedBox(height: 12.h),
                // About Title
                ShimmerBox(width: 60.w, height: 16.h, topRadius: 4.r),
                SizedBox(height: 8.h),
                // Bio Description lines
                ShimmerBox(width: double.infinity, height: 12.h, topRadius: 4.r),
                SizedBox(height: 6.h),
                ShimmerBox(width: double.infinity, height: 12.h, topRadius: 4.r),
                SizedBox(height: 6.h),
                ShimmerBox(width: 180.w, height: 12.h, topRadius: 4.r),
                SizedBox(height: 16.h),
                // Info rows
                Row(
                  children: [
                    ShimmerBox(width: 20.sp, height: 20.sp, topRadius: 4.r),
                    SizedBox(width: 8.w),
                    ShimmerBox(width: 140.w, height: 12.h, topRadius: 4.r),
                  ],
                ),
                SizedBox(height: 8.h),
                Row(
                  children: [
                    ShimmerBox(width: 20.sp, height: 20.sp, topRadius: 4.r),
                    SizedBox(width: 8.w),
                    ShimmerBox(width: 180.w, height: 12.h, topRadius: 4.r),
                  ],
                ),
                SizedBox(height: 16.h),
                // Tags
                Wrap(
                  spacing: 8.w,
                  runSpacing: 6.h,
                  children: List.generate(3, (_) => ShimmerBox(width: 65.w, height: 20.h, topRadius: 10.r)),
                ),
                SizedBox(height: 12.h),
                Divider(color: context.strokeLight),
                SizedBox(height: 12.h),
                // Social links
                Row(
                  spacing: 12.w,
                  children: List.generate(4, (_) => ShimmerBox(width: 44.sp, height: 44.sp, topRadius: 8.r)),
                ),
                SizedBox(height: 24.h),
              ],
            ),
          ),
        ),
      ],
    );
  }
}
