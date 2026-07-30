import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

import '../../../models/room_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../utils/helpers/bottom_sheets.dart';
import '../../../widgets/custom_button.dart';
import '../../../widgets/custom_image.dart';

void showRoomDetailBottomSheet({
  required BreakoutRoom room,
  required VoidCallback onJoin,
}) {
  showMoreBottomSheet(
    child: RoomDetailBottomSheet(room: room, onJoin: onJoin),
  );
}

class RoomDetailBottomSheet extends StatelessWidget {
  final BreakoutRoom room;
  final VoidCallback onJoin;

  const RoomDetailBottomSheet({
    super.key,
    required this.room,
    required this.onJoin,
  });

  static const _hostShow = 4;

  @override
  Widget build(BuildContext context) {
    final hosts = room.occupants.take(_hostShow).toList();
    final startsOn = room.startsOnLabel;
    final description = room.description?.trim() ?? '';

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisSize: MainAxisSize.min,
      children: [
        ClipRRect(
          borderRadius: BorderRadius.circular(12.r),
          child: AspectRatio(
            aspectRatio: 16 / 9,
            child: (room.posterUrl?.isNotEmpty ?? false)
                ? CustomImage(
                    room.posterUrl!,
                    fit: BoxFit.cover,
                    width: double.infinity,
                    height: double.infinity,
                  )
                : ColoredBox(
                    color: const Color(0xFFEEF0F3),
                    child: Center(
                      child: Icon(
                        Icons.meeting_room_outlined,
                        size: 40.sp,
                        color: context.ghost,
                      ),
                    ),
                  ),
          ),
        ),
        SizedBox(height: 16.h),
        Text(
          room.name,
          style: context.h1?.copyWith(fontWeight: FontWeight.w800),
        ),
        if (startsOn != null) ...[
          SizedBox(height: 6.h),
          Text(
            startsOn,
            style: context.bodyRegular?.copyWith(
              color: context.primaryTheme,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
        if (description.isNotEmpty) ...[
          SizedBox(height: 12.h),
          Text(
            description,
            style: context.bodyRegular?.copyWith(
              color: context.caption,
              height: 1.45,
            ),
          ),
        ],
        if (hosts.isNotEmpty) ...[
          SizedBox(height: 20.h),
          Center(
            child: Text(
              'Hosted by',
              style: context.bodyRegular?.copyWith(color: context.caption),
            ),
          ),
          SizedBox(height: 12.h),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              for (final host in hosts)
                Padding(
                  padding: EdgeInsets.symmetric(horizontal: 8.w),
                  child: SizedBox(
                    width: 64.w,
                    child: Column(
                      children: [
                        CustomImage(
                          host.avatarUrl ?? '',
                          width: 52.sp,
                          height: 52.sp,
                          radius: 10.r,
                          fit: BoxFit.cover,
                          avatar: true,
                        ),
                        SizedBox(height: 6.h),
                        Text(
                          _truncateName(host.name),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          textAlign: TextAlign.center,
                          style: context.bodyRegular?.copyWith(
                            fontSize: 11.sp,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
            ],
          ),
        ],
        SizedBox(height: 24.h),
        Button.roundedText(
          text: 'Join Room',
          height: 48,
          radius: 12,
          onTap: onJoin,
        ),
        SizedBox(height: MediaQuery.paddingOf(context).bottom + 8.h),
      ],
    );
  }

  String _truncateName(String name) {
    final trimmed = name.trim();
    if (trimmed.length <= 10) return trimmed;
    return '${trimmed.substring(0, 9)}...';
  }
}
