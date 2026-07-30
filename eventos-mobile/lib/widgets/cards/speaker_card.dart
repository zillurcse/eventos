import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

import '../../utils/extension/size_ext.dart';
import '../../utils/extension/theme_ext.dart';
import '../custom_image.dart';

class SpeakerCard extends StatelessWidget {
  final String name;
  final String designation;
  final String company;
  final String imageUrl;

  const SpeakerCard({
    super.key,
    this.name = '',
    this.designation = '',
    this.company = '',
    this.imageUrl = '',
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(right: 16.sp, bottom: 12.h),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(12.r),
        child: Column(
          children: [
            CustomImage(
              imageUrl.isNotEmpty
                  ? imageUrl
                  : "https://www.mcgill.ca/web-services/files/web-services/styles/hd/public/marco-xu-toupbco62lw-unsplash_cropped.jpg?itok=vnN8-uiS&timestamp=1708957693",
              height: (context.height * .24).sp,
              width: (context.width * .5).sp,
              fit: BoxFit.cover,
            ),
            Container(
              color: context.tertiaryText,
              width: (context.width * .5).sp,
              padding: EdgeInsets.fromLTRB(12.w, 0, 12.w, 0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  SizedBox(height: 12.sp),
                  Text(
                    name.isNotEmpty ? name : 'Speaker',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: context.h2?.copyWith(color: context.heading),
                  ),
                  if (designation.isNotEmpty) ...[
                    SizedBox(height: 6.sp),
                    Text(
                      designation,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: context.bodyRegular
                          ?.copyWith(color: context.caption),
                    ),
                  ],
                   ...[
                    SizedBox(height: 6.sp),
                    Text(
                      company,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: context.specialCaption1
                          ?.copyWith(color: context.caption),
                    ),
                  ],
                  SizedBox(height: 12.sp),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}