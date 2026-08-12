import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_button.dart';
import '../../../widgets/custom_image.dart';
import '../speaker_controller.dart';

class SearchFilterWidget extends StatelessWidget {
  const SearchFilterWidget({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<SpeakerController>();
    return SliverToBoxAdapter(
      child: Padding(
        padding: EdgeInsets.fromLTRB(16.w, 12.h, 16.w, 12.h),
        child: Row(
          children: [
            Expanded(
              child: Container(
                height: 40.h,
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(8.r),
                  border: Border.all(color: context.stroke),
                ),
                child: Row(
                  children: [
                    SizedBox(width: 12.w),
                    CustomImage(
                      "assets/svg/icons/search.svg",
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
                          hintText: "Search speakers...",
                          hintStyle: context.bodyRegular?.copyWith(
                            color: context.ghost,
                          ),
                        ),
                      ),
                    ),
                    Obx(() => controller.searchKey.value.isNotEmpty
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
                        : const SizedBox.shrink()),
                  ],
                ),
              ),
            ),
            SizedBox(width: 12.w),
            Theme(
              data: Theme.of(context).copyWith(
                splashColor: Colors.transparent,
                highlightColor: Colors.transparent,
              ),
              child: PopupMenuButton<String>(
                offset: Offset(0, 50.h),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12.r),
                ),
                color: Colors.white,
                onSelected: (String key) {
                  controller.setSortType(key);
                },
                itemBuilder: (BuildContext popupContext) {
                  final baseStyle = context.bodyRegular;
                  final primaryThemeColor = context.primaryTheme;
                  final bodyColor = context.body;

                  final options = [
                    {'key': 'name_asc', 'label': 'Ascending'},
                    {'key': 'name_desc', 'label': 'Descending'},
                  ];

                  return options.map((opt) {
                    return PopupMenuItem<String>(
                      value: opt['key'],
                      child: Obx(() {
                        final isSelected =
                            controller.sortType.value == opt['key'];
                        return Text(
                          opt['label']!,
                          style: baseStyle?.copyWith(
                            color:
                                isSelected ? primaryThemeColor : bodyColor,
                            fontWeight: isSelected
                                ? FontWeight.w600
                                : FontWeight.normal,
                          ),
                        );
                      }),
                    );
                  }).toList();
                },
                child: Container(
                  width: 40.h,
                  height: 40.h,
                  decoration: BoxDecoration(
                    color: context.primaryFocused,
                    borderRadius: BorderRadius.circular(8.r),
                  ),
                  child: Center(
                    child: CustomImage(
                      "assets/svg/icons/sorting.svg",
                      color: context.primaryTheme,
                    ),
                  ),
                ),
              ),
            ),
            SizedBox(width: 12.w),
            GestureDetector(
              onTap: () => _showFilterSheet(context, controller),
              child: Obx(() {
                final active = controller.hasActiveFilters;
                return Container(
                  width: 40.h,
                  height: 40.h,
                  decoration: BoxDecoration(
                    color: active
                        ? context.primaryTheme
                        : context.primaryFocused,
                    borderRadius: BorderRadius.circular(8.r),
                  ),
                  child: Center(
                    child: CustomImage(
                      "assets/svg/icons/filter.svg",
                      color: active ? Colors.white : context.primaryTheme,
                    ),
                  ),
                );
              }),
            ),
          ],
        ),
      ),
    );
  }

  void _showFilterSheet(
    BuildContext context,
    SpeakerController controller,
  ) {
    Get.bottomSheet(
      Container(
        constraints: BoxConstraints(maxHeight: 0.75.sh),
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
              Flexible(
                child: SingleChildScrollView(
                  child: Obx(() {
                    final companies = controller.companyOptions;
                    final titles = controller.titleOptions;

                    return Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Saved',
                          style: context.bodyRegular?.copyWith(
                            fontWeight: FontWeight.w700,
                            color: context.heading,
                          ),
                        ),
                        SizedBox(height: 8.h),
                        _FilterChip(
                          label: 'Saved only',
                          selected: controller.savedOnly.value,
                          onTap: controller.toggleSavedOnly,
                        ),
                        if (companies.isNotEmpty) ...[
                          SizedBox(height: 16.h),
                          Text(
                            'Companies',
                            style: context.bodyRegular?.copyWith(
                              fontWeight: FontWeight.w700,
                              color: context.heading,
                            ),
                          ),
                          SizedBox(height: 8.h),
                          Wrap(
                            spacing: 8.w,
                            runSpacing: 8.h,
                            children: companies.map((c) {
                              return _FilterChip(
                                label: c,
                                selected:
                                    controller.activeCompanies.contains(c),
                                onTap: () =>
                                    controller.toggleCompanyFilter(c),
                              );
                            }).toList(),
                          ),
                        ],
                        if (titles.isNotEmpty) ...[
                          SizedBox(height: 16.h),
                          Text(
                            'Job Titles',
                            style: context.bodyRegular?.copyWith(
                              fontWeight: FontWeight.w700,
                              color: context.heading,
                            ),
                          ),
                          SizedBox(height: 8.h),
                          Wrap(
                            spacing: 8.w,
                            runSpacing: 8.h,
                            children: titles.map((t) {
                              return _FilterChip(
                                label: t,
                                selected:
                                    controller.activeTitles.contains(t),
                                onTap: () =>
                                    controller.toggleTitleFilter(t),
                              );
                            }).toList(),
                          ),
                        ],
                        if (companies.isEmpty && titles.isEmpty) ...[
                          SizedBox(height: 12.h),
                          Text(
                            'Filters appear once speakers load.',
                            style: context.bodyRegular?.copyWith(
                              color: context.caption,
                            ),
                          ),
                        ],
                      ],
                    );
                  }),
                ),
              ),
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
