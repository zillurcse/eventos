import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../shimmer_box.dart';
import '../../utils/extension/theme_ext.dart';

class DelegateListSkeleton extends StatelessWidget {
  const DelegateListSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScrollView(
      physics: const NeverScrollableScrollPhysics(),
      slivers: [
        SliverList(
          delegate: SliverChildBuilderDelegate(
            (context, index) => const _DelegateCardSkeleton(),
            childCount: 6,
          ),
        ),
      ],
    );
  }
}

class _DelegateCardSkeleton extends StatelessWidget {
  const _DelegateCardSkeleton();

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: EdgeInsets.fromLTRB(16.w, 0, 16.w, 8.h),
      padding: EdgeInsets.all(12.sp),
      decoration: BoxDecoration(
        color: context.tertiaryText,
        borderRadius: BorderRadius.circular(12.r),
        border: Border.all(color: context.strokeLight),
      ),
      child: Row(
        children: [
          ShimmerBox(width: 44.sp, height: 44.sp, topRadius: 8.r),
          SizedBox(width: 8.w),
          Expanded(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                ShimmerBox(width: 120.w, height: 14.h, topRadius: 4.r),
                SizedBox(height: 6.h),
                ShimmerBox(width: 90.w, height: 10.h, topRadius: 4.r),
                SizedBox(height: 4.h),
                ShimmerBox(width: 70.w, height: 10.h, topRadius: 4.r),
              ],
            ),
          ),
          SizedBox(width: 8.w),
          ShimmerBox(width: 40.sp, height: 40.sp, topRadius: 8.r),
        ],
      ),
    );
  }
}
