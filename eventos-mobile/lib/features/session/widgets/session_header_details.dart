import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:intl/intl.dart';
import '../../../models/session_detail_response_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../session_phase.dart';

class SessionHeaderDetails extends StatelessWidget {
  final SessionDetailModel detail;

  const SessionHeaderDetails({super.key, required this.detail});

  String _formatDate(String dateStr) {
    if (dateStr.isEmpty) return '';
    try {
      final parsed = DateTime.tryParse(dateStr);
      if (parsed != null) {
        final day = parsed.day;
        String suffix = 'th';
        if (day == 1 || day == 21 || day == 31) {
          suffix = 'st';
        } else if (day == 2 || day == 22) {
          suffix = 'nd';
        } else if (day == 3 || day == 23) {
          suffix = 'rd';
        }
        final monthAndYear = DateFormat('MMM, yyyy').format(parsed);
        return '$day$suffix $monthAndYear';
      }
    } catch (_) {}
    return dateStr;
  }

  @override
  Widget build(BuildContext context) {
    final dateSource = detail.startsAt ??
        (detail.day.date.isNotEmpty ? detail.day.date : detail.day.title);
    final dateFormatted = _formatDate(dateSource);
    final startTimeFormatted = detail.startTime;
    final endTimeFormatted = detail.endTime;
    final timeRange = (startTimeFormatted.isNotEmpty && endTimeFormatted.isNotEmpty)
        ? '$startTimeFormatted - $endTimeFormatted'
        : '';
    final dateAndTimeText = [dateFormatted, timeRange]
        .where((element) => element.isNotEmpty)
        .join(' | ');
    final isLiveNow = SessionPhaseHelper.isLiveNow(
      status: detail.status,
      startsAt: detail.startsAt,
      endsAt: detail.endsAt,
    );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Badges
        Row(
          children: [
            if (isLiveNow) ...[
              Container(
                padding: EdgeInsets.symmetric(horizontal: 8.w, vertical: 4.h),
                decoration: BoxDecoration(
                  color: const Color(0xFFFF4A00),
                  borderRadius: BorderRadius.circular(4.r),
                ),
                child: Text(
                  'LIVE',
                  style: context.specialCaption2?.copyWith(
                    color: Colors.white,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
              SizedBox(width: 8.w),
            ],
            if (detail.isFeatured)
              Container(
                padding: EdgeInsets.symmetric(horizontal: 8.w, vertical: 4.h),
                decoration: BoxDecoration(
                  color: context.primaryTheme.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(4.r),
                  border: Border.all(color: context.primaryTheme, width: 0.5.r),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(
                      Icons.star_rounded,
                      size: 12.sp,
                      color: context.primaryTheme,
                    ),
                    SizedBox(width: 4.w),
                    Text(
                      'Featured',
                      style: context.specialCaption2?.copyWith(
                        color: context.primaryTheme,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ],
                ),
              ),
          ],
        ),
        SizedBox(height: 12.h),

        // Title
        Text(
          detail.title,
          style: context.h2?.copyWith(
            color: context.heading,
            fontWeight: FontWeight.bold,
          ),
        ),
        SizedBox(height: 16.h),

        // Date and Time
        Row(
          children: [
            Icon(
              Icons.calendar_today_outlined,
              size: 16.sp,
              color: context.caption,
            ),
            SizedBox(width: 8.w),
            Expanded(
              child: Text(
                dateAndTimeText,
                style: context.bodyRegular?.copyWith(color: context.caption),
              ),
            ),
          ],
        ),
        if (detail.sessionPlace.isNotEmpty) ...[
          SizedBox(height: 10.h),
          Row(
            children: [
              Icon(
                Icons.location_on_outlined,
                size: 16.sp,
                color: context.caption,
              ),
              SizedBox(width: 8.w),
              Expanded(
                child: Text(
                  detail.sessionPlace,
                  style: context.bodyRegular?.copyWith(color: context.caption),
                ),
              ),
            ],
          ),
        ],
      ],
    );
  }
}
