import 'package:flutter/material.dart';

import '../../../models/event_feed_model.dart';
import '../../../widgets/cards/video_card.dart';

/// Video content for a feed post.
///
/// Delegates entirely to [VideoCard.box] which handles:
///   • YouTube URLs  → YouTube thumbnail + YoutubeVideoCard modal
///   • Regular URLs  → dark placeholder  + NormalVideoModal player
class PostVideoContent extends StatelessWidget {
  final FeedPostModel post;
  const PostVideoContent({super.key, required this.post});

  @override
  Widget build(BuildContext context) {
    return VideoCard.box(videoUrl: post.attachUrl);
  }
}
