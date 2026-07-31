import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../utils/extension/theme_ext.dart';
import '../contests_controller.dart';

class ContestsPhaseFilter extends StatelessWidget {
  const ContestsPhaseFilter({super.key});

  static const _filters = [
    ('all', 'All'),
    ('ongoing', 'Ongoing'),
    ('upcoming', 'Upcoming'),
    ('ended', 'Ended'),
  ];

  @override
  Widget build(BuildContext context) {
    final ctrl = Get.find<ContestsController>();

    return SliverToBoxAdapter(
      child: Obx(() {
        final counts = ctrl.counts;
        final selected = ctrl.filter.value;
        return SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          padding: EdgeInsets.fromLTRB(16.w, 12.h, 16.w, 4.h),
          child: Row(
            children: _filters.map((f) {
              final key = f.$1;
              final label = f.$2;
              final count = counts[key] ?? 0;
              final on = selected == key;
              return Padding(
                padding: EdgeInsets.only(right: 8.w),
                child: GestureDetector(
                  onTap: () => ctrl.setFilter(key),
                  child: Container(
                    padding:
                        EdgeInsets.symmetric(horizontal: 12.w, vertical: 8.h),
                    decoration: BoxDecoration(
                      color: on ? context.primaryTheme : Colors.white,
                      borderRadius: BorderRadius.circular(20.r),
                      border: Border.all(
                        color: on
                            ? context.primaryTheme
                            : const Color(0xFFE2E8F0),
                      ),
                    ),
                    child: Text(
                      '$label ($count)',
                      style: context.bodyRegular?.copyWith(
                        color: on ? Colors.white : const Color(0xFF64748B),
                        fontWeight: FontWeight.w600,
                        fontSize: 13.sp,
                      ),
                    ),
                  ),
                ),
              );
            }).toList(),
          ),
        );
      }),
    );
  }
}
