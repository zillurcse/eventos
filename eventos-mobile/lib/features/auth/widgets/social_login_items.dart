import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_image.dart';

class SocialLoginItems extends StatelessWidget {
  const SocialLoginItems({super.key});

  @override
  Widget build(BuildContext context) {
    return  Column(
      children: [
        SizedBox(height: 12.h),

        Divider(),

        SizedBox(height: 12.h),

        Center(
          child: Text("Or continue with", style: context.bodyLarge),
        ),

        SizedBox(height: 20.h),

        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            CustomImage(
              "assets/svg/img/fb.svg",
              radius: 8.r,
              height: 40.h,
            ),
            SizedBox(width: 12.w),
            CustomImage(
              "assets/svg/img/google.svg",
              radius: 8.r,
              height: 40.h,
            ),
            SizedBox(width: 12.w),
            CustomImage(
              "assets/svg/img/in.svg",
              radius: 8.r,
              height: 40.h,
            ),
          ],
        ),
      ],
    );
  }
}
