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
  final String? avatarUrl;
  final String senderName;

  const MessageBubble({
    super.key,
    required this.message,
    required this.isMe,
    this.avatarUrl,
    this.senderName = '',
  });

  @override
  Widget build(BuildContext context) {
    final hasBody = message.body != null && message.body!.isNotEmpty;
    final hasImage =
        message.attach != null && message.attachType == 'image';
    final hasVideo =
        message.attach != null && message.attachType == 'video';
    final hasPdf = message.attach != null && message.attachType == 'pdf';
    final isMediaBubble = hasImage || hasVideo;

    final bubbleRadius = BorderRadius.only(
      topLeft: const Radius.circular(12).r,
      topRight: const Radius.circular(12).r,
      bottomLeft: Radius.circular(isMe ? 12 : 0).r,
      bottomRight: Radius.circular(isMe ? 0 : 12).r,
    );

    final bubble = Flexible(
      child: Column(
        crossAxisAlignment:
            isMe ? CrossAxisAlignment.end : CrossAxisAlignment.start,
        children: [
          Container(
            padding: isMediaBubble ? EdgeInsets.zero : EdgeInsets.all(12.sp),
            decoration: BoxDecoration(
              color: isMe ? context.primaryFocused : context.tertiaryText,
              borderRadius: bubbleRadius,
            ),
            clipBehavior: Clip.antiAlias,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (hasImage)
                  GestureDetector(
                    onTap: () => _openImage(context, message.previewUrl!),
                    child: _buildImage(message.previewUrl!),
                  ),
                if (hasVideo)
                  MessageVideoThumbnail(
                      url: message.previewUrl!, isMe: isMe),
                if (hasPdf)
                  Padding(
                    padding: EdgeInsets.all(12.sp),
                    child: MessagePdfLink(
                        url: message.previewUrl!, isMe: isMe),
                  ),
                if (hasBody)
                  Padding(
                    padding: isMediaBubble
                        ? EdgeInsets.fromLTRB(12.sp, 8.sp, 12.sp, 12.sp)
                        : EdgeInsets.zero,
                    child: Text(message.body!, style: context.bodyLarge),
                  ),
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

    return Padding(
      padding: EdgeInsets.symmetric(vertical: 4.h, horizontal: 8.w),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        mainAxisAlignment:
            isMe ? MainAxisAlignment.end : MainAxisAlignment.start,
        children: [
          if (!isMe) ...[
            _SenderAvatar(url: avatarUrl, name: senderName),
            SizedBox(width: 8.w),
          ],
          bubble,
          if (isMe) ...[
            SizedBox(width: 8.w),
            _SenderAvatar(url: avatarUrl, name: senderName),
          ],
        ],
      ),
    );
  }

  Widget _buildImage(String src) {
    final isLocal = src.startsWith('/') || src.startsWith('file://');
    final width = 280.w;
    if (isLocal) {
      return Image.file(
        File(src),
        width: width,
        fit: BoxFit.fitWidth,
        errorBuilder: (context, err, stack) =>
            Icon(Icons.broken_image, size: 60.sp),
      );
    }
    return CustomImage(src, width: width, fit: BoxFit.fitWidth);
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

class _SenderAvatar extends StatelessWidget {
  final String? url;
  final String name;

  const _SenderAvatar({this.url, required this.name});

  @override
  Widget build(BuildContext context) {
    final initial = name.trim().isNotEmpty ? name.trim()[0].toUpperCase() : '?';
    final hasUrl = url != null && url!.isNotEmpty;

    return ClipRRect(
      borderRadius: BorderRadius.circular(16.r),
      child: SizedBox(
        width: 28.sp,
        height: 28.sp,
        child: hasUrl
            ? CustomImage(
                url!,
                width: 28.sp,
                height: 28.sp,
                fit: BoxFit.cover,
              )
            : ColoredBox(
                color: context.primaryTheme,
                child: Center(
                  child: Text(
                    initial,
                    style: TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w700,
                      fontSize: 12.sp,
                    ),
                  ),
                ),
              ),
      ),
    );
  }
}
