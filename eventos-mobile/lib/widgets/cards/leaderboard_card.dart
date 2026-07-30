import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

import '../../utils/extension/theme_ext.dart';
import '../custom_image.dart';

class LeaderboardCard extends StatelessWidget {
  final String name;
  final String points;
  final String avatarUrl;
  final String rankIcon;
  final bool isFirstPlace;
  const LeaderboardCard({
    super.key,
    required this.name,
    required this.points,
    required this.avatarUrl,
    required this.rankIcon,
    this.isFirstPlace = false,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: EdgeInsets.symmetric(horizontal: isFirstPlace ? 12.sp : 16.sp),
      padding: EdgeInsets.all(12.sp),
      decoration: BoxDecoration(
        color: context.tertiaryText,
        borderRadius: BorderRadius.circular(12.r),
        border: Border.all(color: context.strokeLight, width: 1.sp),
        boxShadow: isFirstPlace
            ? [
                BoxShadow(
                  color: context.stroke,
                  blurRadius: 10,
                  spreadRadius: 2,
                  offset: const Offset(-1, 1),
                ),
              ]
            : null,
      ),
      child: Row(
        children: [
          CustomImage(avatarUrl, height: 40.sp, width: 40.sp, radius: 8.r),
          SizedBox(width: 12.w),
          Expanded(child: Text(name, style: context.titleLarge)),
          SizedBox(width: 12.w),
          SizedBox(height: 32.h, child: const VerticalDivider()),
          SizedBox(width: 12.w),
          Column(
            children: [
              CustomImage(rankIcon, height: 24.sp),
              SizedBox(height: 2.h),
              Text(
                points,
                style: context.bodyRegular?.copyWith(color: context.caption),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
