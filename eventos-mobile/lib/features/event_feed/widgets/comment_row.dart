import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:intl/intl.dart';

import '../../../models/event_feed_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../utils/helpers/text_direction_helper.dart';
import '../../../widgets/custom_image.dart';

class CommentRow extends StatelessWidget {
  final FeedCommentModel comment;
  final VoidCallback? onReply;

  const CommentRow({super.key, required this.comment, this.onReply});

  @override
  Widget build(BuildContext context) {
    final bodyDir = TextDirectionHelper.directionOf(comment.body);
    final bodyAlign = TextDirectionHelper.alignOf(comment.body);

    return Padding(
      padding: EdgeInsets.only(top: 10.h),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          CustomImage(
            comment.user.profilePhotoUrl,
            fit: BoxFit.cover,
            height: 34.sp,
            width: 34.sp,
            isCircle: true,
            avatar: true,
          ),
          SizedBox(width: 8.w),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  padding: EdgeInsets.symmetric(horizontal: 12.w, vertical: 8.h),
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.only(
                      topRight: Radius.circular(16.r),
                      bottomLeft: Radius.circular(16.r),
                      bottomRight: Radius.circular(16.r),
                    ),
                    color: context.backgroundColor,
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        comment.user.name,
                        style: context.specialCaption1?.copyWith(
                          color: context.heading,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      Text(
                        DateFormat("hh:mm a dd MMM, yy")
                            .format(comment.diff.toLocal()),
                        style: context.specialCaption2
                            ?.copyWith(color: context.ghost),
                      ),
                      SizedBox(height: 3.h),
                      Text(
                        comment.body,
                        textDirection: bodyDir,
                        textAlign: bodyAlign,
                        style: context.specialCaption1
                            ?.copyWith(color: context.body),
                      ),
                    ],
                  ),
                ),
                if (onReply != null)
                  Padding(
                    padding: EdgeInsets.only(top: 4.h, left: 4.w),
                    child: GestureDetector(
                      onTap: onReply,
                      behavior: HitTestBehavior.opaque,
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            Icons.reply_rounded,
                            size: 16.sp,
                            color: context.caption,
                          ),
                          SizedBox(width: 4.w),
                          Text(
                            'Reply',
                            style: context.specialCaption2?.copyWith(
                              color: context.caption,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
