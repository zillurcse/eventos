import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import '../../../utils/extension/theme_ext.dart';

class PermissionWidget extends StatelessWidget {
  final RxBool agreeWithTc;
  const PermissionWidget({super.key, required this.agreeWithTc});

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.start,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: EdgeInsets.all(2.sp),
          child: SizedBox(
            width: 20.h,
            height: 20.h,
            child: Obx(
              () => Checkbox(
                value: agreeWithTc.value,
                onChanged: (value) {
                  agreeWithTc(value);
                },
              ),
            ),
          ),
        ),
        SizedBox(width: 4.w),
        Expanded(
          child: RichText(
            text: TextSpan(
              children: [
                TextSpan(
                  text: "I agree to the ",
                  style: context.titleRegular?.copyWith(color: context.caption),
                ),
                TextSpan(
                  text: "Terms and Conditions ",
                  style: context.titleRegular?.copyWith(
                    color: context.primaryTheme,
                  ),
                ),
                TextSpan(
                  text: "and ",
                  style: context.titleRegular?.copyWith(color: context.caption),
                ),
                TextSpan(
                  text: "Privacy Policy.",
                  style: context.titleRegular?.copyWith(
                    color: context.primaryTheme,
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}
