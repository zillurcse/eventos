import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import 'package:url_launcher/url_launcher.dart';

import '../../utils/extension/theme_ext.dart';
import '../custom_image.dart';
import '../../models/session_model.dart';
import '../../models/mappers/session_mapper.dart';
import '../../features/session/session_controller.dart';
import '../../features/session/pages/session_details.dart';
import '../../features/briefcase/briefcase_controller.dart';
import '../../utils/bottom_sheets/add_note_bottom_sheet.dart';
import '../../utils/helpers/bottom_sheets.dart';

class SessionCardOption1 extends StatelessWidget {
  final SessionModel? session;
  final bool fullWidth;
  final String title;
  final String startTime;
  final String endTime;
  final String location;
  final List<String> speakerImageUrls;

  const SessionCardOption1({
    super.key,
    this.session,
    this.fullWidth = false,
    this.title = '',
    this.startTime = '',
    this.endTime = '',
    this.location = '',
    this.speakerImageUrls = const [],
  });

  @override
  Widget build(BuildContext context) {
    final displayTitle = title.isNotEmpty ? title : 'Session Title';
    final loc = location.isNotEmpty ? location : 'Community Hall';

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
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            IntrinsicHeight(
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  // Left side: Times
                  Padding(
                    padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 16.h),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(
                          startTime.isNotEmpty ? startTime : '10:00 AM',
                          style: context.bodyRegular?.copyWith(
                            color: context.heading,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        SizedBox(height: 4.h),
                        Text(
                          endTime.isNotEmpty ? endTime : '12:00 AM',
                          style: context.bodyRegular?.copyWith(
                            color: context.caption,
                          ),
                        ),
                      ],
                    ),
                  ),
                  // Divider
                  VerticalDivider(
                    color: context.strokeLight,
                    width: 1,
                    thickness: 1,
                  ),
                  // Right side: Details
                  Expanded(
                    child: Padding(
                      padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 16.h),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            displayTitle,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: context.h2?.copyWith(
                              color: context.heading,
                              fontWeight: FontWeight.w500,
                            ),
                          ),
                          SizedBox(height: 12.h),
                          Row(
                            children: [
                              CustomImage(
                                "assets/svg/icons/speaker.svg",
                                height: 16.sp,
                                color: context.caption,
                              ),
                              SizedBox(width: 4.w),
                              Text(
                                "3", // Participant count placeholder
                                style: context.specialCaption1?.copyWith(
                                  color: context.caption,
                                ),
                              ),
                              SizedBox(width: 12.w),
                              CustomImage(
                                "assets/svg/icons/map.svg",
                                height: 16.sp,
                                color: context.caption,
                              ),
                              SizedBox(width: 4.w),
                              Expanded(
                                child: Text(
                                  loc,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: context.specialCaption1?.copyWith(
                                    color: context.caption,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
            // Bottom Action Bar
            Container(
              decoration: BoxDecoration(
                color: context.primaryTheme.withValues(alpha: 0.08),
                borderRadius: BorderRadius.vertical(bottom: Radius.circular(12.r)),
              ),
              padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 12.h),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
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
                      height: 24.sp,
                      width: 24.sp,
                      color: context.primaryTheme,
                    ),
                  ),
                  SizedBox(width: 20.w),
                  Obx(() {
                    if (session == null) return const SizedBox.shrink();
                    final isBookmarked = Get.find<SessionController>().isBookmarked(session!.uuid);
                    return GestureDetector(
                      onTap: () => Get.find<SessionController>().toggleBookmark(session!.id,
                        uuid: session!.uuid.isNotEmpty ? session?.uuid : null,),
                      child: CustomImage(
                        isBookmarked ? "assets/svg/icons/bookmark_fill.svg" : "assets/svg/icons/bookmark.svg",
                        height: 24.sp,
                        width: 24.sp,
                        color: context.primaryTheme,
                      ),
                    );
                  }),
                  SizedBox(width: 20.w),
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
                      height: 24.sp,
                      width: 24.sp,
                      color: context.primaryTheme,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
