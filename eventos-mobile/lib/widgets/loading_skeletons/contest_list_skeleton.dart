import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../shimmer_box.dart';

class ContestListSkeleton extends StatelessWidget {
  const ContestListSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScrollView(
      physics: const NeverScrollableScrollPhysics(),
      slivers: [
        SliverPadding(
          padding: EdgeInsets.fromLTRB(16.w, 16.h, 16.w, 24.h),
          sliver: SliverList(
            delegate: SliverChildBuilderDelegate(
              (context, index) => const _ContestCardSkeleton(),
              childCount: 3,
            ),
          ),
        ),
      ],
    );
  }
}

class _ContestCardSkeleton extends StatelessWidget {
  const _ContestCardSkeleton();

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: EdgeInsets.only(bottom: 14.h),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12.r),
        border: Border.all(color: const Color(0xFFE8ECF1)),
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Banner image
          AspectRatio(
            aspectRatio: 16 / 9,
            child: ShimmerBox(
              width: double.infinity,
              height: double.infinity,
              topRadius: 0,
            ),
          ),
          Padding(
            padding: EdgeInsets.fromLTRB(16.w, 14.h, 16.w, 16.h),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Title
                ShimmerBox(
                  width: double.infinity,
                  height: 20.h,
                  topRadius: 4.r,
                ),
                SizedBox(height: 6.h),
                // Entries count
                ShimmerBox(
                  width: 80.w,
                  height: 14.h,
                  topRadius: 4.r,
                ),
                SizedBox(height: 14.h),
                // Status label
                ShimmerBox(
                  width: 120.w,
                  height: 14.h,
                  topRadius: 4.r,
                ),
                SizedBox(height: 8.h),
                // Countdown boxes
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    for (int i = 0; i < 4; i++)
                      Expanded(
                        child: Padding(
                          padding: EdgeInsets.only(right: i < 3 ? 8.w : 0),
                          child: ShimmerBox(
                            width: double.infinity,
                            height: 64.h,
                            topRadius: 8.r,
                          ),
                        ),
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
