import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../../../widgets/custom_image.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../models/speaker_model.dart';

class SpeakerHeaderDetails extends StatelessWidget {
  final SpeakerDetailModel detail;

  const SpeakerHeaderDetails({super.key, required this.detail});

  @override
  Widget build(BuildContext context) {
    final hasImage = detail.image != null && detail.image!.isNotEmpty;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Avatar
        hasImage
            ? CustomImage(
                detail.image!,
                height: 100.sp,
                width: 100.sp,
                radius: 10.r,
              )
            : Container(
                height: 100.sp,
                width: 100.sp,
                decoration: BoxDecoration(
                  color: context.primaryFocused,
                  borderRadius: BorderRadius.circular(10.r),
                ),
                child: Center(
                  child: Text(
                    detail.name.isNotEmpty ? detail.name[0].toUpperCase() : '?',
                    style: context.h2?.copyWith(
                      color: context.primaryTheme,
                    ),
                  ),
                ),
              ),
        SizedBox(width: 12.w),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                detail.name,
                style: context.h3?.copyWith(color: context.heading),
              ),
              if (detail.designation.isNotEmpty) ...[
                SizedBox(height: 4.h),
                Text(
                  detail.designation,
                  style: context.bodyRegular?.copyWith(
                    color: context.caption,
                  ),
                ),
              ],
              if (detail.category != null && detail.category!.isNotEmpty) ...[
                SizedBox(height: 6.h),
                Container(
                  padding: EdgeInsets.symmetric(
                    horizontal: 8.w,
                    vertical: 3.h,
                  ),
                  decoration: BoxDecoration(
                    color: context.primaryFocused,
                    borderRadius: BorderRadius.circular(6.r),
                  ),
                  child: Text(
                    detail.category!,
                    style: context.specialCaption2?.copyWith(
                      color: context.primaryTheme,
                    ),
                  ),
                ),
              ],
              if (detail.isFeatured) ...[
                SizedBox(height: 6.h),
                Container(
                  padding: EdgeInsets.symmetric(
                    horizontal: 8.w,
                    vertical: 3.h,
                  ),
                  decoration: BoxDecoration(
                    color: context.primaryTheme.withValues(alpha: .1),
                    borderRadius: BorderRadius.circular(6.r),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(
                        Icons.star_rounded,
                        size: 12.sp,
                        color: context.primaryTheme,
                      ),
                      SizedBox(width: 4.w),
                      Text(
                        'Featured',
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
    );
  }
}
