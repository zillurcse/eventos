import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../../../../utils/extension/theme_ext.dart';
import '../../../../widgets/custom_image.dart';

class TypeChip extends StatelessWidget {
  final String iconUrl;
  final VoidCallback onTap;

  const TypeChip({super.key, required this.iconUrl, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        height: 40.sp,
        width: 40.sp,
        padding: EdgeInsets.all(10.sp),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(8.r),
          color: context.backgroundColor,
        ),
        child: CustomImage(
          iconUrl,
          color: context.ghost,
        ),
      ),
    );
  }
}
