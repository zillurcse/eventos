import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:youtube_player_flutter/youtube_player_flutter.dart';

import '../../../models/event_feed_model.dart';
import '../../../utils/extension/size_ext.dart';
import '../../../widgets/cards/video_card.dart';
import '../../../widgets/custom_image.dart';
import '../pages/feed_media_viewer_page.dart';

/// Video content for a feed post.
///
/// Shows a thumbnail with play button; tap opens the Facebook-style
/// full-screen media viewer with playback controls.
class PostVideoContent extends StatelessWidget {
  final FeedPostModel post;
  const PostVideoContent({super.key, required this.post});

  String? _ytThumbnail(String url) {
    final id = YoutubePlayer.convertUrlToId(url);
    if (id == null) return null;
    return 'https://img.youtube.com/vi/$id/maxresdefault.jpg';
  }

  @override
  Widget build(BuildContext context) {
    final url = post.attachUrl ?? '';
    if (url.isEmpty) return const SizedBox.shrink();

    final isYt = isYoutubeUrl(url);
    final thumbUrl = isYt
        ? _ytThumbnail(url)
        : (post.attachPoster?.isNotEmpty == true ? post.attachPoster : null);
    final w = context.width;
    final h = context.height * .22;

    return GestureDetector(
      onTap: () => openFeedMediaViewer(context, post, isVideo: true),
      child: Stack(
        alignment: Alignment.center,
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(8.r),
            child: thumbUrl != null
                ? CustomImage(
                    thumbUrl,
                    width: w,
                    height: h,
                    fit: BoxFit.cover,
                  )
                : Container(
                    width: w,
                    height: h,
                    color: Colors.black87,
                    child: Icon(
                      Icons.video_file_rounded,
                      color: Colors.white24,
                      size: 48.sp,
                    ),
                  ),
          ),
          ClipRRect(
            borderRadius: BorderRadius.circular(8.r),
            child: Container(
              width: w,
              height: h,
              color: Colors.black.withValues(alpha: 0.25),
            ),
          ),
          Container(
            width: 56.sp,
            height: 56.sp,
            decoration: const BoxDecoration(
              shape: BoxShape.circle,
              color: Colors.black54,
            ),
            child: Icon(
              Icons.play_arrow_rounded,
              color: Colors.white,
              size: 32.sp,
            ),
          ),
        ],
      ),
    );
  }
}
