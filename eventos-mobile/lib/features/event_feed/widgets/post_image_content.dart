import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

import '../../../models/event_feed_model.dart';
import '../../../utils/extension/size_ext.dart';
import '../../../widgets/custom_image.dart';

class PostImageContent extends StatelessWidget {
  final FeedPostModel post;
  const PostImageContent({super.key, required this.post});

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(8.r),
      child: CustomImage(
        post.attachUrl ?? '',
        width: context.width,
        height: context.height * .22,
        radius: 0,
        fit: BoxFit.cover,
      ),
    );
  }
}
