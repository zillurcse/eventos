import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../../../models/speaker_model.dart';
import '../../../utils/extension/theme_ext.dart';

class SpeakerDetailsTags extends StatelessWidget {
  final SpeakerDetailModel detail;

  const SpeakerDetailsTags({super.key, required this.detail});

  @override
  Widget build(BuildContext context) {
    if (detail.tags.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(height: 8.h),
        Wrap(
          spacing: 8.w,
          runSpacing: 6.h,
          children: detail.tags
              .map(
                (tag) => Container(
                  padding: EdgeInsets.symmetric(
                    horizontal: 10.w,
                    vertical: 4.h,
                  ),
                  decoration: BoxDecoration(
                    color: context.primaryFocused,
                    borderRadius: BorderRadius.circular(20.r),
                  ),
                  child: Text(
                    tag,
                    style: context.specialCaption2?.copyWith(
                      color: context.primaryTheme,
                    ),
                  ),
                ),
              )
              .toList(),
        ),
        SizedBox(height: 12.h),
      ],
    );
  }
}
