import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../shimmer_box.dart';
import '../../utils/extension/theme_ext.dart';

class ChatListSkeleton extends StatelessWidget {
  const ChatListSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return ListView.builder(
      physics: const NeverScrollableScrollPhysics(),
      itemCount: 8,
      itemBuilder: (context, index) {
        return Container(
          margin: EdgeInsets.fromLTRB(16.sp, 0, 16.sp, 8.sp),
          padding: EdgeInsets.all(12.sp),
          decoration: BoxDecoration(
            color: context.tertiaryText,
            borderRadius: BorderRadius.circular(8.r),
          ),
          child: Row(
            children: [
              ShimmerBox(
                width: 40.sp,
                height: 40.sp,
                topRadius: 8.r,
              ),
              SizedBox(width: 12.w),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    ShimmerBox(
                      width: 120.w,
                      height: 14.h,
                      topRadius: 4.r,
                    ),
                    SizedBox(height: 6.h),
                    ShimmerBox(
                      width: 200.w,
                      height: 10.h,
                      topRadius: 4.r,
                    ),
                  ],
                ),
              ),
              SizedBox(width: 12.w),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                mainAxisSize: MainAxisSize.min,
                children: [
                  ShimmerBox(
                    width: 50.w,
                    height: 10.h,
                    topRadius: 4.r,
                  ),
                  SizedBox(height: 8.h),
                  SizedBox(height: 12.h), // space for unread badge placeholder
                ],
              ),
            ],
          ),
        );
      },
    );
  }
}
