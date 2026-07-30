import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../shimmer_box.dart';
import '../../utils/extension/theme_ext.dart';

class EventFeedSkeleton extends StatelessWidget {
  const EventFeedSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScrollView(
      physics: const NeverScrollableScrollPhysics(),
      slivers: [
        SliverList(
          delegate: SliverChildBuilderDelegate(
            (context, index) => const _FeedPostSkeleton(),
            childCount: 4,
          ),
        ),
      ],
    );
  }
}

class _FeedPostSkeleton extends StatelessWidget {
  const _FeedPostSkeleton();

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: EdgeInsets.fromLTRB(16.w, 0, 16.w, 12.h),
      padding: EdgeInsets.fromLTRB(16.w, 12.h, 16.w, 12.h),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(8.r),
        color: context.tertiaryText,
        border: Border.all(color: context.strokeLight),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Post Header Skeleton
          Row(
            children: [
              ShimmerBox(width: 40.sp, height: 40.sp, topRadius: 8.r),
              SizedBox(width: 12.w),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    ShimmerBox(width: 120.w, height: 12.h, topRadius: 4.r),
                    SizedBox(height: 6.h),
                    ShimmerBox(width: 80.w, height: 10.h, topRadius: 4.r),
                  ],
                ),
              ),
            ],
          ),
          SizedBox(height: 12.h),
          // Body text skeleton lines
          ShimmerBox(width: double.infinity, height: 12.h, topRadius: 4.r),
          SizedBox(height: 6.h),
          ShimmerBox(width: double.infinity, height: 12.h, topRadius: 4.r),
          SizedBox(height: 6.h),
          ShimmerBox(width: 150.w, height: 12.h, topRadius: 4.r),
          SizedBox(height: 12.h),
          // Content/image placeholder skeleton
          ShimmerBox(width: double.infinity, height: 120.h, topRadius: 6.r),
          SizedBox(height: 12.h),
          // LikeComment action bar skeleton
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              ShimmerBox(width: 60.w, height: 14.h, topRadius: 4.r),
              ShimmerBox(width: 60.w, height: 14.h, topRadius: 4.r),
              ShimmerBox(width: 60.w, height: 14.h, topRadius: 4.r),
            ],
          ),
        ],
      ),
    );
  }
}
