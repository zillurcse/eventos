import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../utils/extension/theme_ext.dart';
import 'shimmer_box.dart';

class AppSkeleton extends StatelessWidget {
  const AppSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return CustomScrollView(
      slivers: [
        SliverList(
          delegate: SliverChildBuilderDelegate(
            (context, index) => const _SkeletonCard(),
            childCount: 5,
          ),
        ),
      ],
    );
  }
}

class _SkeletonCard extends StatelessWidget {
  const _SkeletonCard();

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: EdgeInsets.fromLTRB(16.w, 16.h, 16.w, 0),
      padding: EdgeInsets.all(16.w),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(8.r),
        color: context.tertiaryText,
        border: Border.all(color: context.strokeLight),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              _Box(40.sp, 40.sp, radius: 8.r),
              SizedBox(width: 12.w),
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _Box(120.w, 14.h, radius: 4.r),
                  SizedBox(height: 6.h),
                  _Box(80.w, 10.h, radius: 4.r),
                ],
              ),
            ],
          ),
          SizedBox(height: 12.h),
          _Box(double.infinity, 14.h, radius: 4.r),
          SizedBox(height: 6.h),
          _Box(double.infinity, 14.h, radius: 4.r),
          SizedBox(height: 6.h),
          _Box(180.w, 14.h, radius: 4.r),
        ],
      ),
    );
  }
}

class _Box extends StatelessWidget {
  final double w, h;
  final double radius;
  const _Box(this.w, this.h, {this.radius = 4});

  @override
  Widget build(BuildContext context) {
    return ShimmerBox(
      width: w,
      height: h,
      topRadius: radius,
    );
  }
}
