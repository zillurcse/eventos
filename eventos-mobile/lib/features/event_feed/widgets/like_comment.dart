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
  /// When true, hide the like control (Looking for / Offering use Interested).
  final bool hideLike;

  const LikeComment({super.key, required this.post, this.hideLike = false});

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
          if (!hideLike) ...[
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
          ],

          // ── Comment ────────────────────────────────────────────────────────────
          GestureDetector(
            onTap: () {
              HapticFeedback.lightImpact();
              ctrl.toggleComments(post.id);
            },
            child: Row(
              children: [
                CustomImage(
                  "assets/svg/icons/chat.svg",
                  color: post.commentOpen
                      ? context.primaryTheme
                      : context.ghost,
                ),
                SizedBox(width: 8.w),
                Text(
                  "${post.commentCount} ${post.commentCount == 1 ? 'Comment' : 'Comments'}",
                  style: context.titleRegular?.copyWith(
                    color: post.commentOpen
                        ? context.primaryTheme
                        : context.caption,
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
