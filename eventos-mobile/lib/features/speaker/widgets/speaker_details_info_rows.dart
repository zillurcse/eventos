import 'package:flutter/material.dart';
import '../../../models/speaker_model.dart';
import 'speaker_info_row.dart';

class SpeakerDetailsInfoRows extends StatelessWidget {
  final SpeakerDetailModel detail;

  const SpeakerDetailsInfoRows({super.key, required this.detail});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (detail.email != null && detail.email!.isNotEmpty)
          SpeakerInfoRow(
            icon: Icons.email_outlined,
            label: detail.email!,
            context: context,
          ),
        if (detail.presentationTitle != null && detail.presentationTitle!.isNotEmpty)
          SpeakerInfoRow(
            icon: Icons.slideshow_outlined,
            label: detail.presentationTitle!,
            context: context,
          ),
      ],
    );
  }
}
