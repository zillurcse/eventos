import 'package:expouse/utils/theme/app_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

class Button extends StatelessWidget {
  final Widget child;
  final VoidCallback onTap;

  const Button._internal({required this.child, required this.onTap});

  factory Button.roundedText({
    required String text,
    required VoidCallback onTap,
    double height = 40,
    double radius = 8,
    Color? backgroundColor,
    Color? onBackgroundColor,
    Color? borderColor,
    double? width,
    TextStyle? style,
  }) {
    return Button._internal(
      onTap: onTap,
      child: Container(
        height: height.h,
        width: width ?? double.infinity,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: backgroundColor ?? primaryTheme,
          borderRadius: BorderRadius.circular(radius),
          border: Border.all(color: borderColor ?? Colors.transparent),
        ),
        child: FittedBox(
          child: Text(
            text,
            style:
                style ??
                TextStyle(
                  color: onBackgroundColor ?? accentPrimary,
                  fontSize: 16.sp,
                  fontWeight: FontWeight.w700,
                  height: 1.3,
                  letterSpacing: 0.0,
                ),
          ),
        ),
      ),
    );
  }

  factory Button.textButton({
    required String text,
    required VoidCallback onTap,
    TextStyle? style,
  }) {
    return Button._internal(
      onTap: onTap,
      child: Text(
        text,
        style:
            style ??
            TextStyle(
              color: primaryTheme,
              fontSize: 14.sp,
              fontWeight: FontWeight.w600,
              height: 1.3,
              letterSpacing: 0.0,
            ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return InkWell(onTap: onTap, child: child);
  }
}
