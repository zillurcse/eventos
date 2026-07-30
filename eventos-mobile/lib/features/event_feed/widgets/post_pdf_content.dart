import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../models/event_feed_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_image.dart';

class PostPdfContent extends StatelessWidget {
  final FeedPostModel post;
  const PostPdfContent({super.key, required this.post});

  String get _fileName {
    final url = post.attach ?? '';
    final seg = Uri.tryParse(url)?.pathSegments;
    return (seg != null && seg.isNotEmpty && seg.last.isNotEmpty)
        ? seg.last
        : 'Document.pdf';
  }

  Future<void> _launch() async {
    final url = post.attach ?? '';
    if (url.isEmpty) return;
    final uri = Uri.tryParse(url);
    if (uri != null && await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: _launch,
      borderRadius: BorderRadius.circular(8.r),
      child: Container(
        padding: EdgeInsets.all(10.sp),
        decoration: BoxDecoration(
          color: context.tertiaryText,
          borderRadius: BorderRadius.circular(8.r),
          border: Border.all(color: context.strokeLight),
        ),
        child: Row(
          children: [
            CustomImage('assets/svg/icons/pdf.svg', height: 32.sp, width: 32.sp),
            SizedBox(width: 10.w),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(_fileName,
                    style: context.titleRegular?.copyWith(color: context.heading),
                    maxLines: 1, overflow: TextOverflow.ellipsis),
                  SizedBox(height: 2.h),
                  Text('PDF File',
                    style: context.specialLabelCapital?.copyWith(color: context.ghost)),
                ],
              ),
            ),
            SizedBox(width: 8.w),
            CustomImage('assets/svg/icons/file.svg', height: 24.sp, width: 24.sp),
          ],
        ),
      ),
    );
  }
}
