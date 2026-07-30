import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:url_launcher/url_launcher.dart';
import 'custom_image.dart';

class SharedSocialLinksSection extends StatelessWidget {
  final String? facebook;
  final String? instagram;
  final String? linkedin;
  final String? twitter;
  final String? whatsapp;
  final String? website;

  const SharedSocialLinksSection({
    super.key,
    this.facebook,
    this.instagram,
    this.linkedin,
    this.twitter,
    this.whatsapp,
    this.website,
  });

  Future<void> _open(String url) async {
    if (url.isEmpty) return;
    final uri = Uri.tryParse(
      url.startsWith('http') ? url : 'https://$url',
    );
    if (uri != null) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  Widget _buildIcon(String asset, String url) {
    return GestureDetector(
      onTap: () => _open(url),
      child: CustomImage(
        asset,
        height: 40.sp,
        fit: BoxFit.fill,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final activeSocials = <Widget>[
      if (twitter != null && twitter!.isNotEmpty)
        _buildIcon('assets/png/twiter.png', twitter!),
      if (instagram != null && instagram!.isNotEmpty)
        _buildIcon('assets/png/insta.png', instagram!),
      if (linkedin != null && linkedin!.isNotEmpty)
        _buildIcon('assets/png/in.png', linkedin!),
      if (facebook != null && facebook!.isNotEmpty)
        _buildIcon('assets/png/fb.png', facebook!),
      if (whatsapp != null && whatsapp!.isNotEmpty)
        _buildIcon('assets/png/wa.png', whatsapp!),
      if (website != null && website!.isNotEmpty)
        _buildIcon('assets/png/web.png', website!),
    ];

    if (activeSocials.isEmpty) {
      return const SizedBox.shrink();
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Divider(),
        SizedBox(height: 12.h),
        Wrap(
          spacing: 12.w,
          runSpacing: 10.h,
          children: activeSocials,
        ),
        SizedBox(height: 24.h),
      ],
    );
  }
}
