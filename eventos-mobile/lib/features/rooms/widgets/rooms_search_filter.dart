import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../models/room_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_image.dart';
import '../rooms_controller.dart';

class RoomsSearchFilter extends StatelessWidget {
  const RoomsSearchFilter({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<RoomsController>();

    return SliverToBoxAdapter(
      child: Padding(
        padding: EdgeInsets.fromLTRB(16.w, 12.h, 16.w, 4.h),
        child: Row(
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
            Obx(() {
              final types = controller.availableTypes;
              return Theme(
                data: Theme.of(context).copyWith(
                  splashColor: Colors.transparent,
                  highlightColor: Colors.transparent,
                ),
                child: PopupMenuButton<String>(
                  onSelected: controller.setType,
                  offset: Offset(0, 50.h),
                  color: Colors.white,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12.r),
                  ),
                  itemBuilder: (_) => [
                    PopupMenuItem(
                      value: 'all',
                      child: Text(
                        'Type: All',
                        style: context.bodyRegular?.copyWith(
                          color: controller.selectedType.value == 'all'
                              ? context.primaryTheme
                              : context.body,
                          fontWeight: controller.selectedType.value == 'all'
                              ? FontWeight.w700
                              : FontWeight.w500,
                        ),
                      ),
                    ),
                    for (final type in types)
                      PopupMenuItem(
                        value: type,
                        child: Text(
                          'Type: ${BreakoutRoom.typeLabels[type] ?? type}',
                          style: context.bodyRegular?.copyWith(
                            color: controller.selectedType.value == type
                                ? context.primaryTheme
                                : context.body,
                            fontWeight: controller.selectedType.value == type
                                ? FontWeight.w700
                                : FontWeight.w500,
                          ),
                        ),
                      ),
                  ],
                  child: Container(
                    height: 40.h,
                    width: 40.h,
                    decoration: BoxDecoration(
                      color: context.primaryFocused,
                      borderRadius: BorderRadius.circular(8.r),
                    ),
                    child: Center(
                      child: CustomImage(
                        'assets/svg/icons/sorting.svg',
                        color: context.primaryTheme,
                      ),
                    ),
                  ),
                ),
              );
            }),
          ],
        ),
      ),
    );
  }
}
