import 'package:expouse/utils/extension/theme_ext.dart';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../widgets/custom_button.dart';
import '../../features/briefcase/briefcase_controller.dart';
import '../../features/speaker/speaker_controller.dart';
import '../../features/session/session_controller.dart';

class AddNoteBottomSheet extends StatefulWidget {
  final String noteType; // 'Attendee' | 'Speaker' | 'Session'
  final int? entityId;
  final String entityName;
  final String entityRole;
  final String entityImage;
  final String? initialNoteText;

  const AddNoteBottomSheet({
    super.key,
    this.noteType = 'Session',
    this.entityId,
    this.entityName = 'General',
    this.entityRole = 'Event Participant',
    this.entityImage = '',
    this.initialNoteText,
  });

  @override
  State<AddNoteBottomSheet> createState() => _AddNoteBottomSheetState();
}

class _AddNoteBottomSheetState extends State<AddNoteBottomSheet> {
  late final TextEditingController _noteController;

  @override
  void initState() {
    super.initState();
    _noteController = TextEditingController(text: widget.initialNoteText);
  }

  @override
  void dispose() {
    _noteController.dispose();
    super.dispose();
  }

  bool _isSaving = false;

  void _saveNote() async {
    final text = _noteController.text.trim();
    if (text.isEmpty) {
      Get.rawSnackbar(message: "Please write something first.");
      return;
    }

    setState(() {
      _isSaving = true;
    });

    try {
      if (widget.noteType == 'Speaker' && widget.entityId != null) {
        final speakerCtrl = Get.find<SpeakerController>();
        final success = await speakerCtrl.addOrUpdateSpeakerNote(widget.entityId!, text);
        if (success) {
          final briefcaseCtrl = Get.find<BriefcaseController>();
          briefcaseCtrl.addNote(
            noteType: widget.noteType,
            entityId: widget.entityId,
            entityName: widget.entityName,
            entityRole: widget.entityRole,
            entityImage: widget.entityImage,
            noteText: text,
          );
          if (mounted) {
            Navigator.of(context).pop();
          }
          Get.rawSnackbar(message: "Note saved successfully.");
        }
      } else if (widget.noteType == 'Session' && widget.entityId != null) {
        final sessionCtrl = Get.find<SessionController>();
        final success = await sessionCtrl.addOrUpdateSessionNote(widget.entityId!, text);
        if (success) {
          final briefcaseCtrl = Get.find<BriefcaseController>();
          briefcaseCtrl.addNote(
            noteType: widget.noteType,
            entityId: widget.entityId,
            entityName: widget.entityName,
            entityRole: widget.entityRole,
            entityImage: widget.entityImage,
            noteText: text,
          );
          if (mounted) {
            Navigator.of(context).pop();
          }
          Get.rawSnackbar(message: "Note saved successfully.");
        }
      } else {
        final briefcaseCtrl = Get.find<BriefcaseController>();
        briefcaseCtrl.addNote(
          noteType: widget.noteType,
          entityId: widget.entityId,
          entityName: widget.entityName,
          entityRole: widget.entityRole,
          entityImage: widget.entityImage,
          noteText: text,
        );
        if (mounted) {
          Navigator.of(context).pop();
        }
        Get.rawSnackbar(message: "Note saved to briefcase successfully.");
      }
    } finally {
      if (mounted) {
        setState(() {
          _isSaving = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(height: 12.h),
            Text("Add a note", style: context.h3),
            SizedBox(height: 16.h),
            RichText(
              text: TextSpan(
                children: [
                  TextSpan(
                    text: "You can view saved notes from",
                    style: context.bodyRegular?.copyWith(color: context.caption),
                  ),
                  TextSpan(
                    text: " My Briefcase.",
                    style: context.bodyRegular?.copyWith(
                      color: context.primaryTheme,
                    ),
                  ),
                ],
              ),
            ),
            SizedBox(height: 12.h),
            Container(
              padding: EdgeInsets.symmetric(horizontal: 12.w),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(12.r),
                border: Border.all(color: context.stroke),
              ),
              child: TextField(
                controller: _noteController,
                maxLines: 8,
                enabled: !_isSaving,
                decoration: InputDecoration(
                  hintText: "Start taking a note",
                  hintStyle: context.bodyRegular?.copyWith(color: context.ghost),
                  border: InputBorder.none,
                ),
              ),
            ),
            SizedBox(height: 12.h),
            Row(
              children: [
                Expanded(
                  child: Button.roundedText(
                    text: "Cancel",
                    backgroundColor: context.primaryFocused,
                    onBackgroundColor: context.primaryTheme,
                    onTap: _isSaving ? () {} : () {
                      Navigator.of(context).pop();
                    },
                  ),
                ),
                SizedBox(width: 12.w),
                Expanded(
                  child: Button.roundedText(
                    text: "Save",
                    onTap: _isSaving ? () {} : _saveNote,
                  ),
                ),
              ],
            ),
            SizedBox(height: MediaQuery.of(context).viewInsets.bottom.h),
          ],
        ),
        if (_isSaving)
          Positioned.fill(
            child: Container(
              color: context.tertiaryText.withValues(alpha: 0.7),
              child: Center(
                child: CircularProgressIndicator(
                  color: context.primaryTheme,
                ),
              ),
            ),
          ),
      ],
    );
  }
}
