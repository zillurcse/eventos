import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../shimmer_box.dart';
import '../../utils/extension/theme_ext.dart';

class BadgeListSkeleton extends StatelessWidget {
  const BadgeListSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return ListView.separated(
      padding: EdgeInsets.fromLTRB(16.w, 16.h, 16.w, 24.h),
      itemCount: 2,
      separatorBuilder: (_, _) => SizedBox(height: 20.h),
      itemBuilder: (context, index) => const _BadgeCardSkeleton(),
    );
  }
}

class _BadgeCardSkeleton extends StatelessWidget {
  const _BadgeCardSkeleton();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.all(16.w),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16.r),
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Column(
        children: [
          // Main badge render area
          ShimmerBox(
            width: double.infinity,
            height: 380.h,
            topRadius: 12.r,
          ),
          SizedBox(height: 12.h),
          // Role label
          ShimmerBox(
            width: 80.w,
            height: 12.h,
            topRadius: 4.r,
          ),
          SizedBox(height: 6.h),
          // Name
          ShimmerBox(
            width: 150.w,
            height: 18.h,
            topRadius: 4.r,
          ),
          SizedBox(height: 12.h),
          // Actions
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              ShimmerBox(
                width: 100.w,
                height: 36.h,
                topRadius: 18.r,
              ),
              SizedBox(width: 8.w),
              ShimmerBox(
                width: 100.w,
                height: 36.h,
                topRadius: 18.r,
              ),
            ],
          ),
        ],
      ),
    );
  }
}
