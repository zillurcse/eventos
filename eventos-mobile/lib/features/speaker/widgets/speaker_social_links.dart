import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../../../models/speaker_model.dart';
import '../../../../widgets/custom_image.dart';

class SpeakerSocialLinks extends StatelessWidget {
  final SpeakerDetailModel detail;
  const SpeakerSocialLinks({super.key, required this.detail});

  Future<void> _open(String? url) async {
    if (url == null || url.isEmpty) return;
    final uri = Uri.tryParse(
      url.startsWith('http') ? url : 'https://$url',
    );
    if (uri != null) await launchUrl(uri, mode: LaunchMode.externalApplication);
  }

  @override
  Widget build(BuildContext context) {
    final socials = <({String asset, String? url})>[
      if (detail.twitter != null && detail.twitter!.isNotEmpty)
        (asset: 'assets/png/twiter.png', url: detail.twitter),
      if (detail.instagram != null && detail.instagram!.isNotEmpty)
        (asset: 'assets/png/insta.png', url: detail.instagram),
      if (detail.linkedin != null && detail.linkedin!.isNotEmpty)
        (asset: 'assets/png/in.png', url: detail.linkedin),
      if (detail.facebook != null && detail.facebook!.isNotEmpty)
        (asset: 'assets/png/fb.png', url: detail.facebook),
    ];

    // If no social links filled in, show dimmed icons
    if (socials.isEmpty) {
      return SizedBox.shrink();
    }

    return Row(
      spacing: 8.w,
      children: socials
          .map(
            (s) => GestureDetector(
              onTap: () => _open(s.url),
              child: CustomImage(s.asset, height: 40.sp, fit: BoxFit.fill),
            ),
          )
          .toList(),
    );
  }
}
