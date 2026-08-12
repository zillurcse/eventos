import 'package:expouse/widgets/custom_input.dart';
import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get_core/src/get_main.dart';
import 'package:get/get_navigation/src/extension_navigation.dart';
import '../../../widgets/app_webview_page.dart';

import '../../../utils/extension/size_ext.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_button.dart';
import '../../../widgets/custom_image.dart';
import '../../../widgets/headers/auth_header.dart';

class OtpView extends StatelessWidget {
  const OtpView({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      resizeToAvoidBottomInset: false,
      body: Column(
        children: [
          AuthHeader(),
          Expanded(
            child: Padding(
              padding: EdgeInsets.all(16.sp),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  SizedBox(height: 12.h),

                  CustomImage(
                    "assets/png/event_logo.png",
                    width: context.width / 3.8,
                  ),

                  SizedBox(height: 20.h),

                  Text("AI Expo 2026", style: context.h5),
                  SizedBox(height: 6.h),

                  Text(
                    "Check your email.",
                    style: context.titleLarge?.copyWith(color: context.caption),
                  ),

                  SizedBox(height: 6.h),

                  RichText(
                    text: TextSpan(
                      children: [
                        TextSpan(
                          text: "We’ve sent you the OTP to your email ",
                          style: context.bodyLarge?.copyWith(
                            color: context.caption,
                          ),
                        ),
                        TextSpan(
                          text: " rajdharajiya@gmail.com",
                          style: context.titleLarge,
                        ),
                      ],
                    ),
                  ),

                  SizedBox(height: 20.h),

                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      CustomInput(hint: "", height: 48.sp, width: 48.sp),
                      SizedBox(width: 12.w),
                      CustomInput(hint: "", height: 48.sp, width: 48.sp),
                      SizedBox(width: 12.w),
                      CustomInput(hint: "", height: 48.sp, width: 48.sp),
                      SizedBox(width: 12.w),
                      CustomInput(hint: "", height: 48.sp, width: 48.sp),
                    ],
                  ),

                  SizedBox(height: 20.h),

                  RichText(
                    textAlign: TextAlign.center,
                    text: TextSpan(
                      children: [
                        TextSpan(
                          text: "By signing in you agree to our  ",
                          style: context.titleRegular?.copyWith(
                            color: context.caption,
                          ),
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
                          style: context.titleRegular?.copyWith(
                            color: context.caption,
                          ),
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

                  SizedBox(height: 20.h),
                  Row(
                    children: [
                      Expanded(
                        flex: 2,
                        child: Button.roundedText(
                          text: "Cancel",
                          width: context.width / 2,
                          onTap: () {},
                          backgroundColor: context.primaryFocused,
                          onBackgroundColor: context.primaryTheme,
                          style: context.buttonLabelLarge?.copyWith(
                            color: context.primaryTheme,
                          ),
                        ),
                      ),
                      SizedBox(width: 16.w),
                      Expanded(
                        flex: 4,
                        child: Button.roundedText(text: "Verify", onTap: () {}),
                      ),
                    ],
                  ),
                  SizedBox(height: 20.h),

                  Center(
                    child: RichText(
                      textAlign: TextAlign.center,
                      text: TextSpan(
                        children: [
                          TextSpan(
                            text: "OTP expires in:  ",
                            style: context.titleRegular,
                          ),
                          TextSpan(
                            text: "02:52",
                            style: context.titleRegular?.copyWith(
                              color: context.redError,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),

                  SizedBox(height: 20.h),

                  Center(
                    child: RichText(
                      textAlign: TextAlign.center,
                      text: TextSpan(
                        children: [
                          TextSpan(
                            text: "Didn’t receive OTP?  ",
                            style: context.titleRegular,
                          ),
                          TextSpan(
                            text: "Resend",
                            style: context.titleRegular?.copyWith(
                              color: context.primaryTheme,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),

                  Spacer(),

                  Center(
                    child: GestureDetector(
                      onTap: () {
                        Get.to(() => const AppWebviewPage(
                          title: 'Expouse',
                          url: 'https://expouse.com/',
                        ));
                      },
                      child: CustomImage(
                        "assets/png/logo-primary.png",
                        height: 24.h,
                      ),
                    ),
                  ),

                  SizedBox(height: context.width / 8),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
