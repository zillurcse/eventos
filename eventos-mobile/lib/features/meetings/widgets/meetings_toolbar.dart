import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_button.dart';
import '../../../widgets/custom_image.dart';
import '../meetings_controller.dart';

class MeetingsToolbar extends StatelessWidget {
  const MeetingsToolbar({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<MeetingsController>();

    return SliverToBoxAdapter(
      child: Padding(
        padding: EdgeInsets.fromLTRB(16.w, 12.h, 16.w, 4.h),
        child: Column(
          children: [
            Row(
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
                              contentPadding:
                                  EdgeInsets.symmetric(vertical: 10.h),
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
                                    padding:
                                        EdgeInsets.symmetric(horizontal: 8.w),
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
                PopupMenuButton<String>(
                  onSelected: controller.setSortType,
                  offset: Offset(0, 45.h),
                  color: Colors.white,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(8.r),
                  ),
                  itemBuilder: (_) {
                    final options = [
                      {'key': 'newest', 'label': 'Newest first'},
                      {'key': 'oldest', 'label': 'Oldest first'},
                      {'key': 'name', 'label': 'By name'},
                    ];
                    return options.map((opt) {
                      return PopupMenuItem<String>(
                        value: opt['key'],
                        child: Obx(() {
                          final selected =
                              controller.sortType.value == opt['key'];
                          return Text(
                            opt['label']!,
                            style: context.bodyRegular?.copyWith(
                              color: selected
                                  ? context.primaryTheme
                                  : context.body,
                              fontWeight: selected
                                  ? FontWeight.w700
                                  : FontWeight.w500,
                            ),
                          );
                        }),
                      );
                    }).toList();
                  },
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
                SizedBox(width: 10.w),
                GestureDetector(
                  onTap: () => _showFilterSheet(context, controller),
                  child: Obx(() {
                    final active = controller.selectedStatuses.isNotEmpty ||
                        controller.selectedDirection.value != 'all';
                    return Container(
                      height: 40.h,
                      width: 40.w,
                      decoration: BoxDecoration(
                        color: active
                            ? context.primaryFocused
                            : Colors.white,
                        borderRadius: BorderRadius.circular(8.r),
                        border: Border.all(
                          color: active
                              ? context.primaryTheme
                              : context.stroke,
                        ),
                      ),
                      child: Center(
                        child: CustomImage(
                          'assets/svg/icons/filter.svg',
                          color: context.primaryTheme,
                        ),
                      ),
                    );
                  }),
                ),
              ],
            ),
            SizedBox(height: 12.h),
            Row(
              children: [
                Expanded(
                  child: Obx(
                    () => Row(
                      children: [
                        Text(
                          'Show past meetings',
                          style: context.bodyRegular?.copyWith(
                            color: context.body,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                        SizedBox(width: 8.w),
                        Switch.adaptive(
                          value: controller.showPast.value,
                          onChanged: controller.setShowPast,
                          activeTrackColor: context.primaryTheme,
                          materialTapTargetSize:
                              MaterialTapTargetSize.shrinkWrap,
                        ),
                      ],
                    ),
                  ),
                ),
                Obx(
                  () => Opacity(
                    opacity: controller.canRequest ? 1 : 0.5,
                    child: Button.roundedText(
                      text: '+ Meeting',
                      onTap: controller.openRequestMeeting,
                      height: 36,
                      width: 110.w,
                      radius: 8,
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 13.sp,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  void _showFilterSheet(
    BuildContext context,
    MeetingsController controller,
  ) {
    Get.bottomSheet(
      Container(
        padding: EdgeInsets.fromLTRB(20.w, 16.h, 20.w, 28.h),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(16.r)),
        ),
        child: SafeArea(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                  width: 36.w,
                  height: 4.h,
                  decoration: BoxDecoration(
                    color: context.stroke,
                    borderRadius: BorderRadius.circular(2.r),
                  ),
                ),
              ),
              SizedBox(height: 16.h),
              Row(
                children: [
                  Text(
                    'Filter',
                    style: context.h2?.copyWith(fontWeight: FontWeight.w800),
                  ),
                  const Spacer(),
                  TextButton(
                    onPressed: () {
                      controller.clearFilters();
                      Get.back();
                    },
                    child: Text(
                      'Clear',
                      style: context.bodyRegular?.copyWith(
                        color: context.primaryTheme,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ],
              ),
              SizedBox(height: 12.h),
              Text(
                'Type',
                style: context.bodyRegular?.copyWith(
                  fontWeight: FontWeight.w700,
                  color: context.heading,
                ),
              ),
              SizedBox(height: 8.h),
              Obx(() {
                final options = [
                  ('all', 'All'),
                  ('incoming', 'Received'),
                  ('outgoing', 'Sent'),
                ];
                return Wrap(
                  spacing: 8.w,
                  runSpacing: 8.h,
                  children: options.map((o) {
                    final on = controller.selectedDirection.value == o.$1;
                    return _FilterChip(
                      label: o.$2,
                      selected: on,
                      onTap: () => controller.setDirection(o.$1),
                    );
                  }).toList(),
                );
              }),
              SizedBox(height: 16.h),
              Text(
                'Status',
                style: context.bodyRegular?.copyWith(
                  fontWeight: FontWeight.w700,
                  color: context.heading,
                ),
              ),
              SizedBox(height: 8.h),
              Obx(() {
                final options = [
                  ('requested', 'Pending'),
                  ('confirmed', 'Accepted'),
                  ('rejected', 'Rejected'),
                ];
                return Wrap(
                  spacing: 8.w,
                  runSpacing: 8.h,
                  children: options.map((o) {
                    final on = controller.selectedStatuses.contains(o.$1);
                    return _FilterChip(
                      label: o.$2,
                      selected: on,
                      onTap: () => controller.toggleStatus(o.$1),
                    );
                  }).toList(),
                );
              }),
              SizedBox(height: 20.h),
              Button.roundedText(
                text: 'Apply',
                onTap: () => Get.back(),
                height: 44,
              ),
            ],
          ),
        ),
      ),
      isScrollControlled: true,
    );
  }
}

class _FilterChip extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;

  const _FilterChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: EdgeInsets.symmetric(horizontal: 14.w, vertical: 8.h),
        decoration: BoxDecoration(
          color: selected ? context.primaryTheme : Colors.white,
          borderRadius: BorderRadius.circular(20.r),
          border: Border.all(
            color: selected ? context.primaryTheme : context.stroke,
          ),
        ),
        child: Text(
          label,
          style: context.bodyRegular?.copyWith(
            color: selected ? Colors.white : context.body,
            fontWeight: FontWeight.w600,
            fontSize: 13.sp,
          ),
        ),
      ),
    );
  }
}
