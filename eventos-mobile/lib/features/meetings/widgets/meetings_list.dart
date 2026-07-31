import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../utils/extension/theme_ext.dart';
import '../meetings_controller.dart';
import 'meeting_card.dart';

class MeetingsList extends StatelessWidget {
  const MeetingsList({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<MeetingsController>();

    return Obx(() {
      final list = controller.filteredMeetings;

      if (controller.meetings.isEmpty) {
        return SliverFillRemaining(
          hasScrollBody: false,
          child: Center(
            child: Padding(
              padding: EdgeInsets.all(24.w),
              child: Text(
                'No meetings yet. Request one to start networking.',
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
                'No meetings match your filters.',
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
            (_, index) => MeetingCard(meeting: list[index]),
            childCount: list.length,
          ),
        ),
      );
    });
  }
}
