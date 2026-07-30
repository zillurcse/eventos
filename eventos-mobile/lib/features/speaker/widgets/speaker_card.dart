import 'package:expouse/features/speaker/pages/speaker_details.dart';
import 'package:expouse/features/speaker/widgets/speaker_card_actions.dart';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../models/speaker_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_image.dart';
import '../speaker_controller.dart';

class SpeakerCard extends StatefulWidget {
  final SpeakerItemModel speaker;

  const SpeakerCard({super.key, required this.speaker});

  @override
  State<SpeakerCard> createState() => _SpeakerCardState();
}

class _SpeakerCardState extends State<SpeakerCard> {
  @override
  Widget build(BuildContext context) {
    final speaker = widget.speaker;
    final hasImage = speaker.image != null && speaker.image!.isNotEmpty;
    final ctrl = Get.find<SpeakerController>();

    return Obx(() {
      final showDetail = ctrl.expandedSpeakerId.value == speaker.id;

      return GestureDetector(
        onTap: () {
          Get.to(() => SpeakerDetails(speaker: speaker));
        },
        child: Container(
          margin: EdgeInsets.fromLTRB(16.w, 0, 16.w, 8.h),
          padding: EdgeInsets.all(12.sp),
          decoration: BoxDecoration(
            color: context.tertiaryText,
            borderRadius: BorderRadius.circular(12.r),
            border: Border.all(
              color: showDetail ? context.primaryTheme : context.tertiaryText,
            ),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Row(
                children: [
                  // Avatar
                  hasImage
                      ? CustomImage(
                          speaker.image!,
                          height: 40.sp,
                          width: 40.sp,
                          radius: 8.r,
                        )
                      : Container(
                          height: 40.sp,
                          width: 40.sp,
                          decoration: BoxDecoration(
                            color: context.primaryFocused,
                            borderRadius: BorderRadius.circular(8.r),
                          ),
                          child: Center(
                            child: Text(
                              speaker.name.isNotEmpty
                                  ? speaker.name[0].toUpperCase()
                                  : '?',
                              style: context.titleLarge?.copyWith(
                                color: context.primaryTheme,
                              ),
                            ),
                          ),
                        ),
                  SizedBox(width: 8.w),
                  // Name + designation
                  Expanded(
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          speaker.name,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: context.titleLarge?.copyWith(
                            color: context.heading,
                          ),
                        ),
                        SizedBox(height: 4.h),
                        Text(
                          speaker.designation,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: context.specialCaption1?.copyWith(
                            color: context.caption,
                          ),
                        ),
                        if (speaker.presentationTitle != null &&
                            speaker.presentationTitle!.isNotEmpty) ...[
                          SizedBox(height: 2.h),
                          Text(
                            speaker.presentationTitle!,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: context.specialCaption1?.copyWith(
                              color: context.primaryTheme,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  SizedBox(width: 8.w),
                  // Expand/collapse toggle
                  GestureDetector(
                    onTap: () {
                      if (ctrl.expandedSpeakerId.value == speaker.id) {
                        ctrl.expandedSpeakerId.value = null;
                      } else {
                        ctrl.expandedSpeakerId.value = speaker.id;
                      }
                    },
                    child: Container(
                      height: 40.sp,
                      width: 40.sp,
                      padding: EdgeInsets.all(8.sp),
                      decoration: BoxDecoration(
                        color: showDetail
                            ? context.backgroundColor
                            : context.tertiaryText,
                        borderRadius: BorderRadius.circular(8.r),
                      ),
                      child: Icon(
                        showDetail ? Icons.close : Icons.more_horiz,
                        color: context.caption,
                      ),
                    ),
                  ),
                ],
              ),
              if (showDetail)
                SpeakerCardActions(
                  speaker: speaker,
                ),
            ],
          ),
        ),
      );
    });
  }
}
