import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../../../models/exhibitor_model.dart';
import '../../../utils/extension/theme_ext.dart';

class ExhibitorGetInTouch extends StatelessWidget {
  final ExhibitorModel exhibitor;

  const ExhibitorGetInTouch({super.key, required this.exhibitor});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text("Get in Touch",
            style: context.h2?.copyWith(color: context.heading)),
        SizedBox(height: 16.h),
        Row(
          children: [
            Icon(Icons.phone_outlined,
                color: context.primaryTheme, size: 20.sp),
            SizedBox(width: 12.w),
            Text(
              exhibitor.internalContact?.mobileNumber.isNotEmpty == true
                  ? exhibitor.internalContact!.mobileNumber
                  : exhibitor.mobileNumber?.isNotEmpty == true
                      ? exhibitor.mobileNumber!
                      : "01865-664628",
              style: context.titleRegular
                  ?.copyWith(color: context.primaryTheme),
            ),
          ],
        ),
        SizedBox(height: 16.h),
        Row(
          children: [
            Icon(Icons.email_outlined,
                color: context.primaryTheme, size: 20.sp),
            SizedBox(width: 12.w),
            Text(
              exhibitor.internalContact?.email.isNotEmpty == true
                  ? exhibitor.internalContact!.email
                  : exhibitor.email?.isNotEmpty == true
                      ? exhibitor.email!
                      : "badar@okum.om",
              style: context.titleRegular
                  ?.copyWith(color: context.primaryTheme),
            ),
          ],
        ),
      ],
    );
  }
}
