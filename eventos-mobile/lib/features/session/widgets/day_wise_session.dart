import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import '../session_controller.dart';
import 'session_day_item.dart';
import '../../../../utils/extension/theme_ext.dart';

class DayWiseSession extends StatelessWidget {
  const DayWiseSession({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<SessionController>();

    return Obx(() {
      final schedules = controller.activeDaySchedules;

      if (schedules.isEmpty) {
        return SliverToBoxAdapter(
          child: Padding(
            padding: EdgeInsets.symmetric(vertical: 40.h, horizontal: 16.w),
            child: Center(
              child: Text(
                "No sessions found for this day.",
                style: context.bodyRegular?.copyWith(color: context.caption),
              ),
            ),
          ),
        );
      }

      return SliverList(
        delegate: SliverChildBuilderDelegate(
          (context, index) {
            return SessionDayItem(session: schedules[index]);
          },
          childCount: schedules.length,
        ),
      );
    });
  }
}
