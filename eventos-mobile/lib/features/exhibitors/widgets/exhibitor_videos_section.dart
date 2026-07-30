import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../../../models/exhibitor_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/cards/video_card.dart';

class ExhibitorVideosSection extends StatelessWidget {
  final ExhibitorModel exhibitor;

  const ExhibitorVideosSection({super.key, required this.exhibitor});

  @override
  Widget build(BuildContext context) {
    // Extract actual URLs from exhibitor.videos or exhibitor.videosData
    final List<String> videoUrls = [];

    for (var item in exhibitor.videos) {
      final url = _parseVideoUrl(item);
      if (url != null && url.isNotEmpty) {
        videoUrls.add(url);
      }
    }

    if (videoUrls.isEmpty) {
      for (var item in exhibitor.videosData) {
        final url = _parseVideoUrl(item);
        if (url != null && url.isNotEmpty) {
          videoUrls.add(url);
        }
      }
    }

    // Default mock fallback if no videos are found
    if (videoUrls.isEmpty) {
      videoUrls.add('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Videos (${videoUrls.length})',
          style: context.h2?.copyWith(color: context.heading),
        ),
        SizedBox(height: 16.h),
        ...videoUrls.map((url) => Padding(
              padding: EdgeInsets.only(bottom: 12.h),
              child: VideoCard.box(videoUrl: url),
            )),
      ],
    );
  }

  String? _parseVideoUrl(dynamic item) {
    if (item == null) return null;
    if (item is String) return item;
    if (item is Map) {
      return item['video_url']?.toString() ??
          item['url']?.toString() ??
          item['link']?.toString() ??
          item['youtube_video_url']?.toString() ??
          item['youtube_url']?.toString();
    }
    return null;
  }
}
