import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../shimmer_box.dart';
import '../../utils/extension/theme_ext.dart';

class RoomListSkeleton extends StatelessWidget {
  const RoomListSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScrollView(
      physics: const NeverScrollableScrollPhysics(),
      slivers: [
        SliverPadding(
          padding: EdgeInsets.fromLTRB(16.w, 16.h, 16.w, 24.h),
          sliver: SliverList(
            delegate: SliverChildBuilderDelegate(
              (context, index) => const _RoomCardSkeleton(),
              childCount: 3,
            ),
          ),
        ),
      ],
    );
  }
}

class _RoomCardSkeleton extends StatelessWidget {
  const _RoomCardSkeleton();

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: EdgeInsets.only(bottom: 14.h),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14.r),
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Top image
          AspectRatio(
            aspectRatio: 16 / 9,
            child: ShimmerBox(
              width: double.infinity,
              height: double.infinity,
              topRadius: 0,
            ),
          ),
          Padding(
            padding: EdgeInsets.fromLTRB(14.w, 12.h, 14.w, 0),
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
                ShimmerBox(
                  width: 200.w,
                  height: 14.h,
                  topRadius: 4.r,
                ),
                SizedBox(height: 12.h),
                // Avatars row
                Row(
                  children: [
                    for (int i = 0; i < 4; i++)
                      Align(
                        widthFactor: 0.7,
                        child: Container(
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            border: Border.all(
                              color: Colors.white,
                              width: 2.sp,
                            ),
                          ),
                          child: ClipOval(
                            child: ShimmerBox(
                              width: 28.sp,
                              height: 28.sp,
                              topRadius: 14.r,
                            ),
                          ),
                        ),
                      ),
                    SizedBox(width: 10.w),
                    ShimmerBox(
                      width: 60.w,
                      height: 14.h,
                      topRadius: 4.r,
                    ),
                  ],
                ),
              ],
            ),
          ),
          Padding(
            padding: EdgeInsets.fromLTRB(14.w, 14.h, 14.w, 14.h),
            child: ShimmerBox(
              width: double.infinity,
              height: 40.h,
              topRadius: 10.r,
            ),
          ),
        ],
      ),
    );
  }
}
