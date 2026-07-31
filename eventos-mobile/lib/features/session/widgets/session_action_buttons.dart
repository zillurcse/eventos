import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../widgets/custom_image.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../utils/bottom_sheets/add_note_bottom_sheet.dart';
import '../../../utils/helpers/bottom_sheets.dart';
import '../../../models/session_detail_response_model.dart';
import '../../briefcase/briefcase_controller.dart';
import '../session_controller.dart';

class SessionActionButtons extends StatelessWidget {
  final SessionDetailModel? detail;
  const SessionActionButtons({super.key, this.detail});

  @override
  Widget build(BuildContext context) {
    final detail = this.detail;
    if (detail == null) return const SizedBox.shrink();

    final sessionCtrl = Get.find<SessionController>();

    return Obx(() {
      final bookmarked = sessionCtrl.isBookmarkedById(detail.id);

      return Row(
        children: [
          GestureDetector(
            onTap: () => sessionCtrl.toggleBookmark(detail.id),
            child: Container(
              padding: EdgeInsets.all(10.sp),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(8.r),
                border: Border.all(
                  color: bookmarked ? context.primaryTheme : context.strokeLight,
                ),
              ),
              child: CustomImage(
                bookmarked
                    ? "assets/svg/icons/bookmark_fill.svg"
                    : "assets/svg/icons/bookmark.svg",
                color: bookmarked ? context.primaryTheme : context.ghost,
                width: 20.sp,
                height: 20.sp,
              ),
            ),
          ),
          SizedBox(width: 12.w),
          Expanded(
            child: Obx(() {
              final briefcaseCtrl = Get.find<BriefcaseController>();
              final hasNoteLocally = briefcaseCtrl.notes.any(
                (n) => n.noteType == 'Session' && n.entityId == detail.id,
              );
              final match = briefcaseCtrl.notes.firstWhereOrNull(
                (n) => n.noteType == 'Session' && n.entityId == detail.id,
              );
              final initialNoteText = match?.noteText;

              return GestureDetector(
                onTap: () {
                  addNoteBottomSheet(
                    child: AddNoteBottomSheet(
                      noteType: 'Session',
                      entityId: detail.id,
                      entityName: detail.title,
                      entityRole: detail.sessionPlace.isNotEmpty
                          ? detail.sessionPlace
                          : 'Community Hall',
                      entityImage: '',
                      initialNoteText: initialNoteText,
                    ),
                  );
                },
                child: Container(
                  height: 44.sp,
                  decoration: BoxDecoration(
                    color: hasNoteLocally
                        ? context.primaryFocused
                        : context.tertiaryText,
                    borderRadius: BorderRadius.circular(8.r),
                    border: Border.all(color: context.primaryTheme),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      CustomImage(
                        "assets/svg/icons/add_note.svg",
                        color: context.primaryTheme,
                      ),
                      SizedBox(width: 8.w),
                      Text(
                        hasNoteLocally ? "View Note" : "Add Note",
                        style: context.buttonMediumBold?.copyWith(
                          color: context.primaryTheme,
                        ),
                      ),
                    ],
                  ),
                ),
              );
            }),
          ),
          SizedBox(width: 12.w),
          Expanded(
            child: GestureDetector(
              onTap: () async {
                final url = sessionCtrl.calendarUrlFor(detail);
                if (url == null) {
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
              },
              child: Container(
                height: 44.sp,
                decoration: BoxDecoration(
                  color: Colors.transparent,
                  borderRadius: BorderRadius.circular(8.r),
                  border: Border.all(color: context.primaryTheme),
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    CustomImage(
                      "assets/svg/icons/calender_add.svg",
                      color: context.primaryTheme,
                    ),
                    SizedBox(width: 8.w),
                    Flexible(
                      child: Text(
                        "Add to Calendar",
                        style: context.buttonMediumBold?.copyWith(
                          color: context.primaryTheme,
                          fontSize: 12.sp,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      );
    });
  }
}
