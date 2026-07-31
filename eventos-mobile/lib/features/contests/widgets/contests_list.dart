import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../utils/extension/theme_ext.dart';
import '../contests_controller.dart';
import 'contest_card.dart';

class ContestsList extends StatelessWidget {
  const ContestsList({super.key});

  @override
  Widget build(BuildContext context) {
    final ctrl = Get.find<ContestsController>();

    return Obx(() {
      final items = ctrl.shown;
      if (items.isEmpty) {
        return SliverFillRemaining(
          hasScrollBody: false,
          child: Center(
            child: Padding(
              padding: EdgeInsets.symmetric(horizontal: 32.w),
              child: Text(
                ctrl.filter.value == 'all'
                    ? 'No contests yet.'
                    : 'No ${ctrl.filter.value} contests.',
                style: context.bodyRegular?.copyWith(
                  color: context.caption,
                ),
                textAlign: TextAlign.center,
              ),
            ),
          ),
        );
      }

      return SliverPadding(
        padding: EdgeInsets.fromLTRB(16.w, 8.h, 16.w, 24.h),
        sliver: SliverList(
          delegate: SliverChildBuilderDelegate(
            (context, index) {
              final contest = items[index];
              return ContestCard(
                contest: contest,
                onTap: () => ctrl.openContest(contest),
              );
            },
            childCount: items.length,
          ),
        ),
      );
    });
  }
}
