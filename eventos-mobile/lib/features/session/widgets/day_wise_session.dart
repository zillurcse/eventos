import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import '../session_controller.dart';
import '../../../../widgets/cards/session_card.dart';
import '../../../../widgets/cards/session_card_option_1.dart';
import '../../../../widgets/cards/session_card_option_2.dart';
import '../../../../utils/extension/theme_ext.dart';

class DayWiseSession extends StatelessWidget {
  const DayWiseSession({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<SessionController>();

    return Obx(() {
      final schedules = controller.activeDaySchedules;
      final currentViewOption = controller.viewOption.value;

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
            final session = schedules[index];
            return Padding(
              padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 8.h),
              child: _buildCard(
                currentViewOption,
                session,
                true,
              ),
            );
          },
          childCount: schedules.length,
        ),
      );
    });
  }

  Widget _buildCard(int viewOption, dynamic session, bool fullWidth) {
    if (viewOption == 1) {
      return SessionCardOption1(
        session: session,
        fullWidth: fullWidth,
        title: session.title,
        startTime: session.startTime,
        endTime: session.endTime,
        location: session.sessionPlace,
        speakerImageUrls: session.speakers.map<String>((sp) => sp.imageUrl as String).toList(),
      );
    } else if (viewOption == 2) {
      return SessionCardOption2(
        session: session,
        fullWidth: fullWidth,
        title: session.title,
        startTime: session.startTime,
        endTime: session.endTime,
        dayLabel: session.day.title,
        location: session.sessionPlace,
        speakerImageUrls: session.speakers.map<String>((sp) => sp.imageUrl as String).toList(),
      );
    } else {
      return SessionCard(
        session: session,
        isOnGoing: false,
        fullWidth: fullWidth,
        title: session.title,
        startTime: session.startTime,
        endTime: session.endTime,
        dayLabel: session.day.title,
        logoUrl: session.logoUrl,
        speakerImageUrls: session.speakers.map<String>((sp) => sp.imageUrl as String).toList(),
      );
    }
  }
}
