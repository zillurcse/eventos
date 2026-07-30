import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

import '../utils/extension/theme_ext.dart';

class CustomInput extends StatelessWidget {
  final String hint;
  final bool obscureText;
  final bool isEditable;
  final int maxLines;
  final TextEditingController? controller;
  final Function(String value)? onChanged;
  final String? Function(String?)? validator;
  final Widget? suffix;
  final double? width;
  final double? height;

  const CustomInput({
    super.key,
    required this.hint,
    this.obscureText = false,
    this.isEditable = true,
    this.maxLines = 1,
    this.controller,
    this.onChanged,
    this.validator,
    this.suffix,
    this.width,
    this.height,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      height: height,
      width: width,
      padding: EdgeInsets.symmetric(horizontal: 12.w),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: context.ghost),
      ),
      child: Row(
        children: [
          Expanded(
            child: TextFormField(
              enabled: isEditable,
              controller: controller,
              onChanged: onChanged,
              validator: validator,
              obscureText: obscureText,
              maxLines: maxLines,
              decoration: InputDecoration(
                border: InputBorder.none,
                hintText: hint,
                hintStyle: context.bodyRegular,
              ),
            ),
          ),
          suffix ?? Container(),
        ],
      ),
    );
  }
}
