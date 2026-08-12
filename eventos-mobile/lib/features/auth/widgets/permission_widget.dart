import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import 'package:flutter/gestures.dart';
import '../../../widgets/app_webview_page.dart';
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
                  recognizer: TapGestureRecognizer()
                    ..onTap = () {
                      Get.to(() => const AppWebviewPage(
                        title: 'Terms and Conditions',
                        url: 'https://expouse.com/terms',
                      ));
                    },
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
                  recognizer: TapGestureRecognizer()
                    ..onTap = () {
                      Get.to(() => const AppWebviewPage(
                        title: 'Privacy Policy',
                        url: 'https://expouse.com/privacy',
                      ));
                    },
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}
