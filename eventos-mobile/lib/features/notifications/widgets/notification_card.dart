import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../../../utils/extension/theme_ext.dart';

enum NotificationIconType { avatar, video }

class NotificationCard extends StatelessWidget {
  final bool isUnread;
  final NotificationIconType iconType;
  final String avatarUrl; // Used if iconType is avatar
  final Widget? icon; // Used if iconType is not avatar
  final List<TextSpan> messageSpans;
  final String time;
  final bool hasActions;
  final VoidCallback? onAccept;
  final VoidCallback? onDecline;

  const NotificationCard({
    super.key,
    this.isUnread = false,
    this.iconType = NotificationIconType.avatar,
    this.avatarUrl = '',
    this.icon,
    required this.messageSpans,
    required this.time,
    this.hasActions = false,
    this.onAccept,
    this.onDecline,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: EdgeInsets.only(bottom: 12.h),
      padding: EdgeInsets.all(16.w),
      decoration: BoxDecoration(
        color: isUnread 
            ? context.primaryTheme.withValues(alpha: 0.05) 
            : context.backgroundColor,
        borderRadius: BorderRadius.circular(12.r),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _buildIconSection(context),
              SizedBox(width: 12.w),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    RichText(
                      text: TextSpan(
                        style: context.bodyRegular?.copyWith(
                          color: context.heading,
                          height: 1.4,
                        ),
                        children: messageSpans,
                      ),
                    ),
                    SizedBox(height: 6.h),
                    Text(
                      time,
                      style: context.bodyRegular?.copyWith(
                        color: context.ghost,
                        fontSize: 12.sp,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          if (hasActions) ...[
            SizedBox(height: 16.h),
            Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                TextButton(
                  onPressed: onDecline,
                  child: Text(
                    "Decline",
                    style: context.titleRegular?.copyWith(
                      color: context.primaryTheme,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
                SizedBox(width: 12.w),
                ElevatedButton(
                  onPressed: onAccept,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: context.primaryTheme,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(8.r),
                    ),
                    padding: EdgeInsets.symmetric(horizontal: 24.w, vertical: 12.h),
                    elevation: 0,
                  ),
                  child: Text(
                    "Accept",
                    style: context.titleRegular?.copyWith(
                      color: Colors.white,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildIconSection(BuildContext context) {
    if (iconType == NotificationIconType.avatar) {
      return Stack(
        clipBehavior: Clip.none,
        children: [
          Container(
            width: 44.w,
            height: 44.w,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: context.stroke,
              image: avatarUrl.isNotEmpty
                  ? DecorationImage(
                      image: NetworkImage(avatarUrl),
                      fit: BoxFit.cover,
                    )
                  : null,
            ),
          ),
          if (isUnread)
            Positioned(
              top: -2.h,
              right: -2.w,
              child: Container(
                width: 14.w,
                height: 14.w,
                decoration: BoxDecoration(
                  color: context.primaryTheme,
                  shape: BoxShape.circle,
                  border: Border.all(color: Colors.white, width: 2),
                ),
                child: Center(
                  child: Icon(
                    Icons.check,
                    color: Colors.white,
                    size: 8.sp,
                  ),
                ),
              ),
            ),
        ],
      );
    } else {
      return Container(
        width: 44.w,
        height: 44.w,
        decoration: BoxDecoration(
          color: context.primaryTheme,
          borderRadius: BorderRadius.circular(12.r),
        ),
        child: Center(
          child: icon ?? Icon(Icons.videocam_outlined, color: Colors.white, size: 24.sp),
        ),
      );
    }
  }
}
