import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:intl/intl.dart';

import '../../../models/event_feed_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_image.dart';

class PostHeader extends StatelessWidget {
  final FeedPostModel post;

  const PostHeader({super.key, required this.post});

  @override
  Widget build(BuildContext context) {
    final user = post.user;
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
                DateFormat("hh:mm a | dd MMM, yy").format(post.createdAtTime),
                style: context.specialCaption1?.copyWith(
                  color: context.caption,
                ),
              ),
            ],
          ),
        ),
        SizedBox(
          height: 24.h,
          width: 16.h,
          child: Icon(Icons.more_vert, size: 18.sp, color: context.ghost),
        ),
      ],
    );
  }
}
