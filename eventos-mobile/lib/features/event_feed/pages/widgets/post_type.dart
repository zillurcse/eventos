import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

import '../../../../utils/extension/theme_ext.dart';
import '../../../../widgets/custom_image.dart';

class PostType extends StatelessWidget {
  final bool isSelected;
  final Map<String, dynamic> postItem;
  final VoidCallback onTap;
  const PostType({
    super.key,
    required this.isSelected,
    required this.postItem,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        curve: Curves.easeInOut,
        height: 46.sp,
        padding: EdgeInsets.all(12.sp),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(12.r),
          color: isSelected ? context.primaryTheme : context.backgroundColor,
        ),
        child: Row(
          children: [
            CustomImage(
              postItem["url"],
              color: isSelected ? context.tertiaryText : context.ghost,
            ),
            isSelected ? Padding(
              padding: EdgeInsets.only(left: 8.w),
              child: Text(
                postItem["name"],
                style: context.buttonMediumBold?.copyWith(
                  color: context.tertiaryText,
                ),
              ),
            ) : SizedBox.shrink(),
          ],
        ),
      ),
    );
  }
}