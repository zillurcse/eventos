import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';

import '../../../models/contest_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_image.dart';
import '../contest_details_controller.dart';

class ContestEntryCard extends StatefulWidget {
  final ContestEntry entry;
  final Contest contest;

  const ContestEntryCard({
    super.key,
    required this.entry,
    required this.contest,
  });

  @override
  State<ContestEntryCard> createState() => _ContestEntryCardState();
}

class _ContestEntryCardState extends State<ContestEntryCard> {
  final _commentCtrl = TextEditingController();
  bool _commenting = false;

  ContestDetailsController get ctrl => Get.find<ContestDetailsController>();

  @override
  void dispose() {
    _commentCtrl.dispose();
    super.dispose();
  }

  String _timeAgo(String? iso) {
    if (iso == null || iso.isEmpty) return '';
    final dt = DateTime.tryParse(iso)?.toLocal();
    if (dt == null) return '';
    final diff = DateTime.now().difference(dt);
    if (diff.inMinutes < 1) return 'just now';
    if (diff.inMinutes < 60) return '${diff.inMinutes}m ago';
    if (diff.inHours < 24) return '${diff.inHours}h ago';
    if (diff.inDays < 7) return '${diff.inDays}d ago';
    return DateFormat('dd MMM').format(dt);
  }

