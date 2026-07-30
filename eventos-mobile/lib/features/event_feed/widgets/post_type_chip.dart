import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_image.dart';

/// A single post-type chip.
/// [isSelected] controls the highlighted state.
class PostTypeChip extends StatelessWidget {
  final String iconUrl;
  final bool isSelected;
  final VoidCallback onTap;

  const PostTypeChip({
    super.key,
    required this.iconUrl,
    required this.isSelected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        curve: Curves.easeInOut,
        height: 40.sp,
        width: 40.sp,
        padding: EdgeInsets.all(12.sp),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(8.r),
          color: isSelected ? context.primaryFocused : context.backgroundColor,
        ),
        child: CustomImage(
          iconUrl,
          color: isSelected ? context.primaryTheme : context.ghost,
        ),
      ),
    );
  }
}