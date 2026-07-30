import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

import '../../../models/event_feed_model.dart';
import '../../../utils/enum/enums.dart';
import '../../../utils/extension/theme_ext.dart';
import 'comment_card.dart';
import 'like_comment.dart';
import 'post_header.dart';
import 'post_image_content.dart';
import 'post_interest_card.dart';
import 'post_link_preview.dart';
import 'post_pdf_content.dart';
import 'post_poll_content.dart';
import 'post_video_content.dart';

class FeedPost extends StatelessWidget {
  final FeedPostModel post;
  const FeedPost({super.key, required this.post});

  PostTypes get _resolvedType {
    if (post.type == 'poll') return PostTypes.poll;
    if (post.type == 'looking-for' || post.type == 'looking_for') {
      return PostTypes.lookingFor;
    }
    if (post.type == 'offering') return PostTypes.offering;
    if (post.type == 'pdf' || post.attachType == 'pdf') return PostTypes.pdf;
    if (post.type == 'video' || post.attachType == 'video') {
      return PostTypes.video;
    }
    if (post.attachUrl != null ||
        post.attach != null ||
        post.attachType == 'image') {
      return PostTypes.image;
    }
    if (post.type == 'post' || post.type == 'text' || post.type == 'image') {
      return PostTypes.post;
    }
    return PostTypes.post;
  }

  String? get _firstLink {
    if (post.body == null || post.body!.isEmpty) return null;
    final match = RegExp(r'https?://[^\s]+', caseSensitive: false).firstMatch(post.body!);
    return match?.group(0);
  }

  Widget? _buildContent(PostTypes type) {
    switch (type) {
      case PostTypes.image:    return PostImageContent(post: post);
      case PostTypes.video:    return PostVideoContent(post: post);
      case PostTypes.poll:     return PostPollContent(post: post);
      case PostTypes.pdf:      return PostPdfContent(post: post);
      case PostTypes.lookingFor: return PostInterestCard(post: post, isLookingFor: true);
      case PostTypes.offering:   return PostInterestCard(post: post, isLookingFor: false);
      case PostTypes.post:
        final link = _firstLink;
        return link != null ? PostLinkPreview(url: link, post: post) : null;
    }
  }

  @override
  Widget build(BuildContext context) {
    final type = _resolvedType;
    final content = _buildContent(type);

    return Container(
      margin: EdgeInsets.fromLTRB(16.w, 0, 16.w, 12.h),
      padding: EdgeInsets.fromLTRB(16.w, 12.h, 16.w, 12.h),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(8.r),
        color: context.tertiaryText,
        border: Border.all(color: context.strokeLight),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          PostHeader(post: post),
          if (post.body != null && post.body!.isNotEmpty && type != PostTypes.lookingFor && type != PostTypes.offering)
            Padding(
              padding: EdgeInsets.only(top: 10.h),
              child: Text(post.body!, style: context.bodyRegular),
            ),
          if (type == PostTypes.poll && post.question != null)
            Padding(
              padding: EdgeInsets.only(top: 6.h),
              child: Text(post.question!,
                  style: context.bodyRegular),
            ),
          if (content != null)
            Padding(
              padding: EdgeInsets.only(top: 12.h),
              child: content,
            ),
          LikeComment(post: post),
          CommentCard(post: post),
        ],
      ),
    );
  }
}
