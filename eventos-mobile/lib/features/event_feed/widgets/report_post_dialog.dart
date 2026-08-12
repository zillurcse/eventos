import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../utils/extension/theme_ext.dart';
import '../event_feed_controller.dart';

const _reasons = <({String value, String label})>[
  (value: 'inappropriate', label: "It's inappropriate for this event community"),
  (value: 'irrelevant', label: "It's irrelevant to this community"),
  (value: 'spam', label: "It's spam"),
];

/// Dialog matching the report-post design: title, radio reasons, SEND.
Future<void> showReportPostDialog({
  required BuildContext context,
  required int postId,
}) {
  return showDialog<void>(
    context: context,
    barrierColor: Colors.black54,
    builder: (_) => _ReportPostDialog(postId: postId),
  );
}

class _ReportPostDialog extends StatefulWidget {
  final int postId;

  const _ReportPostDialog({required this.postId});

  @override
  State<_ReportPostDialog> createState() => _ReportPostDialogState();
}

class _ReportPostDialogState extends State<_ReportPostDialog> {
  String? _selected;
  bool _sending = false;

  Future<void> _submit() async {
    if (_selected == null || _sending) return;
    setState(() => _sending = true);
    final ok = await Get.find<EventFeedController>().reportPost(
      widget.postId,
      _selected!,
    );
    if (!mounted) return;
    if (ok) {
      Navigator.of(context).pop();
    } else {
      setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: context.backgroundColor,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14.r)),
      insetPadding: EdgeInsets.symmetric(horizontal: 28.w),
      child: Padding(
        padding: EdgeInsets.fromLTRB(0, 4.h, 0, 16.h),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Padding(
              padding: EdgeInsets.fromLTRB(16.w, 12.h, 8.w, 12.h),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Text(
                      "Tell us, what's wrong with this post?",
                      style: context.h2?.copyWith(
                        color: context.heading,
                        fontWeight: FontWeight.w600,
                        height: 1.35,
                      ),
                    ),
                  ),
                  SizedBox(width: 8.w),
                  Material(
                    color: context.redError,
                    shape: const CircleBorder(),
                    child: InkWell(
                      customBorder: const CircleBorder(),
                      onTap: () => Navigator.of(context).pop(),
                      child: SizedBox(
                        width: 32.sp,
                        height: 32.sp,
                        child: Icon(Icons.close, size: 16.sp, color: context.redErrorLight),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            Divider(height: 1, color: context.strokeLight),
            Padding(
              padding: EdgeInsets.fromLTRB(16.w, 16.h, 16.w, 8.h),
              child: Column(
                children: [
                  for (final r in _reasons)
                    InkWell(
                      onTap: _sending
                          ? null
                          : () => setState(() => _selected = r.value),
                      child: Padding(
                        padding: EdgeInsets.symmetric(vertical: 8.h),
                        child: Row(
                          children: [
                            _RadioDot(selected: _selected == r.value),
                            SizedBox(width: 12.w),
                            Expanded(
                              child: Text(
                                r.label,
                                style: context.bodyRegular?.copyWith(
                                  color: context.heading,
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
            Align(
              alignment: Alignment.centerRight,
              child: Padding(
                padding: EdgeInsets.only(right: 16.w, top: 4.h),
                child: TextButton(
                  onPressed: (_selected == null || _sending) ? null : _submit,
                  style: TextButton.styleFrom(
                    backgroundColor: context.primaryTheme,
                    disabledBackgroundColor:
                        context.primaryTheme.withValues(alpha: 0.45),
                    foregroundColor: context.primaryHover,
                    disabledForegroundColor: context.primaryHover.withValues(alpha: 0.7),
                    padding:
                        EdgeInsets.symmetric(horizontal: 28.w, vertical: 10.h),
                    shape: StadiumBorder(),
                  ),
                  child: Text(
                    _sending ? 'SENDING…' : 'SEND',
                    style: context.bodyRegular?.copyWith(
                      fontWeight: FontWeight.w700,
                      color: context.tertiaryText,
                      letterSpacing: 0.6,
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _RadioDot extends StatelessWidget {
  final bool selected;

  const _RadioDot({required this.selected});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 20.sp,
      height: 20.sp,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        border: Border.all(color: context.primaryTheme, width: 2),
      ),
      alignment: Alignment.center,
      child: selected
          ? Container(
              width: 10.sp,
              height: 10.sp,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: context.primaryTheme,
              ),
            )
          : null,
    );
  }
}
