import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../utils/extension/theme_ext.dart';
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
                          hintText: "Search...",
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
                        final isSelected = controller.sortType.value == opt['key'];
                        return Text(
                          opt['label']!,
                          style: baseStyle?.copyWith(
                            color: isSelected ? primaryThemeColor : bodyColor,
                            fontWeight: isSelected ? FontWeight.w600 : FontWeight.normal,
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
            Container(
              width: 40.h,
              height: 40.h,
              decoration: BoxDecoration(
                color: context.primaryFocused,
                borderRadius: BorderRadius.circular(8.r),
              ),
              child: Center(
                child: CustomImage(
                  "assets/svg/icons/filter.svg",
                  color: context.primaryTheme,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
