import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';

import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_image.dart';
import '../session_controller.dart';
import '../../../../models/session_day_model.dart';

class DaySelectorWidget extends StatelessWidget {
  const DaySelectorWidget({super.key});

  String _formatDateLabel(SessionDayModel day) {
    if (day.dateLabel.isNotEmpty) return day.dateLabel;
    try {
      final parsed = DateTime.tryParse(day.date);
      if (parsed != null) {
        return DateFormat('dd MMM').format(parsed);
      }
    } catch (_) {}
    return day.title;
  }

  String _formatDayName(SessionDayModel day) {
    if (day.dayName.isNotEmpty) {
      return day.dayName.length >= 3 ? day.dayName.substring(0, 3) : day.dayName;
    }
    try {
      final parsed = DateTime.tryParse(day.date);
      if (parsed != null) {
        return DateFormat('E').format(parsed);
      }
    } catch (_) {}
    return '';
  }

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<SessionController>();

    return Obx(() {
      final days = controller.days;
      if (days.isEmpty) {
        return const SliverToBoxAdapter(child: SizedBox.shrink());
      }

      final activeIndex = controller.activeDayIndex.value;

      return SliverToBoxAdapter(
        child: Padding(
          padding: EdgeInsets.only(bottom: 4.h),
          child: Row(
            children: [
              // ── Horizontal Days Selector ──
              Expanded(
                child: SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  padding: EdgeInsets.symmetric(horizontal: 16.w),
                  child: Row(
                    children: List.generate(days.length, (index) {
                      final day = days[index];
                      final isSelected = index == activeIndex;
                      final dateLabel = _formatDateLabel(day);
                      final dayName = _formatDayName(day);

                      return GestureDetector(
                        onTap: () => controller.setActiveDayIndex(index),
                        child: Container(
                          width: 56.w,
                          height: 52.h,
                          margin: EdgeInsets.only(right: 12.w),
                          decoration: BoxDecoration(
                            color: isSelected ? context.primaryTheme : Colors.transparent,
                            borderRadius: BorderRadius.circular(8.r),
                          ),
                          padding: EdgeInsets.symmetric(horizontal: 2.w, vertical: 2.h),
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              FittedBox(
                                fit: BoxFit.scaleDown,
                                child: Text(
                                  dateLabel,
                                  maxLines: 1,
                                  style: context.buttonMediumBold?.copyWith(
                                    color: isSelected ? context.tertiaryText : context.heading,
                                  ),
                                ),
                              ),
                              if (dayName.isNotEmpty) ...[
                                SizedBox(height: 2.h),
                                Text(
                                  dayName,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: context.specialCaption2?.copyWith(
                                    color: isSelected ? context.tertiaryText : context.caption,
                                    fontSize: 11.sp,
                                  ),
                                ),
                              ],
                            ],
                          ),
                        ),
                      );
                    }),
                  ),
                ),
              ),

              // ── Divider ──
              Container(
                height: 44.h,
                width: 1.w,
                color: context.strokeLight,
              ),
              SizedBox(width: 8.w),

              // ── Calendar Button ──
              GestureDetector(
                onTap: () async {
                  final activeDay = controller.activeDay;
                  final initialDate = activeDay != null
                      ? (DateTime.tryParse(activeDay.date) ?? DateTime.now())
                      : DateTime.now();

                  // Determine selectable date range from controller days
                  DateTime firstDate = DateTime(2020);
                  DateTime lastDate = DateTime(2030);
                  if (days.isNotEmpty) {
                    final parsedDates = days
                        .map((d) => DateTime.tryParse(d.date))
                        .where((d) => d != null)
                        .cast<DateTime>()
                        .toList();
                    if (parsedDates.isNotEmpty) {
                      parsedDates.sort();
                      firstDate = parsedDates.first;
                      lastDate = parsedDates.last;
                    }
                  }

                  final selectedDate = await showDatePicker(
                    context: context,
                    initialDate: initialDate,
                    firstDate: firstDate,
                    lastDate: lastDate,
                    selectableDayPredicate: (date) {
                      return days.any((day) {
                        final parsed = DateTime.tryParse(day.date);
                        if (parsed == null) return false;
                        return parsed.year == date.year &&
                            parsed.month == date.month &&
                            parsed.day == date.day;
                      });
                    },
                    builder: (context, child) {
                      return Theme(
                        data: Theme.of(context).copyWith(
                          colorScheme: ColorScheme.light(
                            primary: context.primaryTheme,
                            onPrimary: Colors.white,
                            onSurface: context.heading,
                          ),
                        ),
                        child: child!,
                      );
                    },
                  );

                  if (selectedDate != null) {
                    final matchIndex = days.indexWhere((day) {
                      final parsed = DateTime.tryParse(day.date);
                      if (parsed == null) return false;
                      return parsed.year == selectedDate.year &&
                          parsed.month == selectedDate.month &&
                          parsed.day == selectedDate.day;
                    });
                    if (matchIndex != -1) {
                      controller.setActiveDayIndex(matchIndex);
                    }
                  }
                },
                child: Container(
                  width: 54.w,
                  height: 52.h,
                  margin: EdgeInsets.only(right: 16.w),
                  decoration: BoxDecoration(
                    color: context.primaryTheme,
                    borderRadius: BorderRadius.circular(8.r),
                  ),
                  alignment: Alignment.center,
                  child: CustomImage(
                    "assets/svg/icons/calender.svg",
                    color: Colors.white,
                    height: 22.sp,
                    width: 22.sp,
                  ),
                ),
              ),
            ],
          ),
        ),
      );
    });
  }
}
