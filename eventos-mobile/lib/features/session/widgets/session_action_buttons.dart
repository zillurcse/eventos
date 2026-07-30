import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../../../widgets/custom_image.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../utils/bottom_sheets/add_note_bottom_sheet.dart';
import '../../../utils/helpers/bottom_sheets.dart';

import 'package:get/get.dart';
import '../../../models/session_detail_response_model.dart';
import '../../briefcase/briefcase_controller.dart';

class SessionActionButtons extends StatefulWidget {
  final SessionDetailModel? detail;
  const SessionActionButtons({super.key, this.detail});

  @override
  State<SessionActionButtons> createState() => _SessionActionButtonsState();
}

class _SessionActionButtonsState extends State<SessionActionButtons> {
  bool _isBookmarked = false;
  bool _isRegistered = false;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        // Bookmark Button
        GestureDetector(
          onTap: () {
            setState(() {
              _isBookmarked = !_isBookmarked;
            });
          },
          child: Container(
            padding: EdgeInsets.all(10.sp),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(8.r),
              border: Border.all(
                color: _isBookmarked ? context.primaryTheme : context.strokeLight,
              ),
            ),
            child: CustomImage(
              _isBookmarked ? "assets/svg/icons/bookmark_fill.svg" : "assets/svg/icons/bookmark.svg",
              color: _isBookmarked ? context.primaryTheme : context.ghost,
              width: 20.sp,
              height: 20.sp,
            ),
          ),
        ),
        SizedBox(width: 12.w),
        // Add Note Button
        Expanded(
          child: Obx(() {
            final briefcaseCtrl = Get.find<BriefcaseController>();
            final hasNoteLocally = widget.detail?.id != null && briefcaseCtrl.notes.any(
              (n) => n.noteType == 'Session' && n.entityId == widget.detail?.id,
            );
            
            final match = briefcaseCtrl.notes.firstWhereOrNull(
              (n) => n.noteType == 'Session' && n.entityId == widget.detail?.id,
            );
            final initialNoteText = match?.noteText;

            return GestureDetector(
              onTap: () {
                addNoteBottomSheet(
                  child: AddNoteBottomSheet(
                    noteType: 'Session',
                    entityId: widget.detail?.id,
                    entityName: widget.detail?.title ?? 'Session',
                    entityRole: widget.detail?.sessionPlace ?? 'Community Hall',
                    entityImage: '',
                    initialNoteText: initialNoteText,
                  ),
                );
              },
              child: Container(
                height: 44.sp,
                decoration: BoxDecoration(
                  color: hasNoteLocally ? context.primaryFocused : context.tertiaryText,
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
        // Add to Calendar Button
        Expanded(
          child: GestureDetector(
            onTap: () {
              setState(() {
                _isRegistered = !_isRegistered;
              });
            },
            child: Container(
              height: 44.sp,
              decoration: BoxDecoration(
                color: _isRegistered ? context.primaryTheme : Colors.transparent,
                borderRadius: BorderRadius.circular(8.r),
                border: Border.all(color: context.primaryTheme),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  CustomImage(
                    "assets/svg/icons/calender_add.svg",
                    color: _isRegistered ? Colors.white : context.primaryTheme,
                  ),
                  SizedBox(width: 8.w),
                  Text(
                    _isRegistered ? "Added" : "Add to Calendar",
                    style: context.buttonMediumBold?.copyWith(
                      color: _isRegistered ? Colors.white : context.primaryTheme,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }
}
