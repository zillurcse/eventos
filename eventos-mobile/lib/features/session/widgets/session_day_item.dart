import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:intl/intl.dart';
import 'package:get/get.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../../../models/session_model.dart';
import '../../../../models/mappers/session_mapper.dart';
import '../../../../widgets/custom_image.dart';
import '../../../../utils/extension/theme_ext.dart';
import '../pages/session_details.dart';
import '../session_controller.dart';
import '../../briefcase/briefcase_controller.dart';
import '../../../../utils/bottom_sheets/add_note_bottom_sheet.dart';
import '../../../../utils/helpers/bottom_sheets.dart';

class SessionDayItem extends StatelessWidget {
  final SessionModel session;

  const SessionDayItem({
    super.key,
    required this.session,
  });

  String _formatDate(String dateStr) {
    if (dateStr.isEmpty) return '';
    try {
      final parsed = DateTime.tryParse(dateStr);
      if (parsed != null) {
        return DateFormat('MMMM dd').format(parsed);
      }
    } catch (_) {}
    return dateStr;
  }

  @override
  Widget build(BuildContext context) {
    final dateFormatted = _formatDate(session.day.date.isNotEmpty ? session.day.date : session.day.title);
    final startTimeFormatted = session.startTime;
    final endTimeFormatted = session.endTime;
    final timeRange = (startTimeFormatted.isNotEmpty && endTimeFormatted.isNotEmpty)
        ? '$startTimeFormatted - $endTimeFormatted'
        : '';
    final dateAndTimeText = [dateFormatted, timeRange]
        .where((element) => element.isNotEmpty)
        .join(' | ');

    // Extract speakers and sponsors
    final speakers = session.speakers;
    final sponsors = session.sponsors;

    // Display limits for avatar/logos
    const maxSpeakers = 3;
    final displaySpeakers = speakers.take(maxSpeakers).toList();
    final extraSpeakers = speakers.length - maxSpeakers;

    const maxSponsors = 3;
    final displaySponsors = sponsors.take(maxSponsors).toList();
    final extraSponsors = sponsors.length - maxSponsors;

    return GestureDetector(
      onTap: () => Get.to(() => SessionDetails(scheduleId: session.id)),
      child: Container(
        margin: EdgeInsets.symmetric(horizontal: 16.w, vertical: 8.h),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16.r),
        border: Border.all(color: context.strokeLight, width: 1.r),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.03),
            blurRadius: 10.r,
            offset: Offset(0, 4.h),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // ── Top Row (Date, Time, Action Icons) ──
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
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                SizedBox(width: 8.w),
                // Icon Buttons
                GestureDetector(
                  onTap: () async {
                    final url = SessionMapper.googleCalendarUrl(
                      title: session.title,
                      startsAt: session.startsAt,
                      endsAt: session.endsAt,
                    );
                    if (url.isEmpty) return;
                    final uri = Uri.tryParse(url);
                    if (uri != null) {
                      await launchUrl(uri, mode: LaunchMode.externalApplication);
                    }
                  },
                  child: CustomImage(
                    "assets/svg/icons/schedule.svg",
                    height: 20.sp,
                    width: 20.sp,
                    color: context.primaryTheme,
                  ),
                ),
                SizedBox(width: 14.w),
                GestureDetector(
                  onTap: () {
                    final sessionCtrl = Get.find<SessionController>();
                    sessionCtrl.toggleBookmark(session.id);
                  },
                  child: CustomImage(
                    session.isFavorite ? "assets/svg/icons/bookmark_fill.svg" : "assets/svg/icons/bookmark.svg",
                    height: 20.sp,
                    width: 20.sp,
                    color: session.isFavorite ? context.primaryTheme : context.primaryTheme.withValues(alpha: 0.5),
                  ),
                ),
                SizedBox(width: 14.w),
                GestureDetector(
                  onTap: () {
                    final briefcaseCtrl = Get.find<BriefcaseController>();
                    final match = briefcaseCtrl.notes.firstWhereOrNull(
                      (n) => n.noteType == 'Session' && n.entityId == session.id,
                    );
                    final initialNoteText = match?.noteText;

                    addNoteBottomSheet(
                      child: AddNoteBottomSheet(
                        noteType: 'Session',
                        entityId: session.id,
                        entityName: session.title,
                        entityRole: session.sessionPlace.isNotEmpty ? session.sessionPlace : 'Community Hall',
                        entityImage: '',
                        initialNoteText: initialNoteText,
                      ),
                    );
                  },
                  child: Obx(() {
                    final briefcaseCtrl = Get.find<BriefcaseController>();
                    final hasNoteLocally = briefcaseCtrl.notes.any(
                      (n) => n.noteType == 'Session' && n.entityId == session.id,
                    );
                    return CustomImage(
                      "assets/svg/icons/calender_add.svg",
                      height: 20.sp,
                      width: 20.sp,
                      color: hasNoteLocally ? context.primaryTheme : context.primaryTheme.withValues(alpha: 0.5),
                    );
                  }),
                ),
              ],
            ),
          ),

          Padding(
            padding: EdgeInsets.symmetric(horizontal: 16.w),
            child: Divider(color: context.strokeLight, height: 1.h, thickness: 1.h),
          ),

          // ── Middle Row (Title, Location) ──
          Padding(
            padding: EdgeInsets.fromLTRB(16.w, 14.h, 16.w, 14.h),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  session.title.isNotEmpty ? session.title : 'Session Title',
                  style: context.h2?.copyWith(
                    color: context.heading,
                    fontWeight: FontWeight.w700,
                    height: 1.25,
                  ),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
                if (session.sessionPlace.isNotEmpty) ...[
                  SizedBox(height: 10.h),
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.center,
                    children: [
                      CustomImage(
                        "assets/svg/icons/map.svg",
                        height: 16.sp,
                        width: 16.sp,
                        color: context.caption,
                      ),
                      SizedBox(width: 6.w),
                      Expanded(
                        child: Text(
                          session.sessionPlace,
                          style: context.bodyRegular?.copyWith(
                            color: context.caption,
                          ),
                          maxLines: 1,
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

          // ── Bottom Row (Speakers Stack & Sponsor Brands) ──
          Padding(
            padding: EdgeInsets.fromLTRB(16.w, 12.h, 16.w, 16.h),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                // Speakers overlapping avatars stack
                if (speakers.isNotEmpty)
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
                      if (extraSpeakers > 0)
                        Align(
                          widthFactor: 0.7,
                          child: Container(
                            width: 32.sp,
                            height: 32.sp,
                            decoration: BoxDecoration(
                              color: context.primaryTheme,
                              shape: BoxShape.circle,
                              border: Border.all(color: Colors.white, width: 2.sp),
                            ),
                            alignment: Alignment.center,
                            child: Text(
                              "+$extraSpeakers",
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: 11.sp,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ),
                        ),
                    ],
                  )
                else
                  const SizedBox.shrink(),

                // Sponsor Logos row
                if (sponsors.isNotEmpty)
                  Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      for (int i = 0; i < displaySponsors.length; i++)
                        Container(
                          margin: EdgeInsets.only(left: 6.w),
                          width: 32.sp,
                          height: 32.sp,
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(6.r),
                            border: Border.all(color: context.strokeLight, width: 1.r),
                          ),
                          padding: EdgeInsets.all(4.sp),
                          child: CustomImage(
                            displaySponsors[i].logoUrl,
                            fit: BoxFit.contain,
                          ),
                        ),
                      if (extraSponsors > 0)
                        Container(
                          margin: EdgeInsets.only(left: 6.w),
                          width: 32.sp,
                          height: 32.sp,
                          decoration: BoxDecoration(
                            color: context.primaryTheme,
                            borderRadius: BorderRadius.circular(6.r),
                          ),
                          alignment: Alignment.center,
                          child: Text(
                            "+$extraSponsors",
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 11.sp,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                    ],
                  )
                else
                  const SizedBox.shrink(),
              ],
            ),
          ),
        ],
      ),
    ),
  );
}
}
