import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../shimmer_box.dart';
import '../../utils/extension/theme_ext.dart';

class SessionListSkeleton extends StatelessWidget {
  const SessionListSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScrollView(
      physics: const NeverScrollableScrollPhysics(),
      slivers: [
        SliverList(
          delegate: SliverChildBuilderDelegate(
            (context, index) => const _SessionCardSkeleton(),
            childCount: 4,
          ),
        ),
      ],
    );
  }
}

class _SessionCardSkeleton extends StatelessWidget {
  const _SessionCardSkeleton();

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: EdgeInsets.symmetric(horizontal: 16.w, vertical: 8.h),
      decoration: BoxDecoration(
        color: context.tertiaryText,
        borderRadius: BorderRadius.circular(16.r),
        border: Border.all(color: context.strokeLight),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Top row skeleton
          Padding(
            padding: EdgeInsets.fromLTRB(16.w, 16.h, 16.w, 12.h),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                ShimmerBox(width: 160.w, height: 12.h, topRadius: 4.r),
                Row(
                  children: [
                    ShimmerBox(width: 20.sp, height: 20.sp, topRadius: 4.r),
                    SizedBox(width: 14.w),
                    ShimmerBox(width: 20.sp, height: 20.sp, topRadius: 4.r),
                    SizedBox(width: 14.w),
                    ShimmerBox(width: 20.sp, height: 20.sp, topRadius: 4.r),
                  ],
                )
              ],
            ),
          ),

          Padding(
            padding: EdgeInsets.symmetric(horizontal: 16.w),
            child: Divider(color: context.strokeLight, height: 1.h),
          ),

          // Middle row skeleton
          Padding(
            padding: EdgeInsets.fromLTRB(16.w, 14.h, 16.w, 14.h),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                ShimmerBox(width: double.infinity, height: 16.h, topRadius: 4.r),
                SizedBox(height: 6.h),
                ShimmerBox(width: 200.w, height: 14.h, topRadius: 4.r),
                SizedBox(height: 12.h),
                Row(
                  children: [
                    ShimmerBox(width: 14.sp, height: 14.sp, topRadius: 4.r),
                    SizedBox(width: 6.w),
                    ShimmerBox(width: 120.w, height: 10.h, topRadius: 4.r),
                  ],
                ),
              ],
            ),
          ),

          Padding(
            padding: EdgeInsets.symmetric(horizontal: 16.w),
            child: Divider(color: context.strokeLight, height: 1.h),
          ),

          // Bottom row skeleton
          Padding(
            padding: EdgeInsets.fromLTRB(16.w, 12.h, 16.w, 16.h),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                // Overlapping avatar stack skeletons
                Row(
                  children: [
                    for (int i = 0; i < 3; i++)
                      Align(
                        widthFactor: 0.7,
                        child: Container(
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            border: Border.all(color: Colors.white, width: 2.sp),
                          ),
                          child: ClipOval(
                            child: ShimmerBox(width: 32.sp, height: 32.sp, topRadius: 16.r),
                          ),
                        ),
                      ),
                  ],
                ),

                // Sponsor logo skeletons
                Row(
                  children: [
                    for (int i = 0; i < 3; i++)
                      Container(
                        margin: EdgeInsets.only(left: 6.w),
                        width: 32.sp,
                        height: 32.sp,
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(6.r),
                          border: Border.all(color: context.strokeLight, width: 1.r),
                        ),
                        padding: EdgeInsets.all(4.sp),
                        child: ShimmerBox(width: 24.sp, height: 24.sp, topRadius: 4.r),
                      ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
