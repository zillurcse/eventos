import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../../../../utils/extension/theme_ext.dart';

class PostDateTimeField extends StatelessWidget {
  final String hint;
  final String? value;
  final VoidCallback onTap;

  const PostDateTimeField({
    super.key,
    required this.hint,
    required this.value,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: EdgeInsets.symmetric(horizontal: 14.w, vertical: 12.h),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(8.r),
          border: Border.all(color: context.stroke),
          color: context.tertiaryText,
        ),
        child: Text(
          value ?? hint,
          style: context.specialCaption1?.copyWith(
            color: value != null ? context.heading : context.ghost,
          ),
        ),
      ),
    );
  }
}
