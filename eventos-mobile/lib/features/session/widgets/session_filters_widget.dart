import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../utils/extension/theme_ext.dart';
import '../session_controller.dart';

class SessionFiltersWidget extends StatelessWidget {
  const SessionFiltersWidget({super.key});

  Widget _buildFilterDropdown<T>({
    required BuildContext context,
    required String displayLabel,
    required T? selectedValue,
    required List<PopupMenuEntry<T>> items,
    required ValueChanged<T?> onChanged,
  }) {
    return Container(
      margin: EdgeInsets.only(right: 8.w),
      padding: EdgeInsets.symmetric(horizontal: 12.w),
      decoration: BoxDecoration(
        color: context.backgroundColor,
        borderRadius: BorderRadius.circular(12.r),
        border: Border.all(color: context.strokeLight, width: 1.r),
      ),
      height: 38.h,
      alignment: Alignment.center,
      child: PopupMenuButton<T>(
        initialValue: selectedValue,
        onSelected: onChanged,
        color: Colors.white,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12.r)),
        itemBuilder: (context) => items,
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            ConstrainedBox(
              constraints: BoxConstraints(maxWidth: 120.w),
              child: Text(
                displayLabel,
                overflow: TextOverflow.ellipsis,
                style: context.bodyRegular?.copyWith(
                  color: selectedValue != null ? context.heading : context.caption,
                  fontSize: 13.sp,
                ),
              ),
            ),
            Padding(
              padding: EdgeInsets.only(left: 4.w),
              child: Icon(
                Icons.keyboard_arrow_down,
                color: context.caption,
                size: 16.sp,
              ),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<SessionController>();

    return SliverToBoxAdapter(
      child: Obx(() {
        if (controller.days.isEmpty) {
          return const SizedBox.shrink();
        }

        return SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          padding: EdgeInsets.fromLTRB(16.w, 4.h, 16.w, 12.h),
          child: Row(
            children: [
              // Reset Filters
              if (controller.hasActiveFilters)
                GestureDetector(
                  onTap: controller.resetFilters,
                  child: Container(
                    margin: EdgeInsets.only(right: 8.w),
                    padding: EdgeInsets.symmetric(horizontal: 12.w),
                    height: 38.h,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: context.redErrorLight,
                      borderRadius: BorderRadius.circular(12.r),
                      border: Border.all(color: context.redError, width: 1.r),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.close, color: context.redError, size: 16.sp),
                        SizedBox(width: 4.w),
                        Text(
                          'Reset',
                          style: context.bodyRegular?.copyWith(
                            color: context.redError,
                            fontSize: 13.sp,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),

              // Tracks filter dropdown
              if (controller.tracksList.isNotEmpty)
                _buildFilterDropdown<int>(
                  context: context,
                  displayLabel: controller.selectedTrackId.value != null
                      ? controller.tracksList.firstWhere((t) => t.id == controller.selectedTrackId.value).title
                      : "Tracks",
                  selectedValue: controller.selectedTrackId.value,
                  onChanged: controller.selectTrack,
                  items: controller.tracksList.map((track) {
                    return PopupMenuItem<int>(
                      value: track.id,
                      child: Text(
                        track.title,
                        style: context.bodyRegular?.copyWith(color: Colors.black),
                      ),
                    );
                  }).toList(),
                ),

              // Tags filter dropdown
              if (controller.tagsList.isNotEmpty)
                _buildFilterDropdown<String>(
                  context: context,
                  displayLabel: controller.selectedTag.value ?? "Tags",
                  selectedValue: controller.selectedTag.value,
                  onChanged: controller.selectTag,
                  items: controller.tagsList.map((tag) {
                    return PopupMenuItem<String>(
                      value: tag,
                      child: Text(
                        tag,
                        style: context.bodyRegular?.copyWith(color: Colors.black),
                      ),
                    );
                  }).toList(),
                ),

              // Time Zone filter dropdown
              if (controller.timezoneData.containsKey('event_timezone') || 
                  controller.timezoneData.containsKey('current_timezone') || 
                  controller.timezoneData.containsKey('user_timezone'))
                _buildFilterDropdown<String>(
                  context: context,
                  displayLabel: controller.selectedTimezone.value != null
                      ? (controller.selectedTimezone.value!.startsWith('event_timezone') ? 'Event' : 
                         controller.selectedTimezone.value!.startsWith('current_timezone') ? 'Current' : 'User')
                      : "Time Zone",
                  selectedValue: controller.selectedTimezone.value,
                  onChanged: controller.selectTimezone,
                  items: [
                    if (controller.timezoneData.containsKey('event_timezone'))
                      PopupMenuItem<String>(
                        value: 'event_timezone|${controller.timezoneData['event_timezone']}',
                        child: Text('Event', style: context.bodyRegular?.copyWith(color: Colors.black)),
                      ),
                    if (controller.timezoneData.containsKey('current_timezone'))
                      PopupMenuItem<String>(
                        value: 'current_timezone|${controller.timezoneData['current_timezone']}',
                        child: Text('Current', style: context.bodyRegular?.copyWith(color: Colors.black)),
                      ),
                    if (controller.timezoneData.containsKey('user_timezone'))
                      PopupMenuItem<String>(
                        value: 'user_timezone|${controller.timezoneData['user_timezone']}',
                        child: Text('User', style: context.bodyRegular?.copyWith(color: Colors.black)),
                      ),
                  ],
                ),

              // Speakers filter dropdown
              if (controller.speakersList.isNotEmpty)
                _buildFilterDropdown<int>(
                  context: context,
                  displayLabel: controller.selectedSpeakerId.value != null
                      ? controller.speakersList.firstWhere((s) => s.id == controller.selectedSpeakerId.value).name
                      : "Speakers",
                  selectedValue: controller.selectedSpeakerId.value,
                  onChanged: controller.selectSpeaker,
                  items: controller.speakersList.map((speaker) {
                    return PopupMenuItem<int>(
                      value: speaker.id,
                      child: Text(
                        speaker.name,
                        style: context.bodyRegular?.copyWith(color: Colors.black),
                      ),
                    );
                  }).toList(),
                ),
            ],
          ),
        );
      }),
    );
  }
}
