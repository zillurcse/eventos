import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

import '../../../models/event_feed_model.dart';
import '../../../utils/extension/size_ext.dart';
import '../../../widgets/custom_image.dart';
import '../pages/feed_media_viewer_page.dart';

class PostImageContent extends StatelessWidget {
  final FeedPostModel post;
  const PostImageContent({super.key, required this.post});

  @override
  Widget build(BuildContext context) {
    // Near-square tile shows more of portrait photos than a short landscape crop.
    final side = context.width;

    return GestureDetector(
      onTap: () => openFeedMediaViewer(context, post, isVideo: false),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(8.r),
        child: CustomImage(
          post.attachUrl ?? '',
          width: side,
          radius: 0,
        ),
      ),
    );
  }
}
