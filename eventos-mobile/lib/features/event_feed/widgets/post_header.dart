import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';

import '../../../models/event_feed_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_image.dart';
import '../event_feed_controller.dart';
import 'report_post_dialog.dart';

class PostHeader extends StatelessWidget {
  final FeedPostModel post;

  const PostHeader({super.key, required this.post});

  @override
  Widget build(BuildContext context) {
    final user = post.user;
    final feed = Get.find<EventFeedController>();

    return Row(
      children: [
        CustomImage(
          user.profilePhotoUrl,
          fit: BoxFit.cover,
          height: 40.sp,
          width: 40.sp,
          radius: 8.r,
          avatar: true,
        ),
        SizedBox(width: 12.w),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                user.name,
                style: context.h2?.copyWith(color: context.heading),
              ),
              SizedBox(height: 4.h),
              Text(
                DateFormat("hh:mm a | dd MMM, yy")
                    .format(post.createdAtTime.toLocal()),
                style: context.specialCaption1?.copyWith(
                  color: context.caption,
                ),
              ),
            ],
          ),
        ),
        Obx(() {
          // Touch the RxSet so Obx rebuilds after a successful report.
          final reportedSession = feed.reportedPostUuids.contains(post.uuid);
          final live = feed.posts.firstWhereOrNull((p) => p.id == post.id) ?? post;
          if (live.isMine ||
              live.status != 'published' ||
              live.uuid.isEmpty ||
              live.reportedByMe ||
              reportedSession) {
            return SizedBox(height: 24.h, width: 16.h);
          }
          return PopupMenuButton<String>(
            padding: EdgeInsets.zero,
            offset: Offset(0, 28.h),
            color: context.backgroundColor,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(4.r),
            ),
            onSelected: (value) {
              if (value == 'report') {
                showReportPostDialog(context: context, postId: post.id);
              }
            },
            itemBuilder: (_) => [
              PopupMenuItem<String>(
                value: 'report',
                height: 40.h,
                child: Row(
                  children: [
                    Icon(Icons.edit_note, size: 18.sp, color: context.heading),
                    SizedBox(width: 8.w),
                    Text(
                      'Report',
                      style: context.bodyRegular?.copyWith(
                        color: context.heading,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
            ],
            child: SizedBox(
              height: 24.h,
              width: 24.h,
              child: Icon(Icons.more_vert, size: 18.sp, color: context.ghost),
            ),
          );
        }),
      ],
    );
  }
}
