import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../models/event_feed_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_image.dart';
import '../event_feed_controller.dart';

class LikeComment extends StatelessWidget {
  final FeedPostModel post;

  const LikeComment({super.key, required this.post});

  @override
  Widget build(BuildContext context) {
    final ctrl = Get.find<EventFeedController>();

    return Obx(() {
      final post =
          ctrl.posts.firstWhereOrNull((p) => p.id == this.post.id) ?? this.post;

      return Padding(
      padding: EdgeInsets.only(top: 12.h),
      child: Row(
        children: [
          // ── Like ───────────────────────────────────────────────────────────────
          GestureDetector(
            onTap: () {
              HapticFeedback.lightImpact();
              ctrl.toggleLike(post.id);
            },
            child: Row(
              children: [
                AnimatedSwitcher(
                  duration: const Duration(milliseconds: 200),
                  transitionBuilder: (child, animation) => ScaleTransition(
                    scale: animation,
                    child: child,
                  ),
                  child: CustomImage(
                    "assets/svg/icons/love.svg",
                    key: ValueKey(post.isLiked),
                    color: post.isLiked ? Colors.red : context.ghost,
                  ),
                ),
                SizedBox(width: 8.w),
                AnimatedSwitcher(
                  duration: const Duration(milliseconds: 200),
                  child: Text(
                    "${post.like} ${post.like == 1 ? 'Like' : 'Likes'}",
                    key: ValueKey(post.like),
                    style: context.titleRegular?.copyWith(
                      color: post.isLiked ? Colors.red : context.caption,
                    ),
                  ),
                ),
              ],
            ),
          ),

          SizedBox(width: 24.w),

          // ── Comment ────────────────────────────────────────────────────────────
          GestureDetector(
            onTap: () {
              // Comment input is already visible in CommentCard below
            },
            child: Row(
              children: [
                CustomImage(
                  "assets/svg/icons/chat.svg",
                  color: context.primaryTheme,
                ),
                SizedBox(width: 8.w),
                Text(
                  "${post.comments.length} ${post.comments.length == 1 ? 'Comment' : 'Comments'}",
                  style: context.titleRegular?.copyWith(
                    color: context.caption,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
    });
  }
}