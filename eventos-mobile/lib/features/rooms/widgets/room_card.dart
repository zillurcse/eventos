import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../models/room_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_button.dart';
import '../../../widgets/custom_image.dart';
import '../rooms_controller.dart';

class RoomCard extends StatelessWidget {
  final BreakoutRoom room;

  const RoomCard({super.key, required this.room});

  static const _avatarShow = 4;

  @override
  Widget build(BuildContext context) {
    final ctrl = Get.find<RoomsController>();
    final shown = room.occupants.take(_avatarShow).toList();
    final moreCount = (room.occupied - shown.length).clamp(0, 999);
    final subtitle = room.startedLabel ??
        ((room.description?.isNotEmpty ?? false) ? room.description! : null);

    return Obx(() {
      final joining = ctrl.joiningId.value == room.id;

      return Container(
        margin: EdgeInsets.only(bottom: 14.h),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14.r),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.05),
              blurRadius: 12,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        clipBehavior: Clip.antiAlias,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            GestureDetector(
              onTap: () => ctrl.openRoomDetail(room),
              behavior: HitTestBehavior.opaque,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  AspectRatio(
                    aspectRatio: 16 / 9,
                    child: Stack(
                      fit: StackFit.expand,
                      children: [
                        (room.posterUrl?.isNotEmpty ?? false)
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
                        Positioned(
                          top: 12.h,
                          left: 12.w,
                          child: room.isPrivate
                              ? _lockBadge(context)
                              : _openBadge(context),
                        ),
                      ],
                    ),
                  ),
                  Padding(
                    padding: EdgeInsets.fromLTRB(14.w, 12.h, 14.w, 0),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          room.name,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style:
                              context.h2?.copyWith(fontWeight: FontWeight.w800),
                        ),
                        if (subtitle != null) ...[
                          SizedBox(height: 4.h),
                          Text(
                            subtitle,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: context.bodyRegular?.copyWith(
                              color: context.caption,
                            ),
                          ),
                        ],
                        if (shown.isNotEmpty) ...[
                          SizedBox(height: 12.h),
                          Row(
                            children: [
                              for (var i = 0; i < shown.length; i++)
                                Align(
                                  widthFactor: 0.7,
                                  child: Container(
                                    decoration: BoxDecoration(
                                      shape: BoxShape.circle,
                                      border: Border.all(
                                        color: Colors.white,
                                        width: 2.sp,
                                      ),
                                    ),
                                    child: CustomImage(
                                      shown[i].avatarUrl ?? '',
                                      width: 28.sp,
                                      height: 28.sp,
                                      isCircle: true,
                                      avatar: true,
                                    ),
                                  ),
                                ),
                              if (moreCount > 0) ...[
                                SizedBox(width: 10.w),
                                Text(
                                  '+$moreCount More',
                                  style: context.bodyRegular?.copyWith(
                                    color: context.primaryTheme,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              ],
                            ],
                          ),
                        ],
                      ],
                    ),
                  ),
                ],
              ),
            ),
            Padding(
              padding: EdgeInsets.fromLTRB(14.w, 14.h, 14.w, 14.h),
              child: joining
                  ? SizedBox(
                      height: 40.h,
                      child: Center(
                        child: SizedBox(
                          width: 22.sp,
                          height: 22.sp,
                          child: CircularProgressIndicator(
                            strokeWidth: 2.5,
                            color: context.primaryTheme,
                          ),
                        ),
                      ),
                    )
                  : Button.roundedText(
                      text: 'Join Room',
                      height: 40,
                      radius: 10,
                      onTap: () => ctrl.onJoinPressed(room),
                    ),
            ),
          ],
        ),
      );
    });
  }

  Widget _openBadge(BuildContext context) {
    return Container(
      padding: EdgeInsets.symmetric(horizontal: 10.w, vertical: 5.h),
      decoration: BoxDecoration(
        color: context.primaryTheme,
        borderRadius: BorderRadius.circular(8.r),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 7.sp,
            height: 7.sp,
            decoration: const BoxDecoration(
              color: Colors.white,
              shape: BoxShape.circle,
            ),
          ),
          SizedBox(width: 6.w),
          Text(
            'Open',
            style: TextStyle(
              color: Colors.white,
              fontSize: 11.sp,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }

  Widget _lockBadge(BuildContext context) {
    return Container(
      padding: EdgeInsets.all(6.sp),
      decoration: BoxDecoration(
        color: context.primaryTheme,
        shape: BoxShape.circle,
      ),
      child: Icon(Icons.lock_outline, size: 14.sp, color: Colors.white),
    );
  }
}
