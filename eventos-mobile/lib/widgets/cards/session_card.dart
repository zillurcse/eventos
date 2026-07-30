import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../utils/extension/theme_ext.dart';
import '../custom_button.dart';
import '../custom_image.dart';
import '../image_group.dart';
import '../../models/session_model.dart';
import '../../features/session/session_controller.dart';
import '../../features/briefcase/briefcase_controller.dart';
import '../../utils/bottom_sheets/add_note_bottom_sheet.dart';
import '../../utils/helpers/bottom_sheets.dart';

class SessionCard extends StatelessWidget {
  final SessionModel? session;
  final bool isOnGoing;
  final String title;
  final String startTime;
  final String endTime;
  final String dayLabel;
  final String logoUrl;
  final List<String> speakerImageUrls;

  const SessionCard({
    super.key,
    this.session,
    this.isOnGoing = false,
    this.title = '',
    this.startTime = '',
    this.endTime = '',
    this.dayLabel = '',
    this.logoUrl = '',
    this.speakerImageUrls = const [],
  });

  @override
  Widget build(BuildContext context) {
    final timeLabel = (startTime.isNotEmpty && endTime.isNotEmpty)
        ? '$startTime – $endTime'
        : '—';
    final displayTitle = title.isNotEmpty
        ? title
        : 'Session Title';

    return Container(
      width: context.width * .8,
      margin: EdgeInsets.only(right: 16.w),
      decoration: BoxDecoration(
        color: context.tertiaryText,
        borderRadius: BorderRadius.circular(12.r),
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(12.r),
        child: Column(
          children: [
            Padding(
              padding: EdgeInsets.all(16.sp),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        padding: EdgeInsets.all(4.sp),
                        decoration: BoxDecoration(
                          color: context.primaryFocused,
                          borderRadius: BorderRadius.circular(4.r),
                        ),
                        child: CustomImage(
                          "assets/svg/icons/schedule.svg",
                          height: 16.sp,
                        ),
                      ),
                      SizedBox(width: 8.w),
                      Text(
                        timeLabel,
                        style: context.titleRegular
                            ?.copyWith(color: context.caption),
                      ),
                      const Spacer(),
                      GestureDetector(
                        onTap: () {
                          if (session != null) {
                            final sessionCtrl = Get.find<SessionController>();
                            sessionCtrl.toggleBookmark(session!.id);
                          }
                        },
                        child: CustomImage(
                          (session?.isFavorite ?? false)
                              ? "assets/svg/icons/bookmark_fill.svg"
                              : "assets/svg/icons/bookmark.svg",
                          color: (session?.isFavorite ?? false)
                              ? context.primaryTheme
                              : context.primaryTheme.withValues(alpha: 0.5),
                        ),
                      ),
                      SizedBox(width: 12.w),
                      GestureDetector(
                        onTap: () {
                          if (session != null) {
                            final briefcaseCtrl = Get.find<BriefcaseController>();
                            final match = briefcaseCtrl.notes.firstWhereOrNull(
                              (n) => n.noteType == 'Session' && n.entityId == session!.id,
                            );
                            final initialNoteText = match?.noteText;

                            addNoteBottomSheet(
                              child: AddNoteBottomSheet(
                                noteType: 'Session',
                                entityId: session!.id,
                                entityName: session!.title,
                                entityRole: session!.sessionPlace.isNotEmpty
                                    ? session!.sessionPlace
                                    : 'Community Hall',
                                entityImage: '',
                                initialNoteText: initialNoteText,
                              ),
                            );
                          }
                        },
                        child: Obx(() {
                          final hasNoteLocally = session != null &&
                              Get.find<BriefcaseController>().notes.any(
                                    (n) => n.noteType == 'Session' && n.entityId == session!.id,
                                  );
                          return CustomImage(
                            "assets/svg/icons/calender_add.svg",
                            color: hasNoteLocally
                                ? context.primaryTheme
                                : context.primaryTheme.withValues(alpha: 0.5),
                          );
                        }),
                      ),
                    ],
                  ),
                  SizedBox(height: 20.sp),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(8.r),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        CustomImage(
                          logoUrl,
                          width: context.width,
                          height: context.height * .16,
                          fit: BoxFit.cover,
                        ),
                        if (isOnGoing)
                          LinearProgressIndicator(
                            value: .6,
                            backgroundColor: context.primaryFocused,
                            color: context.primaryTheme,
                            borderRadius: BorderRadius.only(
                              topRight: Radius.circular(100.r),
                              bottomRight: Radius.circular(100.r),
                            ),
                            minHeight: 6.h,
                          ),
                      ],
                    ),
                  ),
                  SizedBox(height: 20.sp),
                  Text(
                    displayTitle,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: context.h2?.copyWith(color: context.heading),
                  ),
                  if (dayLabel.isNotEmpty) ...[
                    SizedBox(height: 8.sp),
                    Text(
                      dayLabel,
                      style: context.specialCaption1
                          ?.copyWith(color: context.caption),
                    ),
                  ],
                  SizedBox(height: 12.sp),
                  if (speakerImageUrls.isNotEmpty) ...[
                    const Divider(),
                    SizedBox(height: 8.sp),
                    Text(
                      "Speakers (${speakerImageUrls.length})",
                      style: context.specialCaption1
                          ?.copyWith(color: context.caption),
                    ),
                    SizedBox(height: 8.sp),
                    ImageGroup(
                      imageUrls: speakerImageUrls,
                    ),
                    SizedBox(height: 12.sp),
                  ],
                  if (isOnGoing)
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Divider(),
                        SizedBox(height: 12.sp),
                        Button.roundedText(
                          text: "Join Now",
                          width: context.width * .4,
                          style: context.buttonMediumBold
                              ?.copyWith(color: context.tertiaryText),
                          onTap: () {},
                        ),
                      ],
                    )
                  else
                    Container(
                      padding: EdgeInsets.fromLTRB(8.sp, 4.sp, 12.sp, 6.sp),
                      decoration: BoxDecoration(
                        color: context.greenPositiveLight,
                        borderRadius: BorderRadius.circular(8.r),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          CustomImage("assets/svg/icons/timer.svg",
                              height: 14.sp),
                          SizedBox(width: 8.w),
                          Text(
                            "Starts soon",
                            style: context.specialCaption1
                                ?.copyWith(color: context.greenPositive),
                          ),
                        ],
                      ),
                    ),
                ],
              ),
            ),
            if (isOnGoing) ...[
              const Spacer(),
              LinearProgressIndicator(
                value: .6,
                backgroundColor: context.primaryFocused,
                color: context.primaryTheme,
                borderRadius: BorderRadius.only(
                  topRight: Radius.circular(100.r),
                  bottomRight: Radius.circular(100.r),
                ),
                minHeight: 6.h,
              ),
            ],
          ],
        ),
      ),
    );
  }
}
