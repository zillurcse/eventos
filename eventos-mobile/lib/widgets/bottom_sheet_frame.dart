import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

import '../utils/extension/theme_ext.dart';

class BottomSheetFrame extends StatelessWidget {
  final Widget child;
  const BottomSheetFrame({super.key, required this.child});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: EdgeInsets.symmetric(vertical: 12.h, horizontal: 16.w),
      decoration: BoxDecoration(
        color: context.tertiaryText,
        borderRadius: BorderRadius.vertical(top: Radius.circular(20.r)),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Center(
            child: Container(
              height: 3.h,
              width: 80.w,
              decoration: BoxDecoration(
                color: context.ghost,
                borderRadius: BorderRadius.circular(50.r),
              ),
            ),
          ),
          SizedBox(height: 12.h),
          Flexible(
            child: SingleChildScrollView(
              child: child,
            ),
          ),
        ],
      ),
    );
  }
}
