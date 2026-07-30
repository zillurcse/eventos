import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../models/room_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../utils/helpers/bottom_sheets.dart';
import '../../../widgets/custom_button.dart';
import '../../../widgets/custom_input.dart';

void showRoomAccessCodeSheet({
  required BreakoutRoom room,
  required ValueChanged<String> onJoin,
}) {
  showMoreBottomSheet(
    child: RoomAccessCodeSheet(
      room: room,
      onSubmit: (code) {
        Get.back();
        onJoin(code);
      },
      onCancel: () => Get.back(),
    ),
  );
}

class RoomAccessCodeSheet extends StatefulWidget {
  final BreakoutRoom room;
  final ValueChanged<String> onSubmit;
  final VoidCallback onCancel;

  const RoomAccessCodeSheet({
    super.key,
    required this.room,
    required this.onSubmit,
    required this.onCancel,
  });

  @override
  State<RoomAccessCodeSheet> createState() => _RoomAccessCodeSheetState();
}

class _RoomAccessCodeSheetState extends State<RoomAccessCodeSheet> {
  final _controller = TextEditingController();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          'Enter access code',
          style: context.h2?.copyWith(fontWeight: FontWeight.w800),
        ),
        SizedBox(height: 6.h),
        Text(
          '"${widget.room.name}" is a private room. Enter the code shared by the organizer.',
          style: context.bodyRegular?.copyWith(color: context.caption),
        ),
        SizedBox(height: 16.h),
        CustomInput(
          controller: _controller,
          hint: 'Access code',
          height: 48.h,
        ),
        SizedBox(height: 20.h),
        Row(
          children: [
            Expanded(
              child: Button.roundedText(
                text: 'Cancel',
                height: 44,
                radius: 10,
                backgroundColor: Colors.white,
                onBackgroundColor: context.body,
                borderColor: context.stroke,
                onTap: widget.onCancel,
              ),
            ),
            SizedBox(width: 12.w),
            Expanded(
              child: Button.roundedText(
                text: 'Join room',
                height: 44,
                radius: 10,
                onTap: _submit,
              ),
            ),
          ],
        ),
        SizedBox(height: MediaQuery.paddingOf(context).bottom + 8.h),
      ],
    );
  }

  void _submit() {
    final code = _controller.text.trim();
    if (code.isEmpty) return;
    widget.onSubmit(code);
  }
}
