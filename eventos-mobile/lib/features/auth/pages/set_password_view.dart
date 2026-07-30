import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

import '../../../utils/extension/size_ext.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_button.dart';
import '../../../widgets/custom_image.dart';
import '../../../widgets/custom_input.dart';
import '../../../widgets/headers/auth_header.dart';
import '../widgets/header_widget.dart';

class SetPasswordView extends StatelessWidget {
  const SetPasswordView({super.key});

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
                  HeaderWidget(),
                  Text("Password", style: context.bodyRegular),

                  SizedBox(height: 6.h),
                  CustomInput(hint: "Enter Password"),
                  SizedBox(height: 12.h),

                  Text("Re-enter Password", style: context.bodyRegular),

                  SizedBox(height: 6.h),

                  CustomInput(
                    hint: "Re-enter password",
                  ),

                  SizedBox(height: 20.h),


                  Button.roundedText(text: "Login", onTap: () {}),

                  SizedBox(height: 20.h),

                  Center(child: Button.textButton(text: "Skip Password", onTap: (){})),

                  Spacer(),

                  Center(
                    child: CustomImage(
                      "assets/png/logo-primary.png",
                      height: 24.h,
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
