import 'package:expouse/utils/extension/theme_ext.dart';
import 'package:expouse/widgets/custom_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:intl/intl.dart';
import '../../../models/chat_room_model.dart';

class ChatListTile extends StatelessWidget {
  final ChatRoomModel room;
  final VoidCallback onTap;
  /// Whether this room has truly-new messages (session-aware, from controller).
  final bool isUnread;

  const ChatListTile({
    super.key,
    required this.room,
    required this.onTap,
    this.isUnread = false,
  });

  @override
  Widget build(BuildContext context) {
    final bool hasUnread = isUnread;

    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: Container(
        margin: EdgeInsets.fromLTRB(16.sp, 0, 16.sp, 8.sp),
        padding: EdgeInsets.all(12.sp),
        decoration: BoxDecoration(
          // Highlight unread rooms with the primaryFocused background
          color: hasUnread ? context.primaryFocused : context.tertiaryText,
          borderRadius: BorderRadius.circular(8.r),
        ),
        child: Row(
          children: [
            // Avatar
            CustomImage(
              room.partner?.profilePhotoUrl ?? "",
              height: 40.sp,
              width: 40.sp,
              radius: 8.r,
              fit: BoxFit.cover,
            ),
            SizedBox(width: 12.w),

            // Name + Message
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    room.partner?.name ?? "Unknown User",
                    style: context.titleRegular?.copyWith(
                      color: context.heading,
                      // Bold name for unread rooms
                      fontWeight: hasUnread ? FontWeight.w700 : FontWeight.w500,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  SizedBox(height: 4.h),
                  Text(
                    room.latestMessage?.body ??
                        (room.latestMessage?.attachType == 'image'
                            ? "Image"
                            : room.latestMessage?.attachType == 'pdf'
                            ? "PDF Document"
                            : ""),
                    style: context.bodyRegular?.copyWith(
                      color: context.caption,
                      // Slightly bold message for unread rooms
                      fontWeight: hasUnread ? FontWeight.w600 : FontWeight.w400,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
              ),
            ),
            SizedBox(width: 12.w),

            // Time + Unread badge
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  room.latestMessage != null ? _formatDate(room.latestMessage!.updatedAt) : "",
                  style: context.specialCaption1?.copyWith(
                    color: context.caption,
                    // Bold time for unread rooms
                    fontWeight: hasUnread ? FontWeight.w600 : FontWeight.w400,
                  ),
                ),
                SizedBox(height: 4.h),
                if (hasUnread)
                  Container(
                    padding: EdgeInsets.symmetric(horizontal: 8.w, vertical: 3.h),
                    decoration: BoxDecoration(
                      color: context.primaryTheme,
                      borderRadius: BorderRadius.circular(12.r),
                    ),
                    child: Text(
                      'New',
                      style: TextStyle(
                        color: context.tertiaryText,
                        fontWeight: FontWeight.w700,
                        fontSize: 9.sp,
                        height: 1,
                      ),
                    ),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  String _formatDate(DateTime? date) {
    if (date == null) return "";
    try {
      final localDate = date.toLocal();
      final now = DateTime.now();

      final isToday = localDate.year == now.year &&
          localDate.month == now.month &&
          localDate.day == now.day;

      if (isToday) {
        return DateFormat('hh:mm a').format(localDate);
      }

      final isYesterday = localDate.year == now.year &&
          localDate.month == now.month &&
          localDate.day == now.day - 1;

      if (isYesterday) return "Yesterday";

      return DateFormat('dd/MM/yy').format(localDate);
    } catch (e) {
      return "";
    }
  }
}