  @override
  Widget build(BuildContext context) {
    final entry = widget.entry;
    final contest = widget.contest;
    final commentable = contest.isEntryType &&
        (contest.canSeeOtherComments || entry.isMine || contest.isOngoing);
    final likeable = !entry.isMine && !contest.isEnded;

    return Container(
      margin: EdgeInsets.only(bottom: 12.h),
      padding: EdgeInsets.fromLTRB(14.w, 14.h, 14.w, 12.h),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16.r),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 6,
            offset: const Offset(0, 1),
          ),
        ],
        border: entry.isWinner
            ? Border.all(color: const Color(0xFFF59E0B), width: 2)
            : null,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              ClipOval(
                child: SizedBox(
                  width: 40.sp,
                  height: 40.sp,
                  child: (entry.authorAvatar?.isNotEmpty ?? false)
                      ? CustomImage(
                          entry.authorAvatar!,
                          fit: BoxFit.cover,
                          width: 40.sp,
                          height: 40.sp,
                        )
                      : ColoredBox(
                          color: const Color(0xFFE2E8F0),
                          child: Icon(Icons.person, size: 22.sp, color: context.ghost),
                        ),
                ),
              ),
              SizedBox(width: 10.w),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Flexible(
                          child: Text(
                            entry.author,
                            style: context.bodyRegular?.copyWith(
                              fontWeight: FontWeight.w700,
                              color: const Color(0xFF1E293B),
                            ),
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                        if (entry.isMine) ...[
                          SizedBox(width: 6.w),
                          Container(
                            padding: EdgeInsets.symmetric(
                              horizontal: 7.w,
                              vertical: 1.h,
                            ),
                            decoration: BoxDecoration(
                              color: const Color(0xFFE2E8F0),
                              borderRadius: BorderRadius.circular(999),
                            ),
                            child: Text(
                              'You',
                              style: TextStyle(
                                fontSize: 10.sp,
                                fontWeight: FontWeight.w700,
                                color: const Color(0xFF64748B),
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),
                    Text(
                      [
                        entry.authorHeadline ?? 'Attendee',
                        if (entry.createdAt != null) _timeAgo(entry.createdAt),
                      ].where((s) => s.isNotEmpty).join(' · '),
                      style: context.bodyRegular?.copyWith(
                        color: const Color(0xFF94A3B8),
                        fontSize: 12.sp,
                      ),
                    ),
                  ],
                ),
              ),
              if (entry.isWinner)
                _badge(
                  entry.rank != null ? 'Winner #${entry.rank}' : 'Winner',
                  const Color(0xFFF59E0B),
                )
              else if (entry.status == 'pending')
                _badge('In review', const Color(0xFF64748B))
              else if (entry.status == 'rejected')
                _badge('Not accepted', const Color(0xFFEF4444)),
            ],
          ),
          if (entry.body?.isNotEmpty ?? false) ...[
            SizedBox(height: 12.h),
            Text(
              entry.body!,
              style: context.bodyRegular?.copyWith(
                color: const Color(0xFF334155),
                height: 1.4,
              ),
            ),
          ],
          if (entry.attachments.isNotEmpty) ...[
            SizedBox(height: 12.h),
            ...entry.attachments.map((a) {
              if (a.isVideo) {
                return Container(
                  margin: EdgeInsets.only(bottom: 8.h),
                  height: 180.h,
                  width: double.infinity,
                  decoration: BoxDecoration(
                    color: const Color(0xFF0F172A),
                    borderRadius: BorderRadius.circular(12.r),
                  ),
                  child: Center(
                    child: Icon(Icons.play_circle_outline,
                        color: Colors.white, size: 48.sp),
                  ),
                );
              }
              return Padding(
                padding: EdgeInsets.only(bottom: 8.h),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(12.r),
                  child: CustomImage(
                    a.url,
                    fit: BoxFit.cover,
                    width: double.infinity,
                    height: 180.h,
                  ),
                ),
              );
            }),
          ],
          SizedBox(height: 8.h),
          Row(
            children: [
              _action(
                icon: entry.liked ? Icons.favorite : Icons.favorite_border,
                label: '${entry.likeCount}',
                color: entry.liked ? const Color(0xFFE11D48) : context.ghost,
                onTap: likeable ? () => ctrl.toggleLike(entry) : null,
              ),
              if (commentable) ...[
                SizedBox(width: 16.w),
                _action(
                  icon: Icons.chat_bubble_outline,
                  label: '${entry.commentCount}',
                  color: context.ghost,
                  onTap: () => ctrl.toggleComments(entry),
                ),
              ],
              const Spacer(),
              if (entry.isMine && contest.isOngoing)
                TextButton(
                  onPressed: () => _confirmRemove(entry),
                  child: Text(
                    'Remove',
                    style: TextStyle(
                      color: const Color(0xFFEF4444),
                      fontSize: 13.sp,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
            ],
          ),
          Obx(() {
            if (!ctrl.expandedComments.contains(entry.id)) {
              return const SizedBox.shrink();
            }
            final thread = ctrl.comments[entry.id] ?? [];
            return Padding(
              padding: EdgeInsets.only(top: 10.h),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (thread.isEmpty)
                    Text(
                      'No comments yet.',
                      style: context.bodyRegular?.copyWith(
                        color: context.caption,
                        fontSize: 12.sp,
                      ),
                    ),
                  ...thread.map((cm) => Padding(
                        padding: EdgeInsets.only(bottom: 10.h),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            ClipOval(
                              child: SizedBox(
                                width: 28.sp,
                                height: 28.sp,
                                child: (cm.authorAvatar?.isNotEmpty ?? false)
                                    ? CustomImage(
                                        cm.authorAvatar!,
                                        fit: BoxFit.cover,
                                        width: 28.sp,
                                        height: 28.sp,
                                      )
                                    : ColoredBox(
                                        color: const Color(0xFFE2E8F0),
                                        child: Icon(Icons.person,
                                            size: 16.sp, color: context.ghost),
                                      ),
                              ),
                            ),
                            SizedBox(width: 8.w),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    cm.author,
                                    style: context.bodyRegular?.copyWith(
                                      fontWeight: FontWeight.w700,
                                      fontSize: 12.sp,
                                    ),
                                  ),
                                  Text(
                                    cm.body ?? '',
                                    style: context.bodyRegular?.copyWith(
                                      fontSize: 13.sp,
                                      color: const Color(0xFF334155),
                                    ),
                                  ),
                                  if (cm.createdAt != null)
                                    Text(
                                      _timeAgo(cm.createdAt),
                                      style: context.bodyRegular?.copyWith(
                                        color: context.caption,
                                        fontSize: 11.sp,
                                      ),
                                    ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      )),
                  if (contest.isOngoing)
                    Row(
                      children: [
                        Expanded(
                          child: TextField(
                            controller: _commentCtrl,
                            maxLength: contest.characterLimit,
                            decoration: InputDecoration(
                              hintText: 'Add a comment…',
                              counterText: '',
                              isDense: true,
                              filled: true,
                              fillColor: const Color(0xFFF8FAFC),
                              border: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(10.r),
                                borderSide: BorderSide.none,
                              ),
                            ),
                            onSubmitted: (_) => _postComment(entry),
                          ),
                        ),
                        SizedBox(width: 8.w),
                        TextButton(
                          onPressed:
                              _commenting ? null : () => _postComment(entry),
                          child: Text(_commenting ? '…' : 'Post'),
                        ),
                      ],
                    ),
                ],
              ),
            );
          }),
        ],
      ),
    );
  }

  Widget _badge(String text, Color color) {
    return Container(
      padding: EdgeInsets.symmetric(horizontal: 8.w, vertical: 3.h),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        text,
        style: TextStyle(
          color: color,
          fontSize: 11.sp,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }

  Widget _action({
    required IconData icon,
    required String label,
    required Color color,
    VoidCallback? onTap,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: Row(
        children: [
          Icon(icon, size: 18.sp, color: color),
          SizedBox(width: 4.w),
          Text(
            label,
            style: TextStyle(
              color: color,
              fontWeight: FontWeight.w600,
              fontSize: 13.sp,
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _postComment(ContestEntry entry) async {
    final text = _commentCtrl.text.trim();
    if (text.isEmpty || _commenting) return;
    setState(() => _commenting = true);
    await ctrl.addComment(entry, text);
    if (mounted) {
      _commentCtrl.clear();
      setState(() => _commenting = false);
    }
  }

  Future<void> _confirmRemove(ContestEntry entry) async {
    final ok = await Get.dialog<bool>(
      AlertDialog(
        title: const Text('Remove entry?'),
        content: const Text('Remove your entry from this contest?'),
        actions: [
          TextButton(onPressed: () => Get.back(result: false), child: const Text('Cancel')),
          TextButton(onPressed: () => Get.back(result: true), child: const Text('Remove')),
        ],
      ),
    );
    if (ok == true) await ctrl.removeEntry(entry);
  }
}
