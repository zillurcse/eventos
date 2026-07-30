import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

import '../../../utils/extension/theme_ext.dart';

class SignUpItem extends StatelessWidget {
  final String label;
  final Widget widget;
  const SignUpItem({super.key, required this.label, required this.widget});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: context.bodyRegular),

        SizedBox(height: 6.h),

        widget,

        SizedBox(height: 12.h),
      ],
    );
  }
}
