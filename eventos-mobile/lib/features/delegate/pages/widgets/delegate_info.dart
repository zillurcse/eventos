import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

import '../../../../models/delegate_model.dart';
import '../../../../utils/extension/theme_ext.dart';
import '../../../../widgets/custom_image.dart';
import '../../widgets/delegate_card_actions.dart';

class DelegateInfo extends StatelessWidget {
  final DelegateItemModel delegate;

  const DelegateInfo({super.key, required this.delegate});

  @override
  Widget build(BuildContext context) {
    final hasImage = delegate.image.isNotEmpty;

    return SliverToBoxAdapter(
      child: Container(
        color: context.tertiaryText,
        padding: EdgeInsets.fromLTRB(16.w, 12.h, 16.w, 4.h),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                // Avatar or placeholder
                hasImage
                    ? CustomImage(
                        delegate.image,
                        height: 100.sp,
                        width: 100.sp,
                        radius: 8.r,
                      )
                    : Container(
                        height: 100.sp,
                        width: 100.sp,
                        decoration: BoxDecoration(
                          color: context.primaryFocused,
                          borderRadius: BorderRadius.circular(8.r),
                        ),
                        child: Center(
                          child: Text(
                            delegate.name.isNotEmpty
                                ? delegate.name[0].toUpperCase()
                                : '?',
                            style: context.h3?.copyWith(
                              color: context.primaryTheme,
                            ),
                          ),
                        ),
                      ),
                SizedBox(width: 12.w),
                Expanded(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        delegate.name,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: context.h3?.copyWith(color: context.heading),
                      ),
                      if (delegate.designation.isNotEmpty) ...[
                        SizedBox(height: 4.h),
                        Text(
                          delegate.designation,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: context.bodyRegular?.copyWith(
                            color: context.caption,
                          ),
                        ),
                      ],
                      if (delegate.company.isNotEmpty) ...[
                        SizedBox(height: 4.h),
                        Text(
                          delegate.company,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: context.specialCaption1?.copyWith(
                            color: context.primaryTheme,
                          ),
                        ),
                      ],
                      if (delegate.country.isNotEmpty) ...[
                        SizedBox(height: 4.h),
                        Container(
                          padding: EdgeInsets.symmetric(
                            horizontal: 6.w,
                            vertical: 2.h,
                          ),
                          decoration: BoxDecoration(
                            color: context.primaryFocused,
                            borderRadius: BorderRadius.circular(4.r),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(
                                Icons.location_on_outlined,
                                size: 12.sp,
                                color: context.primaryTheme,
                              ),
                              SizedBox(width: 4.w),
                              Text(
                                delegate.country,
                                style: context.specialCaption2?.copyWith(
                                  color: context.primaryTheme,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ],
            ),
            DelegateCardActions(delegate: delegate),
            SizedBox(height: 12.h),
            const Divider(),
            SizedBox(height: 8.h),
            Text("About", style: context.h3),
            SizedBox(height: 8.h),
            Text(
              delegate.designation.isNotEmpty
                  ? delegate.designation
                  : "No additional information available.",
              style: context.bodyRegular,
            ),
            Padding(
              padding: EdgeInsets.symmetric(vertical: 12.h),
              child: Row(
                spacing: 8.w,
                mainAxisSize: MainAxisSize.min,
                children: [
                  CustomImage(
                    "assets/png/twiter.png",
                    height: 40.sp,
                    fit: BoxFit.fill,
                  ),
                  CustomImage(
                    "assets/png/insta.png",
                    height: 40.sp,
                    fit: BoxFit.fill,
                  ),
                  CustomImage(
                    "assets/png/in.png",
                    height: 40.sp,
                    fit: BoxFit.fill,
                  ),
                  CustomImage(
                    "assets/png/fb.png",
                    height: 40.sp,
                    fit: BoxFit.fill,
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
