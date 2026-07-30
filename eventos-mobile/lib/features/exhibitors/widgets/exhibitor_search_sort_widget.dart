import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_image.dart';
import '../exhibitor_controller.dart';

class ExhibitorSearchSortWidget extends StatelessWidget {
  const ExhibitorSearchSortWidget({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<ExhibitorController>();

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
                          hintText: "Search ...",
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
            PopupMenuButton<String?>(
              onSelected: (type) {
                controller.setType(type);
              },
              color: context.tertiaryText,
              offset: const Offset(0, 45),
              elevation: 4,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(8.r),
              ),
              itemBuilder: (context) => [
                PopupMenuItem<String?>(
                  value: 'exhibitor',
                  child: Text(
                    "Exhibitor",
                    style: context.bodyRegular?.copyWith(color: context.heading),
                  ),
                ),
                PopupMenuItem<String?>(
                  value: 'sponsor',
                  child: Text(
                    "Sponsor",
                    style: context.bodyRegular?.copyWith(color: context.heading),
                  ),
                ),
              ],
              child: Obx(() {
                final type = controller.selectedType.value;
                final displayLabel = type == 'exhibitor'
                    ? "Exhibitor"
                    : type == 'sponsor'
                        ? "Sponsor"
                        : "Type";
                final hasSelection = type != null;
                return Container(
                  height: 40.h,
                  padding: EdgeInsets.symmetric(horizontal: 20.w),
                  decoration: BoxDecoration(
                    color: context.backgroundColor,
                    borderRadius: BorderRadius.circular(8.r),
                    border: Border.all(
                      color: hasSelection ? context.primaryTheme : context.stroke,
                    ),
                  ),
                  child: Row(
                    children: [
                      Text(
                        displayLabel,
                        style: context.titleRegular?.copyWith(
                          color: hasSelection ? context.primaryTheme : context.caption,
                          fontWeight: hasSelection ? FontWeight.bold : FontWeight.normal,
                        ),
                      ),
                      SizedBox(width: 4.w),
                      Icon(
                        Icons.keyboard_arrow_down_outlined,
                        color: hasSelection ? context.primaryTheme : context.caption,
                      ),
                    ],
                  ),
                );
              }),
            ),
          ],
        ),
      ),
    );
  }
}
