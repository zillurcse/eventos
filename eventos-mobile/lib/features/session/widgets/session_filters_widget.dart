import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../utils/extension/theme_ext.dart';
import '../session_controller.dart';

class SessionFiltersWidget extends StatelessWidget {
  const SessionFiltersWidget({super.key});

  Widget _buildFilterDropdown<T>({
    required BuildContext context,
    required String placeholder,
    required T? selectedValue,
    required List<DropdownMenuItem<T>> items,
    required ValueChanged<T?> onChanged,
    required VoidCallback onClear,
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
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          DropdownButtonHideUnderline(
            child: DropdownButton<T>(
              value: selectedValue,
              icon: Icon(
                Icons.keyboard_arrow_down,
                color: context.caption,
                size: 16.sp,
              ),
              hint: Text(
                placeholder,
                style: context.bodyRegular?.copyWith(
                  color: context.caption,
                  fontSize: 13.sp,
                ),
              ),
              style: context.bodyRegular?.copyWith(
                color: context.heading,
                fontSize: 13.sp,
              ),
              onChanged: onChanged,
              items: items,
              dropdownColor: Colors.white,
              borderRadius: BorderRadius.circular(12.r),
            ),
          ),
        ],
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
              // Saved only chip
              GestureDetector(
                onTap: controller.toggleSavedOnly,
                child: Container(
                  margin: EdgeInsets.only(right: 8.w),
                  padding: EdgeInsets.symmetric(horizontal: 12.w),
                  height: 38.h,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: controller.savedOnly.value
                        ? context.primaryTheme.withValues(alpha: 0.12)
                        : context.backgroundColor,
                    borderRadius: BorderRadius.circular(12.r),
                    border: Border.all(
                      color: controller.savedOnly.value
                          ? context.primaryTheme
                          : context.strokeLight,
                      width: 1.r,
                    ),
                  ),
                  child: Text(
                    controller.bookmarkedSessionCount > 0
                        ? 'Saved only (${controller.bookmarkedSessionCount})'
                        : 'Saved only',
                    style: context.bodyRegular?.copyWith(
                      color: controller.savedOnly.value
                          ? context.primaryTheme
                          : context.caption,
                      fontSize: 13.sp,
                      fontWeight: controller.savedOnly.value
                          ? FontWeight.w600
                          : FontWeight.w400,
                    ),
                  ),
                ),
              ),

              // Tracks filter dropdown
              _buildFilterDropdown<int>(
                context: context,
                placeholder: "Tracks",
                selectedValue: controller.selectedTrackId.value,
                onChanged: controller.selectTrack,
                onClear: () => controller.selectTrack(null),
                items: controller.tracksList.map((track) {
                  return DropdownMenuItem<int>(
                    value: track.id,
                    child: Text(track.title),
                  );
                }).toList(),
              ),

              // Tags filter dropdown
              _buildFilterDropdown<String>(
                context: context,
                placeholder: "Tags",
                selectedValue: controller.selectedTag.value,
                onChanged: controller.selectTag,
                onClear: () => controller.selectTag(null),
                items: controller.tagsList.map((tag) {
                  return DropdownMenuItem<String>(
                    value: tag,
                    child: Text(tag),
                  );
                }).toList(),
              ),

              // Time Zone filter dropdown
              _buildFilterDropdown<String>(
                context: context,
                placeholder: "Time Zone",
                selectedValue: controller.selectedTimezone.value,
                onChanged: controller.selectTimezone,
                onClear: () => controller.selectTimezone(null),
                items: [
                  if (controller.timezoneData.containsKey('event_timezone'))
                    DropdownMenuItem<String>(
                      value: 'event_timezone|${controller.timezoneData['event_timezone']}',
                      child: const Text('Event'),
                    ),
                  if (controller.timezoneData.containsKey('current_timezone'))
                    DropdownMenuItem<String>(
                      value: 'current_timezone|${controller.timezoneData['current_timezone']}',
                      child: const Text('Current'),
                    ),
                  if (controller.timezoneData.containsKey('user_timezone'))
                    DropdownMenuItem<String>(
                      value: 'user_timezone|${controller.timezoneData['user_timezone']}',
                      child: const Text('User'),
                    ),
                ],
              ),

              // Speakers filter dropdown
              _buildFilterDropdown<int>(
                context: context,
                placeholder: "Speakers",
                selectedValue: controller.selectedSpeakerId.value,
                onChanged: controller.selectSpeaker,
                onClear: () => controller.selectSpeaker(null),
                items: controller.speakersList.map((speaker) {
                  return DropdownMenuItem<int>(
                    value: speaker.id,
                    child: Text(speaker.name),
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
