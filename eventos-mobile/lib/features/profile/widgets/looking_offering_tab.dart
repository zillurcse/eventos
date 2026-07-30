import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../../../utils/extension/theme_ext.dart';

class LookingOfferingTab extends StatelessWidget {
  const LookingOfferingTab({super.key});

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: EdgeInsets.symmetric(horizontal: 20.w, vertical: 24.h),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _buildSelectionField(context, label: "Looking for", hint: "Select Options"),
          SizedBox(height: 24.h),
          _buildSelectionField(context, label: "Offering", hint: "Select Options"),
        ],
      ),
    );
  }

  Widget _buildSelectionField(BuildContext context, {required String label, required String hint}) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: context.bodyRegular?.copyWith(
            color: context.heading,
            fontSize: 14.sp,
          ),
        ),
        SizedBox(height: 12.h),
        Container(
          padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 14.h),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(8.r),
            border: Border.all(color: context.strokeLight, width: 1),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                hint,
                style: context.bodyRegular?.copyWith(color: context.caption),
              ),
              Icon(
                Icons.arrow_forward_ios_rounded,
                color: context.caption,
                size: 16.sp,
              ),
            ],
          ),
        ),
      ],
    );
  }
}
