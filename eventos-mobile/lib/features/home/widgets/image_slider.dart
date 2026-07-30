import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

import '../../../utils/extension/size_ext.dart';
import '../../../widgets/custom_image.dart';

class ImageSlider extends StatelessWidget {
  final double height;
  final List<String> imageUrls;
  final double? width;


  const ImageSlider({
    super.key,
    required this.height,
    this.imageUrls = const [],
    this.width,
  });

  static const _placeholder =
      "https://plus.unsplash.com/premium_photo-1701590725747-ac131d4dcffd?w=900&auto=format&fit=crop&q=60";

  @override
  Widget build(BuildContext context) {
    final urls = imageUrls.isNotEmpty ? imageUrls : [_placeholder];

    return SizedBox(
      height: height.sp,
      child: ListView.builder(
        padding: EdgeInsets.only(left: 16.sp),
        scrollDirection: Axis.horizontal,
        itemCount: urls.length,
        itemBuilder: (context, index) {
          return Padding(
            padding: EdgeInsets.only(right: 16.w),
            child: CustomImage(
              urls[index],
              width: width?.sp ?? (context.width * .75).sp,
              height: height.sp,
              radius: 8.r,
              fit: BoxFit.cover,
            ),
          );
        },
      ),
    );
  }
}