import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

import '../../utils/extension/size_ext.dart';
import '../../utils/extension/theme_ext.dart';
import '../custom_image.dart';

class ExhibitorCard extends StatelessWidget {
  final String name;
  final String stallNo;
  final String exhibitorType;
  final String bannerUrl;
  final String logoUrl;

  const ExhibitorCard({
    super.key,
    this.name = '',
    this.stallNo = '',
    this.exhibitorType = '',
    this.bannerUrl = '',
    this.logoUrl = '',
  });

  @override
  Widget build(BuildContext context) {
    final subtitle = [
      if (stallNo.isNotEmpty) stallNo,
      if (exhibitorType.isNotEmpty) exhibitorType,
    ].join(', ');

    return Padding(
      padding: EdgeInsets.only(right: 16.sp, bottom: 12.h),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(12.r),
        child: Column(
          children: [
            // Banner
            CustomImage(
              bannerUrl,
              height: (context.height * .15).sp,
              width: (context.width * .75).sp,
              fit: BoxFit.cover,
            ),
            // Footer row
            Container(
              color: context.tertiaryText,
              width: (context.width * .75).sp,
              padding: EdgeInsets.fromLTRB(12.w, 0, 12.w, 0),
              child: Row(
                children: [
                  CustomImage(
                    logoUrl,
                    height: 48.sp,
                    width: 48.sp,
                    radius: 8.r,
                  ),
                  SizedBox(width: 12.w),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        SizedBox(height: 12.sp),
                        Text(
                          name.isNotEmpty ? name : 'Exhibitor',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: context.h2,
                        ),
                        if (subtitle.isNotEmpty) ...[
                          SizedBox(height: 6.sp),
                          Text(
                            subtitle,
                            style: context.bodyRegular
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
          ],
        ),
      ),
    );
  }
}
