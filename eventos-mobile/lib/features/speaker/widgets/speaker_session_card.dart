import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';
import '../../../models/session_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_image.dart';
import '../../bookmarks/bookmark_controller.dart';
import '../../session/pages/session_details.dart';

class SpeakerSessionCard extends StatefulWidget {
  final SessionModel session;
  final bool hasBorder;

  const SpeakerSessionCard({
    super.key,
    required this.session,
    this.hasBorder = false,
  });

  @override
  State<SpeakerSessionCard> createState() => _SpeakerSessionCardState();
}

class _SpeakerSessionCardState extends State<SpeakerSessionCard> {
  late bool _inMySchedule;
  late final BookmarkController _bookmarkCtrl;

  @override
  void initState() {
    super.initState();
    _inMySchedule = widget.session.inMySchedule;
    _bookmarkCtrl = Get.put(BookmarkController());
  }

  String _formatDate(String dateStr) {
    if (dateStr.isEmpty) return '';
    try {
      final parsed = DateTime.tryParse(dateStr);
      if (parsed != null) {
        final day = parsed.day;
        String suffix = 'th';
        if (day >= 11 && day <= 13) {
          suffix = 'th';
        } else {
          switch (day % 10) {
            case 1:
              suffix = 'st';
              break;
            case 2:
              suffix = 'nd';
              break;
            case 3:
              suffix = 'rd';
              break;
            default:
              suffix = 'th';
          }
        }
        final monthStr = DateFormat('MMM').format(parsed);
        final yearStr = DateFormat('yyyy').format(parsed);
        return '$day$suffix $monthStr, $yearStr';
      }
    } catch (_) {}
    return dateStr;
  }

  String _formatTime(String timeStr) {
    if (timeStr.isEmpty) return '';
    try {
      final parts = timeStr.split(':');
      if (parts.length >= 2) {
        final hour = int.parse(parts[0]);
        final minute = int.parse(parts[1]);
        final dt = DateTime(2026, 1, 1, hour, minute);
        return DateFormat('h:mm a').format(dt);
      }
    } catch (_) {}
    return timeStr;
  }

