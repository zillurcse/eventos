import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../models/event_feed_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_image.dart';
import '../event_feed_controller.dart';
import '../../root/root_controller.dart';
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
  int? _replyToId;
  String? _replyToName;

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

  void _startReply(FeedCommentModel comment) {
    setState(() {
      _replyToId = comment.id;
      _replyToName = comment.user.name;
    });
    final ctrl = Get.find<EventFeedController>();
    final live =
        ctrl.posts.firstWhereOrNull((p) => p.id == widget.post.id) ?? widget.post;
    if (!live.commentOpen) {
      ctrl.toggleComments(widget.post.id);
    }
  }

  void _clearReply() {
    setState(() {
      _replyToId = null;
      _replyToName = null;
    });
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
      parentId: _replyToId,
    );

    if (success) {
      _controller.clear();
      _clearReply();
    }

    if (mounted) setState(() => _isSending = false);
  }

  @override
  Widget build(BuildContext context) {
    final ctrl = Get.find<EventFeedController>();

    return Obx(() {
      final post = ctrl.posts.firstWhereOrNull((p) => p.id == widget.post.id) ??
          widget.post;

      if (!post.commentOpen) return const SizedBox.shrink();

      final roots = post.comments.where((c) => c.parentId == null).toList();
      final repliesByParent = <int, List<FeedCommentModel>>{};
      for (final c in post.comments.where((c) => c.parentId != null)) {
        repliesByParent.putIfAbsent(c.parentId!, () => []).add(c);
      }

      return Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          ...roots.map((c) => Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  CommentRow(
                    comment: c,
                    onReply: () => _startReply(c),
                  ),
                  ...(repliesByParent[c.id] ?? const []).map(
                    (r) => Padding(
                      padding: EdgeInsets.only(left: 28.w),
                      child: CommentRow(comment: r),
                    ),
                  ),
                ],
              )),
          if (_replyToName != null)
            Padding(
              padding: EdgeInsets.only(top: 8.h),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      'Replying to $_replyToName',
                      style: context.specialCaption2?.copyWith(
                        color: context.primaryTheme,
                      ),
                    ),
                  ),
                  GestureDetector(
                    onTap: _clearReply,
                    child: Icon(Icons.close, size: 16.sp, color: context.ghost),
                  ),
                ],
              ),
            ),
          Padding(
            padding: EdgeInsets.only(top: 10.h),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                CustomImage(
                  Get.find<RootController>().profilePhotoUrl.value,
                  fit: BoxFit.cover,
                  height: 40.sp,
                  width: 40.sp,
                  radius: 8.r,
                  avatar: true,
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
                              contentPadding:
                                  EdgeInsets.symmetric(vertical: 9.h),
                              hintText: _replyToName != null
                                  ? 'Write a reply...'
                                  : 'Write a comment...',
                              hintStyle: context.specialCaption2
                                  ?.copyWith(color: context.ghost),
                            ),
                            style: context.specialCaption1
                                ?.copyWith(color: context.heading),
                          ),
                        ),
                        Text('$_remaining',
                            style: context.specialCaption2
                                ?.copyWith(color: context.ghost)),
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
    });
  }
}
