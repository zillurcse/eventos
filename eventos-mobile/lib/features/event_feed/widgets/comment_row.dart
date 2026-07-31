import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:intl/intl.dart';

import '../../../models/event_feed_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_image.dart';

class CommentRow extends StatefulWidget {
  final FeedCommentModel comment;
  const CommentRow({super.key, required this.comment});

  @override
  State<CommentRow> createState() => _CommentRowState();
}

class _CommentRowState extends State<CommentRow> {

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(top: 10.h),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          CustomImage(
            widget.comment.user.profilePhotoUrl,
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
                        widget.comment.user.name,
                        style: context.specialCaption1?.copyWith(
                          color: context.heading,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      SizedBox(height: 3.h),
                      Text(
                        widget.comment.body,
                        style: context.specialCaption1?.copyWith(color: context.body),
                      ),
                    ],
                  ),
                ),
                Padding(
                  padding: EdgeInsets.only(top: 4.h, left: 4.w),
                  child: Text(
                    DateFormat("hh:mm a dd MMM, yy").format(widget.comment.diff),
                    style: context.specialCaption2?.copyWith(color: context.ghost),
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
