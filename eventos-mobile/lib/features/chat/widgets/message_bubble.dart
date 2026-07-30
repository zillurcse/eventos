import 'dart:io';
import 'package:expouse/utils/extension/theme_ext.dart';
import 'package:expouse/widgets/custom_image.dart';
import 'package:expouse/widgets/image_viewer_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:intl/intl.dart';
import '../../../models/message_model.dart';
import 'message_video_thumbnail.dart';
import 'message_pdf_link.dart';

class MessageBubble extends StatelessWidget {
  final MessageModel message;
  final bool isMe;

  const MessageBubble({super.key, required this.message, required this.isMe});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4.0, horizontal: 8.0),
      child: Column(
        crossAxisAlignment: isMe ? CrossAxisAlignment.end : CrossAxisAlignment.start,
        children: [
          Container(
            padding: EdgeInsets.all(12.sp),
            decoration: BoxDecoration(
              color: isMe ? context.primaryFocused : context.tertiaryText,
              borderRadius: BorderRadius.only(
                topLeft: const Radius.circular(12).r,
                topRight: const Radius.circular(12).r,
                bottomLeft: Radius.circular(isMe ? 12 : 0).r,
                bottomRight: Radius.circular(isMe ? 0 : 12).r,
              ),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (message.attach != null && message.attachType == 'image')
                  Padding(
                    padding: const EdgeInsets.only(bottom: 8.0),
                    child: GestureDetector(
                      onTap: () => _openImage(context, message.previewUrl!),
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(8),
                        child: _buildImage(message.previewUrl!),
                      ),
                    ),
                  ),
                if (message.attach != null && message.attachType == 'video')
                  Padding(
                    padding: const EdgeInsets.only(bottom: 8.0),
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(8),
                      child: MessageVideoThumbnail(url: message.previewUrl!, isMe: isMe),
                    ),
                  ),
                if (message.attach != null && message.attachType == 'pdf')
                  MessagePdfLink(url: message.previewUrl!, isMe: isMe),
                if (message.body != null && message.body!.isNotEmpty)
                  Text(message.body!, style: context.bodyLarge),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.only(top: 4.0),
            child: Text(
              _formatDate(message.createdAt),
              style: context.specialCaption1?.copyWith(color: context.caption),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildImage(String src) {
    final isLocal = src.startsWith('/') || src.startsWith('file://');
    if (isLocal) {
      return SizedBox(
        height: 180.h,
        width: 280.w,
        child: Image.file(
          File(src),
          fit: BoxFit.cover,
          errorBuilder: (context, err, stack) => Icon(Icons.broken_image, size: 60.sp),
        ),
      );
    }
    return CustomImage(src, height: 180.h, width: 280.w, fit: BoxFit.cover);
  }

  void _openImage(BuildContext context, String src) {
    final isLocal = src.startsWith('/') || src.startsWith('file://');
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => isLocal
            ? ImageViewerPage(filePath: src)
            : ImageViewerPage(url: src),
      ),
    );
  }

  String _formatDate(String dateStr) {
    if (dateStr.isEmpty) return '';
    try {
      return DateFormat('HH:mm aa').format(DateTime.parse(dateStr).toLocal());
    } catch (_) {
      return dateStr;
    }
  }
}
