import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../shimmer_box.dart';
import '../../utils/extension/theme_ext.dart';

class SpeakerListSkeleton extends StatelessWidget {
  const SpeakerListSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScrollView(
      physics: const NeverScrollableScrollPhysics(),
      slivers: [
        SliverList(
          delegate: SliverChildBuilderDelegate(
            (context, index) => const _SpeakerCardSkeleton(),
            childCount: 6,
          ),
        ),
      ],
    );
  }
}

class _SpeakerCardSkeleton extends StatelessWidget {
  const _SpeakerCardSkeleton();

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
          ShimmerBox(width: 40.sp, height: 40.sp, topRadius: 8.r),
          SizedBox(width: 8.w),
          Expanded(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                ShimmerBox(width: 140.w, height: 14.h, topRadius: 4.r),
                SizedBox(height: 6.h),
                ShimmerBox(width: 100.w, height: 10.h, topRadius: 4.r),
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
