import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

import 'package:get/get.dart';

import '../../../models/speaker_model.dart';
import '../../../utils/bottom_sheets/add_note_bottom_sheet.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../utils/helpers/bottom_sheets.dart';
import '../../../widgets/custom_image.dart';
import '../../bookmarks/bookmark_controller.dart';
import '../../briefcase/briefcase_controller.dart';
import '../speaker_controller.dart';

class SpeakerCardActions extends StatelessWidget {
  /// The speaker this action bar belongs to. Optional to keep backwards
  /// compatibility with [SpeakerDetails] which currently passes no model.
  final SpeakerItemModel? speaker;
  final SpeakerDetailModel? speakerDetail;

  const SpeakerCardActions({
    super.key,
    this.speaker,
    this.speakerDetail,
  });

  @override
  Widget build(BuildContext context) {
    final speakerId = speakerDetail?.id ?? speaker?.id;
    final speakerName = speakerDetail?.name ?? speaker?.name ?? 'Speaker';
    final speakerDesignation =
        speakerDetail?.designation ?? speaker?.designation ?? 'Guest Speaker';
    final speakerImage = speakerDetail?.image ?? speaker?.image ?? '';

    // Determine if a note exists
    final hasNoteLocally = speakerId != null &&
        Get.find<BriefcaseController>().notes.any(
              (n) => n.noteType == 'Speaker' && n.entityId == speakerId,
            );
    final haveNotes =
        speakerDetail?.haveNotes ?? speaker?.haveNotes ?? hasNoteLocally;

    // Determine initialNoteText
    String? initialNoteText;
    if (speakerDetail != null && speakerDetail!.notes.isNotEmpty) {
      initialNoteText = speakerDetail!.notes.first.note;
    } else if (speaker != null && speaker!.notes.isNotEmpty) {
      initialNoteText = speaker!.notes.first.note;
    }

    if (initialNoteText == null && speakerId != null) {
      final briefcaseCtrl = Get.find<BriefcaseController>();
      final match = briefcaseCtrl.notes.firstWhereOrNull(
        (n) => n.noteType == 'Speaker' && n.entityId == speakerId,
      );
      initialNoteText = match?.noteText;
    }

    return Padding(
      padding: EdgeInsets.only(top: 12.h),
      child: Row(
        children: [
          // Bookmark / loved toggle - driven by BookmarkController so the
          // icon updates immediately (API speakers never ship is_loved).
          Obx(() {
            final isLoved = () {
              if (speakerId == null) return false;
              if (Get.isRegistered<BookmarkController>()) {
                final bm = Get.find<BookmarkController>();
                // Touch Rx list so Obx rebuilds after toggle.
                bm.bookmarkedSpeakers.length;
                return bm.isOnHashed('speaker', speakerId);
              }
              return speakerDetail?.isLoved ?? speaker?.isLoved ?? false;
            }();

            return GestureDetector(
              onTap: () {
                if (speakerId != null) {
                  Get.find<SpeakerController>().toggleBookmark(speakerId);
                }
              },
              child: Container(
                padding: EdgeInsets.symmetric(
                  horizontal: 8.sp,
                  vertical: 6.sp,
                ),
                decoration: BoxDecoration(
                  color: context.tertiaryText,
                  borderRadius: BorderRadius.circular(8.r),
                  border: Border.all(
                    color: context.primaryTheme,
                  ),
                ),
                child: Icon(
                  isLoved ? Icons.bookmark : Icons.bookmark_border,
                  color: context.primaryTheme,
                  size: 26.sp,
                ),
              ),
            );
          }),
          SizedBox(width: 8.w),
          // Add Note
          Expanded(
            child: Obx(() {
              final hasNoteLocally = speakerId != null &&
                  Get.find<BriefcaseController>().notes.any(
                        (n) => n.noteType == 'Speaker' && n.entityId == speakerId,
                      );
              final haveNotes =
                  speakerDetail?.haveNotes ?? speaker?.haveNotes ?? hasNoteLocally;

              // Determine initialNoteText
              String? initialNoteText;
              if (speakerDetail != null && speakerDetail!.notes.isNotEmpty) {
                initialNoteText = speakerDetail!.notes.first.note;
              } else if (speaker != null && speaker!.notes.isNotEmpty) {
                initialNoteText = speaker!.notes.first.note;
              }

              if (initialNoteText == null && speakerId != null) {
                final match = Get.find<BriefcaseController>().notes.firstWhereOrNull(
                  (n) => n.noteType == 'Speaker' && n.entityId == speakerId,
                );
                initialNoteText = match?.noteText;
              }

              return GestureDetector(
                onTap: () {
                  addNoteBottomSheet(
                    child: AddNoteBottomSheet(
                      noteType: 'Speaker',
                      entityId: speakerId,
                      entityName: speakerName,
                      entityRole: speakerDesignation,
                      entityImage: speakerImage,
                      initialNoteText: initialNoteText,
                    ),
                  );
                },
                child: Container(
                  height: 42.sp,
                  decoration: BoxDecoration(
                    color:
                        haveNotes ? context.primaryFocused : context.tertiaryText,
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
                      SizedBox(width: 12.w),
                      Text(
                        haveNotes ? "View Note" : "Add Note",
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
          SizedBox(width: 8.w),
          // Chat
          Expanded(
            child: GestureDetector(
              onTap: () {
                if (speakerId == null) return;
                Get.find<SpeakerController>().startChat(
                  speakerId,
                  name: speakerName,
                  imageUrl: speakerImage.isEmpty ? null : speakerImage,
                );
              },
              child: Container(
                height: 42.sp,
                decoration: BoxDecoration(
                  color: context.tertiaryText,
                  borderRadius: BorderRadius.circular(8.r),
                  border: Border.all(color: context.primaryTheme),
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    CustomImage(
                      "assets/svg/icons/chat.svg",
                      color: context.primaryTheme,
                    ),
                    SizedBox(width: 12.w),
                    Text(
                      "Chat",
                      style: context.buttonMediumBold?.copyWith(
                        color: context.primaryTheme,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
