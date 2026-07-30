import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

import '../../../../models/speaker_model.dart';
import '../../../../utils/extension/theme_ext.dart';
import '../../../../widgets/custom_image.dart';
import '../../widgets/speaker_card_actions.dart';

class SpeakerInfo extends StatelessWidget {
  final SpeakerItemModel speaker;

  const SpeakerInfo({super.key, required this.speaker});

  @override
  Widget build(BuildContext context) {
    final hasImage = speaker.image != null && speaker.image!.isNotEmpty;

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
                        speaker.image!,
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
                            speaker.name.isNotEmpty
                                ? speaker.name[0].toUpperCase()
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
                      // Name
                      Text(
                        speaker.name,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: context.h3?.copyWith(color: context.heading),
                      ),
                      SizedBox(height: 4.h),
                      // Designation
                      Text(
                        speaker.designation,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: context.bodyRegular?.copyWith(
                          color: context.caption,
                        ),
                      ),
                      // Presentation title
                      if (speaker.presentationTitle != null &&
                          speaker.presentationTitle!.isNotEmpty) ...[
                        SizedBox(height: 4.h),
                        Text(
                          speaker.presentationTitle!,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: context.specialCaption1?.copyWith(
                            color: context.primaryTheme,
                          ),
                        ),
                      ],
                      // Category badge
                      if (speaker.category != null &&
                          speaker.category!.isNotEmpty) ...[
                        SizedBox(height: 4.h),
                        Container(
                          padding: EdgeInsets.symmetric(
                            horizontal: 4.w,
                            vertical: 2.h,
                          ),
                          decoration: BoxDecoration(
                            color: context.primaryFocused,
                            borderRadius: BorderRadius.circular(4.r),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              CustomImage(
                                "assets/svg/icons/circle_star.svg",
                                color: context.primaryTheme,
                              ),
                              SizedBox(width: 4.w),
                              Text(
                                speaker.category!,
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
            SpeakerCardActions(speaker: speaker),
            SizedBox(height: 12.h),
            const Divider(),
            SizedBox(height: 8.h),
            Text("About", style: context.h3),
            SizedBox(height: 8.h),
            Text(
              speaker.designation.isNotEmpty
                  ? speaker.designation
                  : "No additional information available.",
              style: context.bodyRegular,
            ),
            Padding(
              padding: EdgeInsets.symmetric(vertical: 12.h),
              child: Row(
                spacing: 8.w,
                mainAxisSize: MainAxisSize.min,
                mainAxisAlignment: MainAxisAlignment.start,
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
