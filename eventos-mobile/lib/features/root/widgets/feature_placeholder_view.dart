import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../../../utils/extension/theme_ext.dart';

class FeaturePlaceholderView extends StatelessWidget {
  final String title;

  const FeaturePlaceholderView({super.key, required this.title});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: context.backgroundColor,
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.build_circle_outlined, size: 64.sp, color: context.caption),
            SizedBox(height: 16.h),
            Text(
              "Yet to implement",
              style: context.titleLarge?.copyWith(color: context.caption),
            ),
          ],
        ),
      ),
    );
  }
}
