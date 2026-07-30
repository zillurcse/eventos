import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../shimmer_box.dart';
import '../../utils/extension/theme_ext.dart';

class ExhibitorListSkeleton extends StatelessWidget {
  const ExhibitorListSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScrollView(
      physics: const NeverScrollableScrollPhysics(),
      slivers: [
        SliverList(
          delegate: SliverChildBuilderDelegate(
            (context, index) => const _ExhibitorCardSkeleton(),
            childCount: 4,
          ),
        ),
      ],
    );
  }
}

class _ExhibitorCardSkeleton extends StatelessWidget {
  const _ExhibitorCardSkeleton();

  @override
  Widget build(BuildContext context) {
    // Relative card width based on screen width multiplier used in original list layout
    final screenWidth = MediaQuery.of(context).size.width;
    final cardWidth = screenWidth * 0.95;

    return Padding(
      padding: EdgeInsets.only(top: 12.h),
      child: Center(
        child: ClipRRect(
          borderRadius: BorderRadius.circular(12.r),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              // Banner Image shimmer
              ShimmerBox(
                width: cardWidth,
                height: screenWidth * 0.15 * 1.3, // Match logic of (context.height * .15).sp roughly
                topRadius: 12.r,
              ),
              // Footer details container shimmer
              Container(
                color: context.tertiaryText,
                width: cardWidth,
                padding: EdgeInsets.fromLTRB(12.w, 0, 12.w, 4.h),
                child: Row(
                  children: [
                    ShimmerBox(width: 48.sp, height: 48.sp, topRadius: 8.r),
                    SizedBox(width: 12.w),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          SizedBox(height: 12.sp),
                          ShimmerBox(width: 120.w, height: 14.h, topRadius: 4.r),
                          SizedBox(height: 6.h),
                          ShimmerBox(width: 80.w, height: 10.h, topRadius: 4.r),
                          SizedBox(height: 12.sp),
                        ],
                      ),
                    ),
                    ShimmerBox(width: 40.sp, height: 40.sp, topRadius: 8.r),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
