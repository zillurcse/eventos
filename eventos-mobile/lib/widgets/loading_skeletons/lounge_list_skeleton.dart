import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../shimmer_box.dart';

class LoungeListSkeleton extends StatelessWidget {
  const LoungeListSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScrollView(
      physics: const NeverScrollableScrollPhysics(),
      slivers: [
        SliverPadding(
          padding: EdgeInsets.fromLTRB(16.w, 12.h, 16.w, 24.h),
          sliver: SliverList(
            delegate: SliverChildBuilderDelegate(
              (context, index) => const _LoungeCardSkeleton(),
              childCount: 3,
            ),
          ),
        ),
      ],
    );
  }
}

class _LoungeCardSkeleton extends StatelessWidget {
  const _LoungeCardSkeleton();

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: EdgeInsets.only(bottom: 14.h),
      padding: EdgeInsets.fromLTRB(16.w, 18.h, 16.w, 16.h),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16.r),
      ),
      child: Column(
        children: [
          // Table name
          ShimmerBox(
            width: 150.w,
            height: 20.h,
            topRadius: 4.r,
          ),
          SizedBox(height: 6.h),
          // Table kind
          ShimmerBox(
            width: 100.w,
            height: 14.h,
            topRadius: 4.r,
          ),
          SizedBox(height: 12.h),
          // Seats available badge
          ShimmerBox(
            width: 120.w,
            height: 24.h,
            topRadius: 12.r,
          ),
          SizedBox(height: 16.h),
          // Centerpiece graphic
          SizedBox(
            height: 240.h,
            child: Center(
              child: ShimmerBox(
                width: 160.w,
                height: 160.w,
                topRadius: 80.r, // roughly a circular area
              ),
            ),
          ),
          SizedBox(height: 16.h),
          // CTA button
          ShimmerBox(
            width: double.infinity,
            height: 44.h,
            topRadius: 10.r,
          ),
        ],
      ),
    );
  }
}
