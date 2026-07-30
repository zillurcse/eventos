import 'package:expouse/utils/extension/theme_ext.dart';
import 'package:expouse/widgets/custom_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:url_launcher/url_launcher.dart';

class MessagePdfLink extends StatelessWidget {
  final String url;
  final bool isMe;

  const MessagePdfLink({super.key, required this.url, required this.isMe});

  Future<void> _launch() async {
    if (url.startsWith('/') || url.startsWith('file://')) return;
    final uri = Uri.parse(url);
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  String _fileName(String url) {
    final segments = Uri.tryParse(url)?.pathSegments;
    if (segments != null && segments.isNotEmpty && segments.last.isNotEmpty) {
      return segments.last;
    }
    return 'Document.pdf';
  }

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: _launch,
      borderRadius: BorderRadius.circular(8),
      child: Container(
        width: 240.w,
        padding: EdgeInsets.all(8.sp),
        decoration: BoxDecoration(
          color: context.tertiaryText,
          borderRadius: BorderRadius.circular(8).r,
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            CustomImage('assets/svg/icons/pdf.svg', height: 28.sp),
            const SizedBox(width: 8),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    _fileName(url),
                    style: context.titleRegular,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 2),
                  Text(
                    'PDF File',
                    style: context.specialLabelCapital?.copyWith(color: context.ghost),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 6),
            CustomImage('assets/svg/icons/file.svg', height: 24.sp),
          ],
        ),
      ),
    );
  }
}