  @override
  Widget build(BuildContext context) {
    final session = widget.session;
    final dateFormatted = _formatDate(session.day.date.isNotEmpty ? session.day.date : session.day.title);
    final startTimeFormatted = _formatTime(session.startTime);
    final endTimeFormatted = _formatTime(session.endTime);
    final timeRange = (startTimeFormatted.isNotEmpty && endTimeFormatted.isNotEmpty)
        ? '$startTimeFormatted - $endTimeFormatted'
        : '';
    final dateAndTimeText = [dateFormatted, timeRange]
        .where((element) => element.isNotEmpty)
        .join('  |  ');

    return GestureDetector(
      onTap: () => Get.to(() => SessionDetails(scheduleId: session.id)),
      child: Container(
        margin: EdgeInsets.symmetric(horizontal: 16.w, vertical: 8.h),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16.r),
          border: Border.all(color: context.strokeLight, width: 1.sp),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.03),
              blurRadius: 8,
              offset: const Offset(0, 3),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Top row: Date & Time, Action Icons
            Padding(
              padding: EdgeInsets.fromLTRB(16.w, 16.h, 16.w, 12.h),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      dateAndTimeText.isNotEmpty ? dateAndTimeText : 'Date and Time Info',
                      style: context.bodyRegular?.copyWith(
                        color: context.caption,
                        fontWeight: FontWeight.w500,
                        fontSize: 12.sp,
                      ),
                    ),
                  ),
                  Obx(() {
                    final isBookmarked = _bookmarkCtrl.bookmarkedSessions.any((s) => s.id == session.id);
                    return GestureDetector(
                      onTap: () {
                        _bookmarkCtrl.toggleSessionBookmark(session);
                      },
                      child: CustomImage(
                        isBookmarked ? "assets/svg/icons/bookmark_fill.svg" : "assets/svg/icons/bookmark.svg",
                        height: 20.sp,
                        width: 20.sp,
                        color: isBookmarked ? context.primaryTheme : context.primaryTheme.withValues(alpha: 0.5),
                      ),
                    );
                  }),
                  SizedBox(width: 14.w),
                  GestureDetector(
                    onTap: () {
                      setState(() {
                        _inMySchedule = !_inMySchedule;
                      });
                    },
                    child: CustomImage(
                      "assets/svg/icons/calender_add.svg",
                      height: 20.sp,
                      width: 20.sp,
                      color: _inMySchedule ? context.primaryTheme : context.primaryTheme.withValues(alpha: 0.5),
                    ),
                  ),
                ],
              ),
            ),
            Padding(
              padding: EdgeInsets.symmetric(horizontal: 16.w),
              child: Divider(color: context.strokeLight, height: 1.h, thickness: 1.h),
            ),
            // Cover Image, Title, Location
            Padding(
              padding: EdgeInsets.all(16.w),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    decoration: widget.hasBorder
                        ? BoxDecoration(
                            borderRadius: BorderRadius.circular(8.r),
                            border: Border.all(color: context.primaryTheme, width: 2.sp),
                          )
                        : null,
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(8.r),
                      child: CustomImage(
                        "https://cdn.pixabay.com/photo/2016/11/21/06/53/beautiful-natural-image-1844362_640.jpg",
                        width: double.infinity,
                        height: 120.h,
                        fit: BoxFit.cover,
                      ),
                    ),
                  ),
                  SizedBox(height: 16.h),
                  Text(
                    session.title,
                    style: context.h2?.copyWith(
                      color: context.heading,
                      fontWeight: FontWeight.bold,
                    ),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  if (session.sessionPlace.isNotEmpty) ...[
                    SizedBox(height: 12.h),
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.center,
                      children: [
                        Container(
                          padding: EdgeInsets.all(8.sp),
                          decoration: BoxDecoration(
                            color: context.strokeLight.withValues(alpha: 0.3),
                            borderRadius: BorderRadius.circular(8.r),
                          ),
                          child: CustomImage(
                            "assets/svg/icons/map.svg",
                            height: 18.sp,
                            width: 18.sp,
                            color: context.caption,
                          ),
                        ),
                        SizedBox(width: 12.w),
                        Expanded(
                          child: Text(
                            session.sessionPlace,
                            style: context.bodyRegular?.copyWith(
                              color: context.caption,
                              fontSize: 12.sp,
                            ),
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ],
                    ),
                  ],
                ],
              ),
            ),
            Padding(
              padding: EdgeInsets.symmetric(horizontal: 16.w),
              child: Divider(color: context.strokeLight, height: 1.h, thickness: 1.h),
            ),
            // Speakers section
            Padding(
              padding: EdgeInsets.fromLTRB(16.w, 12.h, 16.w, 16.h),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    "Speakers (${session.speakers.length})",
                    style: context.bodyRegular?.copyWith(
                      color: context.caption,
                      fontSize: 12.sp,
                    ),
                  ),
                  SizedBox(height: 8.h),
                  _buildSpeakersRow(context, session.speakers),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSpeakersRow(BuildContext context, List<dynamic> speakers) {
    if (speakers.isEmpty) return const SizedBox.shrink();

    const maxSpeakers = 4;
    final displaySpeakers = speakers.take(maxSpeakers).toList();
    final extraSpeakers = speakers.length - maxSpeakers;

    return Row(
      children: [
        Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            for (int i = 0; i < displaySpeakers.length; i++)
              Align(
                widthFactor: 0.7,
                child: Container(
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    border: Border.all(color: Colors.white, width: 2.sp),
                  ),
                  child: CustomImage(
                    displaySpeakers[i].imageUrl,
                    width: 32.sp,
                    height: 32.sp,
                    isCircle: true,
                    avatar: true,
                  ),
                ),
              ),
          ],
        ),
        if (extraSpeakers > 0) ...[
          SizedBox(width: 12.w),
          Text(
            "+$extraSpeakers More",
            style: context.bodyRegular?.copyWith(
              color: context.primaryTheme,
              fontWeight: FontWeight.bold,
            ),
          ),
        ],
      ],
    );
  }
}
