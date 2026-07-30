import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../utils/extension/theme_ext.dart';
import '../lounge_controller.dart';
import 'lounge_classic_card.dart';
import 'lounge_cozy_card.dart';

class LoungeTablesList extends StatelessWidget {
  const LoungeTablesList({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<LoungeController>();

    return Obx(() {
      if (!controller.enabled.value) {
        return SliverFillRemaining(
          hasScrollBody: false,
          child: Center(
            child: Padding(
              padding: EdgeInsets.all(24.w),
              child: Text(
                "The networking lounge isn't open for this event yet.",
                textAlign: TextAlign.center,
                style: context.bodyRegular?.copyWith(color: context.caption),
              ),
            ),
          ),
        );
      }

      final list = controller.filteredTables;
      if (list.isEmpty) {
        final kind = controller.selectedKind.value.name;
        return SliverFillRemaining(
          hasScrollBody: false,
          child: Center(
            child: Padding(
              padding: EdgeInsets.all(24.w),
              child: Text(
                controller.searchKey.value.trim().isNotEmpty
                    ? 'No tables match your search.'
                    : 'No $kind tables in the lounge yet.',
                textAlign: TextAlign.center,
                style: context.bodyRegular?.copyWith(color: context.caption),
              ),
            ),
          ),
        );
      }

      final cozy = controller.viewMode.value == LoungeViewMode.cozy;

      return SliverPadding(
        padding: EdgeInsets.fromLTRB(16.w, 12.h, 16.w, 24.h),
        sliver: SliverList(
          delegate: SliverChildBuilderDelegate(
            (_, index) {
              final table = list[index];
              return cozy
                  ? LoungeCozyCard(table: table)
                  : LoungeClassicCard(table: table);
            },
            childCount: list.length,
          ),
        ),
      );
    });
  }
}
