import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

import '../../../utils/extension/size_ext.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_image.dart';

class HeaderWidget extends StatelessWidget {
  const HeaderWidget({super.key});

  @override
  Widget build(BuildContext context) {
    return Column(
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
          "Sign in to your account.",
          style: context.titleLarge?.copyWith(color: context.caption),
        ),

        SizedBox(height: 16.h),
      ],
    );
  }
}
