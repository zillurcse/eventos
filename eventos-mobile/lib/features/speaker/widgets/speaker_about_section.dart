import 'package:expouse/utils/extension/string_ext.dart';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../../../models/speaker_model.dart';
import '../../../utils/extension/theme_ext.dart';

class SpeakerAboutSection extends StatelessWidget {
  final SpeakerDetailModel detail;

  const SpeakerAboutSection({super.key, required this.detail});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('About', style: context.h3),
        SizedBox(height: 8.h),
        Text(
          (detail.bio != null && detail.bio!.isNotEmpty)
              ? detail.bio!.htmlToPlainText()
              : 'No additional information available.',
          style: context.bodyRegular?.copyWith(color: context.caption),
        ),
      ],
    );
  }
}
