import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../utils/extension/theme_ext.dart';
import '../../../utils/theme/app_colors.dart';
import '../../../widgets/custom_button.dart';
import '../../../widgets/custom_image.dart';
import '../meeting_time_utils.dart';
import '../meetings_controller.dart';

class RequestMeetingFormView extends StatelessWidget {
  const RequestMeetingFormView({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<MeetingsController>();

    return Scaffold(
      backgroundColor: const Color(0xFFF7F7FB),
      body: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: EdgeInsets.fromLTRB(8.w, 4.h, 8.w, 0),
              child: Row(
                children: [
                  IconButton(
                    icon: Icon(Icons.arrow_back_ios_new, size: 18.sp),
                    onPressed: () => Get.back(),
                  ),
                  Expanded(
                    child: Text(
                      'Request a Meeting',
                      textAlign: TextAlign.center,
                      style: context.h2?.copyWith(
                        fontWeight: FontWeight.w800,
                        fontSize: 18.sp,
                      ),
                    ),
                  ),
                  SizedBox(width: 48.w),
                ],
              ),
            ),
            Expanded(
              child: Obx(() {
                final partner = controller.selectedPartner.value;
                if (partner == null) {
                  return const Center(child: Text('No partner selected'));
                }

                return SingleChildScrollView(
                  padding: EdgeInsets.fromLTRB(16.w, 8.h, 16.w, 24.h),
                  child: Container(
                    padding: EdgeInsets.all(16.w),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(16.r),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        _SelectedPartnerCard(
                          name: partner.name,
                          subtitle: partner.subtitle,
                          avatarUrl: partner.avatarUrl,
                          onChange: () => Get.back(),
                        ),
                        SizedBox(height: 18.h),
                        Text(
                          'Meeting Title',
                          style: context.bodyRegular?.copyWith(
                            fontWeight: FontWeight.w700,
                            color: context.heading,
                          ),
                        ),
                        SizedBox(height: 8.h),
                        TextField(
                          controller: controller.titleController,
                          maxLength: 200,
                          decoration: _inputDecoration(
                            context,
                            'Enter Meeting Title',
                          ).copyWith(counterText: ''),
                        ),
                        SizedBox(height: 16.h),
                        Text(
                          'Note',
                          style: context.bodyRegular?.copyWith(
                            fontWeight: FontWeight.w700,
                            color: context.heading,
                          ),
                        ),
                        SizedBox(height: 8.h),
                        TextField(
                          controller: controller.agendaController,
                          maxLength: 200,
                          maxLines: 4,
                          onChanged: controller.onAgendaChanged,
                          decoration: _inputDecoration(
                            context,
                            'Add a note for the invite.',
                          ).copyWith(
                            counterText: '',
                            contentPadding: EdgeInsets.fromLTRB(
                              14.w,
                              12.h,
                              14.w,
                              12.h,
                            ),
                          ),
                        ),
                        Align(
                          alignment: Alignment.centerRight,
                          child: Obx(
                            () => Text(
                              '${controller.agendaLength.value}/200 characters',
                              style: context.specialCaption2?.copyWith(
                                color: context.ghost,
                                fontSize: 11.sp,
                              ),
                            ),
                          ),
                        ),
                        if (controller.isIntelligent) ...[
                          SizedBox(height: 14.h),
                          Container(
                            padding: EdgeInsets.all(12.w),
                            decoration: BoxDecoration(
                              color: context.primaryFocused,
                              borderRadius: BorderRadius.circular(12.r),
                              border: Border.all(
                                color: context.primaryTheme
                                    .withValues(alpha: 0.2),
                              ),
                            ),
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Icon(
                                  Icons.auto_awesome,
                                  size: 18.sp,
                                  color: context.primaryTheme,
                                ),
                                SizedBox(width: 10.w),
                                Expanded(
                                  child: Text(
                                    'A table will be assigned automatically when your invite is accepted.',
                                    style: context.bodyRegular?.copyWith(
                                      color: context.body,
                                      fontSize: 12.sp,
                                      height: 1.4,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                        if (controller.needsLocation) ...[
                          SizedBox(height: 16.h),
                          Text(
                            'Location',
                            style: context.bodyRegular?.copyWith(
                              fontWeight: FontWeight.w700,
                              color: context.heading,
                            ),
                          ),
                          SizedBox(height: 8.h),
                          if (controller.locationOptions.isNotEmpty)
                            Wrap(
                              spacing: 8.w,
                              runSpacing: 8.h,
                              children: controller.locationOptions.map((place) {
                                final on =
                                    controller.locationText.value == place;
                                return GestureDetector(
                                  onTap: () => controller.pickLocation(place),
                                  child: Container(
                                    padding: EdgeInsets.symmetric(
                                      horizontal: 12.w,
                                      vertical: 8.h,
                                    ),
                                    decoration: BoxDecoration(
                                      color: on
                                          ? context.primaryTheme
                                          : const Color(0xFFF7F7FB),
                                      borderRadius: BorderRadius.circular(20.r),
                                      border: Border.all(
                                        color: on
                                            ? context.primaryTheme
                                            : context.stroke,
                                      ),
                                    ),
                                    child: Row(
                                      mainAxisSize: MainAxisSize.min,
                                      children: [
                                        Icon(
                                          Icons.location_on_outlined,
                                          size: 14.sp,
                                          color: on
                                              ? Colors.white
                                              : context.body,
                                        ),
                                        SizedBox(width: 4.w),
                                        Text(
                                          place,
                                          style:
                                              context.bodyRegular?.copyWith(
                                            color: on
                                                ? Colors.white
                                                : context.body,
                                            fontWeight: FontWeight.w600,
                                            fontSize: 12.sp,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                );
                              }).toList(),
                            ),
                          if (controller.locationOptions.isEmpty) ...[
                            SizedBox(height: 4.h),
                            TextField(
                              onChanged: (v) =>
                                  controller.locationText.value = v,
                              decoration: _inputDecoration(
                                context,
                                'e.g. Hall 4, Meeting Room 2',
                              ),
                            ),
                          ],
                        ],
                        SizedBox(height: 16.h),
                        Text(
                          'Pick a meeting slot',
                          style: context.bodyRegular?.copyWith(
                            fontWeight: FontWeight.w700,
                            color: context.heading,
                          ),
                        ),
                        SizedBox(height: 10.h),
                        if (controller.slotsLoading.value)
                          Padding(
                            padding: EdgeInsets.symmetric(vertical: 20.h),
                            child: const Center(
                              child: CircularProgressIndicator(strokeWidth: 2),
                            ),
                          )
                        else if (controller.useSlots) ...[
                          _DateTabs(
                            dates: controller.slotDates,
                            selected: controller.selectedDate.value,
                            onSelect: controller.pickDate,
                          ),
                          SizedBox(height: 12.h),
                          if (controller.daySlots.isEmpty)
                            Text(
                              'No slots on this day.',
                              style: context.bodyRegular?.copyWith(
                                color: context.ghost,
                              ),
                            )
                          else
                            _SlotGrid(
                              slots: controller.daySlots,
                              selected: controller.selectedSlot.value,
                              isBusy: controller.isBusy,
                              onSelect: controller.pickSlot,
                            ),
                        ] else
                          _FallbackTimePicker(controller: controller),
                        if (controller.formError.value.isNotEmpty) ...[
                          SizedBox(height: 12.h),
                          Text(
                            controller.formError.value,
                            style: context.bodyRegular?.copyWith(
                              color: redError,
                              fontSize: 13.sp,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                );
              }),
            ),
            Container(
              padding: EdgeInsets.fromLTRB(16.w, 10.h, 16.w, 16.h),
              color: Colors.white,
              child: SafeArea(
                top: false,
                child: Obx(() {
                  final sending = controller.sending.value;
                  return Row(
                    children: [
                      Expanded(
                        child: Button.roundedText(
                          text: 'Cancel',
                          onTap: () {
                            Get.back();
                            Get.back();
                          },
                          height: 48,
                          backgroundColor: context.primaryFocused,
                          onBackgroundColor: context.primaryTheme,
                          style: TextStyle(
                            color: context.primaryTheme,
                            fontSize: 15.sp,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                      SizedBox(width: 12.w),
                      Expanded(
                        flex: 2,
                        child: Button.roundedText(
                          text: sending
                              ? 'Sending…'
                              : 'Send Meeting Request',
                          onTap: sending
                              ? () {}
                              : () async {
                                  final ok = await controller.submitRequest();
                                  if (ok) {
                                    Get.back();
                                    Get.back();
                                  }
                                },
                          height: 48,
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 14.sp,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                    ],
                  );
                }),
              ),
            ),
          ],
        ),
      ),
    );
  }

  InputDecoration _inputDecoration(BuildContext context, String hint) {
    return InputDecoration(
      hintText: hint,
      hintStyle: context.bodyRegular?.copyWith(color: context.ghost),
      filled: true,
      fillColor: const Color(0xFFF7F7FB),
      contentPadding: EdgeInsets.symmetric(horizontal: 14.w, vertical: 12.h),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10.r),
        borderSide: BorderSide(color: context.stroke),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10.r),
        borderSide: BorderSide(color: context.stroke),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10.r),
        borderSide: BorderSide(color: context.primaryTheme),
      ),
    );
  }
}

class _SelectedPartnerCard extends StatelessWidget {
  final String name;
  final String subtitle;
  final String? avatarUrl;
  final VoidCallback onChange;

  const _SelectedPartnerCard({
    required this.name,
    required this.subtitle,
    this.avatarUrl,
    required this.onChange,
  });

  @override
  Widget build(BuildContext context) {
    final initial = name.isNotEmpty ? name[0].toUpperCase() : '?';

    return Container(
      padding: EdgeInsets.all(12.w),
      decoration: BoxDecoration(
        color: const Color(0xFFF7F7FB),
        borderRadius: BorderRadius.circular(12.r),
        border: Border.all(color: context.stroke),
      ),
      child: Row(
        children: [
          ClipOval(
            child: SizedBox(
              width: 44.sp,
              height: 44.sp,
              child: avatarUrl != null && avatarUrl!.isNotEmpty
                  ? CustomImage(
                      avatarUrl!,
                      fit: BoxFit.cover,
                      width: 44.sp,
                      height: 44.sp,
                    )
                  : ColoredBox(
                      color: context.primaryFocused,
                      child: Center(
                        child: Text(
                          initial,
                          style: TextStyle(
                            color: context.primaryTheme,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                    ),
            ),
          ),
          SizedBox(width: 12.w),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  name,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: context.bodyRegular?.copyWith(
                    fontWeight: FontWeight.w700,
                    color: context.heading,
                  ),
                ),
                if (subtitle.isNotEmpty) ...[
                  SizedBox(height: 2.h),
                  Text(
                    subtitle,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: context.specialCaption2?.copyWith(
                      color: context.caption,
                      fontSize: 12.sp,
                    ),
                  ),
                ],
              ],
            ),
          ),
          GestureDetector(
            onTap: onChange,
            child: Text(
              'Change',
              style: context.bodyRegular?.copyWith(
                color: context.primaryTheme,
                fontWeight: FontWeight.w700,
                fontSize: 13.sp,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _DateTabs extends StatelessWidget {
  final List<String> dates;
  final String selected;
  final ValueChanged<String> onSelect;

  const _DateTabs({
    required this.dates,
    required this.selected,
    required this.onSelect,
  });

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 64.h,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: dates.length,
        separatorBuilder: (_, __) => SizedBox(width: 8.w),
        itemBuilder: (_, index) {
          final date = dates[index];
          final on = selected == date;
          return GestureDetector(
            onTap: () => onSelect(date),
            child: Container(
              width: 64.w,
              alignment: Alignment.center,
              padding: EdgeInsets.symmetric(vertical: 6.h),
              decoration: BoxDecoration(
                color: on ? context.primaryFocused : Colors.white,
                borderRadius: BorderRadius.circular(10.r),
                border: Border.all(
                  color: on ? context.primaryTheme : context.stroke,
                ),
              ),
              child: Text(
                formatDateTab(date),
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: on ? context.primaryTheme : context.body,
                  fontWeight: FontWeight.w700,
                  fontSize: 11.sp,
                  height: 1.25,
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}

class _SlotGrid extends StatelessWidget {
  final List<String> slots;
  final String selected;
  final bool Function(String) isBusy;
  final ValueChanged<String> onSelect;

  const _SlotGrid({
    required this.slots,
    required this.selected,
    required this.isBusy,
    required this.onSelect,
  });

  @override
  Widget build(BuildContext context) {
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: slots.length,
      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 3,
        mainAxisSpacing: 8.h,
        crossAxisSpacing: 8.w,
        childAspectRatio: 2.4,
      ),
      itemBuilder: (_, index) {
        final slot = slots[index];
        final busy = isBusy(slot);
        final on = selected == slot;

        return GestureDetector(
          onTap: busy ? null : () => onSelect(slot),
          child: Container(
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: on
                  ? context.primaryFocused
                  : busy
                      ? const Color(0xFFF1F5F9)
                      : Colors.white,
              borderRadius: BorderRadius.circular(9.r),
              border: Border.all(
                color: on
                    ? context.primaryTheme
                    : busy
                        ? context.strokeLight
                        : context.stroke,
              ),
            ),
            child: Text(
              slot,
              style: TextStyle(
                color: on
                    ? context.primaryTheme
                    : busy
                        ? context.ghost
                        : context.heading,
                fontWeight: FontWeight.w600,
                fontSize: 11.sp,
                decoration: busy ? TextDecoration.lineThrough : null,
              ),
            ),
          ),
        );
      },
    );
  }
}

class _FallbackTimePicker extends StatelessWidget {
  final MeetingsController controller;

  const _FallbackTimePicker({required this.controller});

  List<String> get _timeOptions {
    final out = <String>[];
    for (var h = 0; h < 24; h++) {
      final hh = h.toString().padLeft(2, '0');
      out.add('$hh:00');
      out.add('$hh:30');
    }
    return out;
  }

  String _todayIso() {
    final d = DateTime.now();
    return '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    final dates = controller.availability.value?.dates ?? const <String>[];
    final today = _todayIso();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Proposed time (optional)',
          style: context.specialCaption2?.copyWith(color: context.ghost),
        ),
        SizedBox(height: 8.h),
        if (dates.isNotEmpty)
          SizedBox(
            height: 40.h,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: dates.length,
              separatorBuilder: (_, __) => SizedBox(width: 8.w),
              itemBuilder: (_, index) {
                final d = dates[index];
                final past = d.compareTo(today) < 0;
                final on = controller.fallbackDate.value == d;
                return GestureDetector(
                  onTap: past
                      ? null
                      : () {
                          controller.fallbackDate.value =
                              on ? '' : d;
                          controller.fallbackTime.value = '';
                        },
                  child: Container(
                    padding: EdgeInsets.symmetric(horizontal: 12.w),
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: on ? context.primaryFocused : Colors.white,
                      borderRadius: BorderRadius.circular(10.r),
                      border: Border.all(
                        color: on ? context.primaryTheme : context.stroke,
                      ),
                    ),
                    child: Text(
                      formatDateTabCompact(d),
                      style: TextStyle(
                        color: past
                            ? context.ghost
                            : on
                                ? context.primaryTheme
                                : context.body,
                        fontWeight: FontWeight.w600,
                        fontSize: 12.sp,
                      ),
                    ),
                  ),
                );
              },
            ),
          )
        else
          TextField(
            onChanged: (v) => controller.fallbackDate.value = v,
            decoration: InputDecoration(
              hintText: 'YYYY-MM-DD',
              filled: true,
              fillColor: const Color(0xFFF7F7FB),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(10.r),
              ),
            ),
          ),
        if (controller.fallbackDate.value.isNotEmpty) ...[
          SizedBox(height: 12.h),
          SizedBox(
            height: 160.h,
            child: GridView.builder(
              itemCount: _timeOptions.length,
              gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 4,
                mainAxisSpacing: 8.h,
                crossAxisSpacing: 8.w,
                childAspectRatio: 2.2,
              ),
              itemBuilder: (_, index) {
                final t = _timeOptions[index];
                final on = controller.fallbackTime.value == t;
                return GestureDetector(
                  onTap: () => controller.fallbackTime.value = on ? '' : t,
                  child: Container(
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: on ? context.primaryFocused : Colors.white,
                      borderRadius: BorderRadius.circular(8.r),
                      border: Border.all(
                        color: on ? context.primaryTheme : context.stroke,
                      ),
                    ),
                    child: Text(
                      t,
                      style: TextStyle(
                        color: on ? context.primaryTheme : context.body,
                        fontWeight: FontWeight.w600,
                        fontSize: 11.sp,
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ],
    );
  }
}
