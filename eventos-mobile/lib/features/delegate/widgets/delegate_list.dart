import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../utils/extension/theme_ext.dart';
import '../delegate_controller.dart';
import 'delegate_card.dart';

class DelegateList extends StatelessWidget {
  const DelegateList({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<DelegateController>();
    return Obx(() {
      final delegates = controller.filteredDelegates;

      if (delegates.isEmpty) {
        return SliverFillRemaining(
          hasScrollBody: false,
          child: Center(
            child: Padding(
              padding: EdgeInsets.all(24.w),
              child: Text(
                controller.searchKey.value.trim().isEmpty &&
                        !controller.hasActiveFilters
                    ? 'No delegates found'
                    : 'No delegates match your search',
                style: context.bodyRegular?.copyWith(color: context.caption),
                textAlign: TextAlign.center,
              ),
            ),
          ),
        );
      }

      return SliverList(
        delegate: SliverChildBuilderDelegate(
          (context, index) {
            if (index >= delegates.length) return null;
            return DelegateCard(delegate: delegates[index]);
          },
          childCount: delegates.length,
        ),
      );
    });
  }
}
