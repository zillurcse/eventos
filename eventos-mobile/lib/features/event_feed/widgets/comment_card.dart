import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../models/event_feed_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_image.dart';
import '../event_feed_controller.dart';
import 'comment_row.dart';

class CommentCard extends StatefulWidget {
  final FeedPostModel post;
  const CommentCard({super.key, required this.post});

  @override
  State<CommentCard> createState() => _CommentCardState();
}

class _CommentCardState extends State<CommentCard> {
  final TextEditingController _controller = TextEditingController();
  int _remaining = 200;
  bool _isSending = false;

  @override
  void initState() {
    super.initState();
    _controller.addListener(() {
      setState(() => _remaining = 200 - _controller.text.length);
    });
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _sendComment() async {
    final body = _controller.text.trim();
    if (body.isEmpty || _isSending) return;

    HapticFeedback.lightImpact();
    setState(() => _isSending = true);

    final ctrl = Get.find<EventFeedController>();
    final success = await ctrl.storeComment(
      postId: widget.post.id,
      body: body,
    );

    if (success) {
      _controller.clear();
    }

    if (mounted) setState(() => _isSending = false);
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        ...widget.post.comments.take(3).map((c) => CommentRow(comment: c)),
        Padding(
          padding: EdgeInsets.only(top: 10.h),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              CustomImage(
                widget.post.user.profilePhotoUrl,
                fit: BoxFit.cover,
                height: 40.sp,
                width: 40.sp,
                radius: 8.r,
              ),
              SizedBox(width: 8.w),
              Expanded(
                child: Container(
                  height: 40.sp,
                  constraints: BoxConstraints(minHeight: 38.h),
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(8.r),
                    color: context.backgroundColor,
                    border: Border.all(color: context.strokeLight),
                  ),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.center,
                    children: [
                      SizedBox(width: 14.w),
                      Expanded(
                        child: TextField(
                          controller: _controller,
                          maxLength: 200,
                          maxLines: null,
                          textInputAction: TextInputAction.send,
                          onSubmitted: (_) => _sendComment(),
                          decoration: InputDecoration(
                            border: InputBorder.none,
                            isDense: true,
                            counterText: '',
                            contentPadding: EdgeInsets.symmetric(vertical: 9.h),
                            hintText: 'Write a comment...',
                            hintStyle: context.specialCaption2?.copyWith(color: context.ghost),
                          ),
                          style: context.specialCaption1?.copyWith(color: context.heading),
                        ),
                      ),
                      Text('$_remaining',
                          style: context.specialCaption2?.copyWith(color: context.ghost)),
                      SizedBox(width: 6.w),
                      GestureDetector(
                        onTap: _sendComment,
                        child: Padding(
                          padding: EdgeInsets.only(right: 10.w),
                          child: AnimatedSwitcher(
                            duration: const Duration(milliseconds: 200),
                            child: _isSending
                                ? SizedBox(
                                    key: const ValueKey('loading'),
                                    height: 20.sp,
                                    width: 20.sp,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                      color: context.primaryTheme,
                                    ),
                                  )
                                : CustomImage(
                                    key: const ValueKey('send'),
                                    'assets/svg/icons/send.svg',
                                    height: 20.sp,
                                    width: 20.sp,
                                    color: _remaining < 200
                                        ? context.primaryTheme
                                        : context.ghost,
                                  ),
                          ),
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
    );
  }
}
