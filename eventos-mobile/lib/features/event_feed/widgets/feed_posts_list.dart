import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../event_feed_controller.dart';
import 'feed_post.dart';
import '../../../utils/enum/enums.dart';
import '../../../utils/extension/theme_ext.dart';

class FeedPostsList extends StatelessWidget {
  const FeedPostsList({super.key});

  @override
  Widget build(BuildContext context) {
    final ctrl = Get.find<EventFeedController>();
    return Obx(() {
      // Still loading first page - show skeletons inside the list area
      if (ctrl.feedStatus.value == ApiState.loading && ctrl.posts.isEmpty) {
        return const SliverToBoxAdapter(
          child: SizedBox.shrink(), // skeleton is handled by ApiStateHandler
        );
      }

      // Empty state
      if (ctrl.posts.isEmpty) {
        return SliverFillRemaining(
          hasScrollBody: false,
          child: Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  Icons.dynamic_feed_outlined,
                  size: 48.sp,
                  color: context.ghost,
                ),
                SizedBox(height: 12.h),
                Text(
                  'No posts yet',
                  style: context.bodyRegular?.copyWith(
                    color: context.caption,
                  ),
                ),
              ],
            ),
          ),
        );
      }

      // Posts list + load-more footer
      return SliverList(
        delegate: SliverChildBuilderDelegate(
          (context, index) {
            if (index == ctrl.posts.length) {
              return Obx(
                () => ctrl.isLoadingMore.value
                    ? Padding(
                        padding: EdgeInsets.symmetric(vertical: 16.h),
                        child: const Center(
                          child: CircularProgressIndicator(),
                        ),
                      )
                    : SizedBox(height: 16.h),
              );
            }
            return FeedPost(post: ctrl.posts[index]);
          },
          childCount: ctrl.posts.length + 1,
        ),
      );
    });
  }
}
