import 'package:expouse/utils/extension/theme_ext.dart';
import 'package:expouse/widgets/custom_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

class ImageGroup extends StatelessWidget {
  final List<String> imageUrls;
  final int maxDisplay;

  const ImageGroup({super.key, required this.imageUrls, this.maxDisplay = 4});

  @override
  Widget build(BuildContext context) {
    final int extraCount = imageUrls.length - maxDisplay;
    final List<String> displayImages = imageUrls.take(maxDisplay).toList();

    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        ...displayImages.map((url) {
          return Padding(
            padding: EdgeInsets.only(right: 8.sp),
            child: CustomImage(
              url,
              width: 36.sp,
              height: 36.sp,
              radius: 8.r,
              fit: BoxFit.cover,
            ),
          );
        }),

        if (extraCount > 0) ...[
          SizedBox(width: 8.w),
          Text(
            "+$extraCount More",
            style: context.titleRegular?.copyWith(color: context.primaryTheme),
          ),
        ],
      ],
    );
  }
}
