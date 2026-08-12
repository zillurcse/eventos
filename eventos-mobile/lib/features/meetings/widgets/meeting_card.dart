import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../models/meeting_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../utils/theme/app_colors.dart';
import '../../../widgets/custom_button.dart';
import '../../../widgets/custom_image.dart';
import '../meeting_time_utils.dart';
import '../meetings_controller.dart';
import '../../../widgets/shimmer_box.dart';

class MeetingCard extends StatelessWidget {
  final Meeting meeting;

  const MeetingCard({super.key, required this.meeting});

  @override
  Widget build(BuildContext context) {
    final ctrl = Get.find<MeetingsController>();
    final badge = meetingBadge(meeting);
    final person = meeting.counterpart;
    final subtitle = person?.subtitle ?? '';
    final when = meetingTimeLabel(meeting);
    final location = meeting.displayLocation;
    final joinable = isMeetingJoinable(meeting);

    return Container(
      margin: EdgeInsets.only(bottom: 14.h),
      padding: EdgeInsets.all(16.w),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12.r),
        border: Border.all(color: strokeLight),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 10,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              _StatusBadge(label: badge.label, kind: badge.kind),
              SizedBox(width: 10.w),
              Expanded(
                child: Text(
                  when,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: context.bodyRegular?.copyWith(
                    fontWeight: FontWeight.w700,
                    color: context.heading,
                    fontSize: 13.sp,
                  ),
                ),
              ),
            ],
          ),
          SizedBox(height: 14.h),
          Divider(height: 1, color: strokeLight),
          SizedBox(height: 14.h),
          if (meeting.title != null && meeting.title!.isNotEmpty) ...[
            Text(
              meeting.title!,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: context.h2?.copyWith(
                fontWeight: FontWeight.w800,
                fontSize: 16.sp,
              ),
            ),
            if (meeting.agenda != null && meeting.agenda!.isNotEmpty) ...[
              SizedBox(height: 6.h),
              Text(
                meeting.agenda!,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: context.bodyRegular?.copyWith(
                  color: context.caption,
                  fontSize: 13.sp,
                  height: 1.35,
                ),
              ),
            ],
            SizedBox(height: 14.h),
          ],
          Text(
            meeting.source == 'exhibitor'
                ? 'Meeting with Booth'
                : 'Meeting with',
            style: context.specialCaption2?.copyWith(
              color: context.ghost,
              fontSize: 12.sp,
            ),
          ),
          SizedBox(height: 10.h),
          Row(
            children: [
              _Avatar(
                url: person?.avatarUrl,
                name: person?.name ?? 'A',
              ),
              SizedBox(width: 12.w),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      person?.name ?? 'Attendee',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: context.bodyRegular?.copyWith(
                        fontWeight: FontWeight.w700,
                        color: context.heading,
                      ),
                    ),
                    if (subtitle.isNotEmpty) ...[
                      SizedBox(height: 2.h),
                      Text(
                        subtitle,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: context.specialCaption2?.copyWith(
                          color: context.caption,
                          fontSize: 12.sp,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
          SizedBox(height: 14.h),
          Divider(height: 1, color: strokeLight),
          SizedBox(height: 12.h),
          Row(
            children: [
              Icon(
                Icons.location_on_outlined,
                size: 18.sp,
                color: location.isNotEmpty
                    ? context.primaryTheme
                    : context.ghost,
              ),
              SizedBox(width: 6.w),
              Expanded(
                child: Text(
                  location.isNotEmpty
                      ? location
                      : 'Location not added yet.',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: context.bodyRegular?.copyWith(
                    color: location.isNotEmpty
                        ? context.primaryTheme
                        : context.ghost,
                    fontWeight:
                        location.isNotEmpty ? FontWeight.w600 : FontWeight.w400,
                    fontStyle:
                        location.isNotEmpty ? FontStyle.normal : FontStyle.italic,
                    fontSize: 13.sp,
                  ),
                ),
              ),
            ],
          ),
          SizedBox(height: 14.h),
          Obx(() {
            final acting = ctrl.actingIds.contains(meeting.id);
            final joining = ctrl.joiningId.value == meeting.id;

            if (meeting.canRespond) {
              return Row(
                children: [
                  Expanded(
                    child: Button.roundedText(
                      text: 'Decline',
                      onTap: acting
                          ? () {}
                          : () => ctrl.respond(meeting, 'reject'),
                      height: 40,
                      backgroundColor: const Color(0xFFF1F5F9),
                      onBackgroundColor: const Color(0xFF475569),
                      style: TextStyle(
                        color: const Color(0xFF475569),
                        fontSize: 14.sp,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  SizedBox(width: 10.w),
                  Expanded(
                    child: Button.roundedText(
                      text: acting ? '...' : 'Accept',
                      onTap: acting
                          ? () {}
                          : () => ctrl.respond(meeting, 'accept'),
                      height: 40,
                    ),
                  ),
                ],
              );
            }

            if (meeting.direction == 'outgoing' &&
                meeting.status == 'requested') {
              return Align(
                alignment: Alignment.centerRight,
                child: SizedBox(
                  width: 120.w,
                  child: Button.roundedText(
                    text: acting ? '...' : 'Withdraw',
                    onTap: acting
                        ? () {}
                        : () => ctrl.respond(meeting, 'cancel'),
                    height: 40,
                  ),
                ),
              );
            }

            if (joinable) {
              return Align(
                alignment: Alignment.centerRight,
                child: SizedBox(
                  width: 100.w,
                  child: Button.roundedText(
                    text: joining ? 'Joining…' : 'Join',
                    onTap: joining ? () {} : () => ctrl.joinMeeting(meeting),
                    height: 40,
                  ),
                ),
              );
            }

            return const SizedBox.shrink();
          }),
        ],
      ),
    );
  }
}

class _StatusBadge extends StatelessWidget {
  final String label;
  final String kind;

  const _StatusBadge({required this.label, required this.kind});

  @override
  Widget build(BuildContext context) {
    final (Color bg, Color fg) = switch (kind) {
      'ok' => (const Color(0xFFE6F9F0), const Color(0xFF16A34A)),
      'no' => (const Color(0xFFFDCECE), const Color(0xFFDC2626)),
      'muted' => (const Color(0xFFF1F5F9), const Color(0xFF64748B)),
      _ => (const Color(0xFFFFF6E5), const Color(0xFFD97706)),
    };

    return Container(
      padding: EdgeInsets.symmetric(horizontal: 10.w, vertical: 5.h),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(6.r),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: fg,
          fontSize: 11.sp,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class _Avatar extends StatelessWidget {
  final String? url;
  final String name;

  const _Avatar({this.url, required this.name});

  @override
  Widget build(BuildContext context) {
    final initial = name.isNotEmpty ? name[0].toUpperCase() : '?';

    return ClipRRect(
      borderRadius: BorderRadius.circular(8.r),
      child: SizedBox(
        width: 40.sp,
        height: 40.sp,
        child: url != null && url!.isNotEmpty
            ? CustomImage(url!, fit: BoxFit.cover, width: 40.sp, height: 40.sp)
            : ColoredBox(
                color: context.primaryFocused,
                child: Center(
                  child: Text(
                    initial,
                    style: TextStyle(
                      color: context.primaryTheme,
                      fontWeight: FontWeight.w700,
                      fontSize: 16.sp,
                    ),
                  ),
                ),
              ),
      ),
    );
  }
}

class MeetingCardSkeleton extends StatelessWidget {
  const MeetingCardSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: EdgeInsets.only(bottom: 14.h),
      padding: EdgeInsets.all(16.w),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12.r),
        border: Border.all(color: strokeLight),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              ShimmerBox(width: 60.w, height: 22.h, topRadius: 6.r),
              SizedBox(width: 10.w),
              ShimmerBox(width: 120.w, height: 16.h, topRadius: 4.r),
            ],
          ),
          SizedBox(height: 14.h),
          Divider(height: 1, color: strokeLight),
          SizedBox(height: 14.h),
          ShimmerBox(width: double.infinity, height: 20.h, topRadius: 4.r),
          SizedBox(height: 6.h),
          ShimmerBox(width: 200.w, height: 16.h, topRadius: 4.r),
          SizedBox(height: 14.h),
          ShimmerBox(width: 100.w, height: 12.h, topRadius: 4.r),
          SizedBox(height: 10.h),
          Row(
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(8.r),
                child: ShimmerBox(width: 40.sp, height: 40.sp, topRadius: 8.r),
              ),
              SizedBox(width: 12.w),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    ShimmerBox(width: 150.w, height: 16.h, topRadius: 4.r),
                    SizedBox(height: 4.h),
                    ShimmerBox(width: 100.w, height: 12.h, topRadius: 4.r),
                  ],
                ),
              ),
            ],
          ),
          SizedBox(height: 14.h),
          Divider(height: 1, color: strokeLight),
          SizedBox(height: 12.h),
          Row(
            children: [
              ShimmerBox(width: 18.sp, height: 18.sp, topRadius: 9.r),
              SizedBox(width: 6.w),
              ShimmerBox(width: 150.w, height: 14.h, topRadius: 4.r),
            ],
          ),
          SizedBox(height: 14.h),
          Row(
            children: [
              Expanded(
                child: ShimmerBox(width: double.infinity, height: 40.h, topRadius: 10.r),
              ),
              SizedBox(width: 10.w),
              Expanded(
                child: ShimmerBox(width: double.infinity, height: 40.h, topRadius: 10.r),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
