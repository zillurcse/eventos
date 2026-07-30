import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../../../widgets/custom_image.dart';
import '../../../models/exhibitor_model.dart';
import '../../../utils/extension/theme_ext.dart';

class ExhibitorHeaderDetails extends StatelessWidget {
  final ExhibitorModel exhibitor;

  const ExhibitorHeaderDetails({super.key, required this.exhibitor});

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          height: 85.sp,
          width: 85.sp,
          padding: EdgeInsets.all(12.sp),
          decoration: BoxDecoration(
            color: context.primaryTheme,
            borderRadius: BorderRadius.circular(12.r),
          ),
          child: exhibitor.logoUrl.isNotEmpty
              ? CustomImage(exhibitor.logoUrl, fit: BoxFit.contain)
              : Icon(Icons.graphic_eq, color: context.accentPrimary, size: 40.sp),
        ),
        SizedBox(width: 16.w),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SizedBox(height: 4.h),
              Text(
                exhibitor.name.isNotEmpty ? exhibitor.name : "Expouse DSA",
                style: context.h2?.copyWith(color: context.heading),
              ),
              SizedBox(height: 12.h),
              Text(
                "Stall No : ${exhibitor.stallNo.isNotEmpty ? exhibitor.stallNo : '10082'}",
                style: context.bodyRegular?.copyWith(color: context.caption),
              ),
              SizedBox(height: 4.h),
              Text(
                "Type : ${exhibitor.exhibitorType.isNotEmpty ? exhibitor.exhibitorType : 'Exhibitor'}",
                style: context.bodyRegular?.copyWith(color: context.caption),
              ),
            ],
          ),
        )
      ],
    );
  }
}
