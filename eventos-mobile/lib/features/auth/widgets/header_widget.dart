import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

import '../../../utils/extension/theme_ext.dart';

class HeaderWidget extends StatelessWidget {
  const HeaderWidget({super.key});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(height: 12.h),
        Text('Welcome', style: context.h5),
        SizedBox(height: 6.h),
        Text(
          'Sign in to your account.',
          style: context.titleLarge?.copyWith(color: context.caption),
        ),
        SizedBox(height: 16.h),
      ],
    );
  }
}
