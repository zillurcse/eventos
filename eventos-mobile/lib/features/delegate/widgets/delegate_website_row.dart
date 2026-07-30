import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../../widgets/custom_image.dart';

class DelegateWebsiteRow extends StatelessWidget {
  final String? website;
  final BuildContext context;
  const DelegateWebsiteRow({super.key, this.website, required this.context});

  Future<void> _open() async {
    if (website == null || website!.isEmpty) return;
    final uri = Uri.tryParse(
      website!.startsWith('http') ? website! : 'https://$website',
    );
    if (uri != null) await launchUrl(uri, mode: LaunchMode.externalApplication);
  }

  @override
  Widget build(BuildContext ctx) {
    if (website == null || website!.isEmpty) return const SizedBox.shrink();
    return GestureDetector(
      onTap: _open,
      child: CustomImage("assets/png/web.png", height: 40.sp, fit: BoxFit.fill),
    );
  }
}
