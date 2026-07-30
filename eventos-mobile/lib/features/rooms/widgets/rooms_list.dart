import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../utils/extension/theme_ext.dart';
import '../rooms_controller.dart';
import 'room_card.dart';

class RoomsList extends StatelessWidget {
  const RoomsList({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<RoomsController>();

    return Obx(() {
      final list = controller.filteredRooms;

      if (controller.rooms.isEmpty) {
        return SliverFillRemaining(
          hasScrollBody: false,
          child: Center(
            child: Padding(
              padding: EdgeInsets.all(24.w),
              child: Text(
                'No rooms are open right now. Check back soon.',
                textAlign: TextAlign.center,
                style: context.bodyRegular?.copyWith(color: context.caption),
              ),
            ),
          ),
        );
      }

      if (list.isEmpty) {
        return SliverFillRemaining(
          hasScrollBody: false,
          child: Center(
            child: Padding(
              padding: EdgeInsets.all(24.w),
              child: Text(
                'No rooms match your search.',
                textAlign: TextAlign.center,
                style: context.bodyRegular?.copyWith(color: context.caption),
              ),
            ),
          ),
        );
      }

      return SliverPadding(
        padding: EdgeInsets.fromLTRB(16.w, 12.h, 16.w, 24.h),
        sliver: SliverList(
          delegate: SliverChildBuilderDelegate(
            (_, index) => RoomCard(room: list[index]),
            childCount: list.length,
          ),
        ),
      );
    });
  }
}
