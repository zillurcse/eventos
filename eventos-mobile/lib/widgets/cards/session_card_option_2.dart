import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import 'package:url_launcher/url_launcher.dart';

import '../../utils/extension/theme_ext.dart';
import '../custom_image.dart';
import '../image_group.dart';
import '../../models/session_model.dart';
import '../../models/mappers/session_mapper.dart';
import '../../features/session/pages/session_details.dart';
import '../../features/session/session_controller.dart';
import '../../features/briefcase/briefcase_controller.dart';
import '../../utils/bottom_sheets/add_note_bottom_sheet.dart';
import '../../utils/helpers/bottom_sheets.dart';

class SessionCardOption2 extends StatelessWidget {
  final SessionModel? session;
  final bool fullWidth;
  final String title;
  final String startTime;
  final String endTime;
  final String dayLabel;
  final String location;
  final List<String> speakerImageUrls;

  const SessionCardOption2({
    super.key,
    this.session,
    this.fullWidth = false,
    this.title = '',
    this.startTime = '',
    this.endTime = '',
    this.dayLabel = '',
    this.location = '',
    this.speakerImageUrls = const [],
  });

  @override
  Widget build(BuildContext context) {
    final timeLabel = (startTime.isNotEmpty && endTime.isNotEmpty) ? '$startTime - $endTime' : '10:00 AM - 12:00 AM';
    final dateLabel = dayLabel.isNotEmpty ? dayLabel : '';
    final displayTitle = title.isNotEmpty ? title : '';
    final loc = location.isNotEmpty ? location : '';

    return GestureDetector(
      onTap: () {
        if (session != null) {
          Get.to(() => SessionDetails(scheduleId: session!.id));
        }
      },
      child: Container(
        width: fullWidth ? double.infinity : context.width * .8,
        margin: fullWidth ? EdgeInsets.zero : EdgeInsets.only(right: 16.w),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12.r),
          border: Border.all(color: context.strokeLight),
        ),
        padding: EdgeInsets.all(16.sp),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Top Row
            Row(
              children: [
                Expanded(
                  child: Text(
                    '$dateLabel | $timeLabel',
                    style: context.bodyRegular?.copyWith(
                      color: context.caption,
                      fontWeight: FontWeight.w500,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                SizedBox(width: 8.w),
                GestureDetector(
                  onTap: () {
                    if (session != null) {
                      final briefcaseCtrl = Get.find<BriefcaseController>();
                      final match = briefcaseCtrl.notes.firstWhereOrNull(
                        (n) => n.noteType == 'Session' && n.entityId == session!.id,
                      );
                      addNoteBottomSheet(
                        child: AddNoteBottomSheet(
                          noteType: 'Session',
                          entityId: session!.id,
                          entityName: session!.title,
                          entityRole: loc,
                          entityImage: '',
                          initialNoteText: match?.noteText,
                        ),
                      );
                    }
                  },
                  child: CustomImage(
                    "assets/svg/icons/note_take.svg",
                    height: 22.sp,
                    width: 22.sp,
                    color: context.primaryTheme,
                  ),
                ),
                SizedBox(width: 16.w),
                Obx(() {
                  if (session == null) return const SizedBox.shrink();
                  final isBookmarked = Get.find<SessionController>().isBookmarked(session!.uuid);
                  return GestureDetector(
                    onTap: () => Get.find<SessionController>().toggleBookmark(session?.id ?? 0),
                    child: CustomImage(
                      isBookmarked ? "assets/svg/icons/bookmark_fill.svg" : "assets/svg/icons/bookmark.svg",
                      height: 22.sp,
                      width: 22.sp,
                      color: context.primaryTheme,
                    ),
                  );
                }),
                SizedBox(width: 16.w),
                GestureDetector(
                  onTap: () async {
                    if (session != null) {
                      final url = SessionMapper.googleCalendarUrl(
                        title: session!.title,
                        startsAt: session!.startsAt,
                        endsAt: session!.endsAt,
                      );
                      if (url.isEmpty) {
                        Get.snackbar(
                          'Calendar',
                          'Session time is not available.',
                          snackPosition: SnackPosition.BOTTOM,
                        );
                        return;
                      }
                      final uri = Uri.tryParse(url);
                      if (uri != null) {
                        await launchUrl(uri, mode: LaunchMode.externalApplication);
                      }
                    }
                  },
                  child: CustomImage(
                    "assets/svg/icons/calender_add.svg",
                    height: 22.sp,
                    width: 22.sp,
                    color: context.primaryTheme,
                  ),
                ),
              ],
            ),
            SizedBox(height: 12.h),
            // Blue Divider -> Ghost Divider
            Container(
              height: 2.h,
              color: context.ghost,
            ),
            SizedBox(height: 12.h),
            // Title and Location
            Text(
              displayTitle,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: context.h2?.copyWith(
                color: context.heading,
                fontWeight: FontWeight.w500,
              ),
            ),
            SizedBox(height: 8.h),
            Row(
              children: [
                CustomImage(
                  "assets/svg/icons/map.svg",
                  height: 16.sp,
                  color: context.caption,
                ),
                SizedBox(width: 8.w),
                Expanded(
                  child: Text(
                    loc,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: context.bodyRegular?.copyWith(
                      color: context.caption,
                    ),
                  ),
                ),
              ],
            ),
            SizedBox(height: 16.h),
            // Bottom Divider
            Divider(color: context.ghost, height: 1),
            SizedBox(height: 12.h),
            // Bottom Row (Speakers & Sponsors)
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                if (speakerImageUrls.isNotEmpty)
                  ImageGroup(imageUrls: speakerImageUrls)
                else
                  const SizedBox.shrink(),
                // Sponsor Logos Placeholder
                Row(
                  children: [
                    _buildSponsorLogo("https://upload.wikimedia.org/wikipedia/commons/f/fa/Apple_logo_black.svg"),
                    SizedBox(width: 4.w),
                    _buildSponsorLogo("https://upload.wikimedia.org/wikipedia/en/thumb/d/d3/Starbucks_Corporation_Logo_2011.svg/1200px-Starbucks_Corporation_Logo_2011.svg.png"),
                    SizedBox(width: 4.w),
                    _buildSponsorLogo("https://upload.wikimedia.org/wikipedia/en/thumb/e/e8/Shell_logo.svg/1200px-Shell_logo.svg.png"),
                    SizedBox(width: 4.w),
                    Container(
                      width: 28.sp,
                      height: 28.sp,
                      decoration: BoxDecoration(
                        color: context.primaryTheme,
                        borderRadius: BorderRadius.circular(4.r),
                      ),
                      alignment: Alignment.center,
                      child: Text(
                        "+2",
                        style: context.specialCaption1?.copyWith(
                          color: Colors.white,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSponsorLogo(String url) {
    return Container(
      width: 28.sp,
      height: 28.sp,
      padding: EdgeInsets.all(4.sp),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(4.r),
        border: Border.all(color: Colors.grey.shade300),
      ),
      child: CustomImage(
        url,
        fit: BoxFit.contain,
      ),
    );
  }
}
