import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../../../../utils/extension/theme_ext.dart';

class PostBodyTextField extends StatelessWidget {
  final TextEditingController controller;
  final String? hint;
  final int? maxLines;

  const PostBodyTextField({
    super.key,
    required this.controller,
    this.hint,
    this.maxLines,
  });

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: controller,
      maxLines: maxLines,
      minLines: 3,
      maxLength: 1000,
      buildCounter: (_, {required currentLength, required isFocused, maxLength}) =>
          const SizedBox.shrink(),
      decoration: InputDecoration(
        border: InputBorder.none,
        isDense: true,
        contentPadding: EdgeInsets.only(bottom: 10.h),
        hintMaxLines: 3,
        hintText: hint ?? 'Anything on your mind? Please share it with the community.',
        hintStyle: context.specialCaption1?.copyWith(color: context.ghost),
      ),
      style: context.specialCaption1?.copyWith(color: context.heading),
    );
  }
}
