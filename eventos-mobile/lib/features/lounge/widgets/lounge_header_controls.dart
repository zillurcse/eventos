import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../models/lounge_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_image.dart';
import '../lounge_controller.dart';

/// Classic / Cozy switch + search (classic) or kind tabs (cozy).
class LoungeHeaderControls extends StatelessWidget {
  const LoungeHeaderControls({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<LoungeController>();

    return SliverToBoxAdapter(
      child: Padding(
        padding: EdgeInsets.fromLTRB(16.w, 12.h, 16.w, 4.h),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Obx(() {
              final mode = controller.viewMode.value;
              return Container(
                height: 40.h,
                padding: EdgeInsets.all(3.w),
                decoration: BoxDecoration(
                  color: const Color(0xFFF1F2F6),
                  borderRadius: BorderRadius.circular(10.r),
                ),
                child: Row(
                  children: [
                    _modeTab(
                      context,
                      label: 'Classic Lounge',
                      selected: mode == LoungeViewMode.classic,
                      onTap: () =>
                          controller.setViewMode(LoungeViewMode.classic),
                    ),
                    _modeTab(
                      context,
                      label: 'Cozy Lounge',
                      selected: mode == LoungeViewMode.cozy,
                      onTap: () => controller.setViewMode(LoungeViewMode.cozy),
                    ),
                  ],
                ),
              );
            }),
            SizedBox(height: 12.h),
            Obx(() {
              if (controller.viewMode.value == LoungeViewMode.classic) {
                return _classicSearchRow(context, controller);
              }
              return _cozyKindTabs(context, controller);
            }),
          ],
        ),
      ),
    );
  }

  Widget _modeTab(
    BuildContext context, {
    required String label,
    required bool selected,
    required VoidCallback onTap,
  }) {
    return Expanded(
      child: GestureDetector(
        onTap: onTap,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 160),
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: selected ? Colors.white : Colors.transparent,
            borderRadius: BorderRadius.circular(8.r),
            boxShadow: selected
                ? [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.06),
                      blurRadius: 6,
                      offset: const Offset(0, 1),
                    ),
                  ]
                : null,
          ),
          child: Text(
            label,
            style: context.bodyRegular?.copyWith(
              fontWeight: FontWeight.w700,
              color: selected ? context.primaryTheme : context.caption,
            ),
          ),
        ),
      ),
    );
  }

  Widget _classicSearchRow(BuildContext context, LoungeController controller) {
    return Row(
      children: [
        Expanded(
          child: Container(
            height: 40.h,
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(8.r),
              border: Border.all(color: context.stroke),
            ),
            child: Row(
              children: [
                SizedBox(width: 12.w),
                CustomImage(
                  'assets/svg/icons/search.svg',
                  height: 20.sp,
                  color: context.ghost,
                ),
                SizedBox(width: 10.w),
                Expanded(
                  child: TextField(
                    controller: controller.searchController,
                    onChanged: controller.setSearchKey,
                    decoration: InputDecoration(
                      border: InputBorder.none,
                      isDense: true,
                      contentPadding: EdgeInsets.symmetric(vertical: 10.h),
                      hintText: 'Search...',
                      hintStyle: context.bodyRegular?.copyWith(
                        color: context.ghost,
                      ),
                    ),
                  ),
                ),
                Obx(
                  () => controller.searchKey.value.isNotEmpty
                      ? GestureDetector(
                          onTap: controller.clearSearch,
                          child: Padding(
                            padding: EdgeInsets.symmetric(horizontal: 8.w),
                            child: Icon(
                              Icons.close,
                              size: 18.sp,
                              color: context.ghost,
                            ),
                          ),
                        )
                      : const SizedBox.shrink(),
                ),
              ],
            ),
          ),
        ),
        SizedBox(width: 10.w),
        PopupMenuButton<LoungeTableKind>(
          onSelected: controller.setKind,
          offset: Offset(0, 45.h),
          color: Colors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(8.r),
          ),
          itemBuilder: (_) => [
            for (final kind in LoungeTableKind.values)
              PopupMenuItem(
                value: kind,
                child: Obx(
                  () => Text(
                    _kindTitle(kind),
                    style: context.bodyRegular?.copyWith(
                      color: controller.selectedKind.value == kind
                          ? context.primaryTheme
                          : context.body,
                      fontWeight: controller.selectedKind.value == kind
                          ? FontWeight.w700
                          : FontWeight.w500,
                    ),
                  ),
                ),
              ),
          ],
          child: Container(
            height: 40.h,
            width: 40.w,
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(8.r),
              border: Border.all(color: context.stroke),
            ),
            child: Icon(
              Icons.swap_vert,
              size: 20.sp,
              color: context.primaryTheme,
            ),
          ),
        ),
      ],
    );
  }

  Widget _cozyKindTabs(BuildContext context, LoungeController controller) {
    return Obx(() {
      final items = [
        (LoungeTableKind.attendee, 'Attendees'),
        (LoungeTableKind.exhibitor, 'Exhibitors'),
        (LoungeTableKind.sponsor, 'Sponsors'),
      ];
      return SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: Row(
          children: items.map((item) {
            final selected = controller.selectedKind.value == item.$1;
            return Padding(
              padding: EdgeInsets.only(right: 8.w),
              child: GestureDetector(
                onTap: () => controller.setKind(item.$1),
                child: Container(
                  padding: EdgeInsets.symmetric(
                    horizontal: 14.w,
                    vertical: 8.h,
                  ),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(8.r),
                    border: Border.all(
                      color: selected
                          ? context.primaryTheme
                          : context.strokeLight,
                      width: selected ? 1.5 : 1,
                    ),
                  ),
                  child: Text(
                    item.$2,
                    style: context.bodyRegular?.copyWith(
                      color: selected ? context.primaryTheme : context.caption,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ),
            );
          }).toList(),
        ),
      );
    });
  }

  String _kindTitle(LoungeTableKind kind) => switch (kind) {
        LoungeTableKind.attendee => 'Attendees',
        LoungeTableKind.exhibitor => 'Exhibitors',
        LoungeTableKind.sponsor => 'Sponsors',
      };
}
