import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import '../session_controller.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_image.dart';

class SearchWidget extends StatelessWidget {
  const SearchWidget({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<SessionController>();

    return SliverToBoxAdapter(
      child: Padding(
        padding: EdgeInsets.fromLTRB(16.w, 12.h, 16.w, 12.h),
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
                  decoration: InputDecoration(
                    border: InputBorder.none,
                    isDense: true,
                    contentPadding: EdgeInsets.symmetric(vertical: 10.h),
                    hintText: "Search sessions...",
                    hintStyle: context.bodyRegular?.copyWith(
                      color: context.ghost,
                    ),
                  ),
                ),
              ),
              Obx(() {
                final hasText = controller.searchQuery.value.isNotEmpty;
                if (!hasText) return const SizedBox.shrink();

                return GestureDetector(
                  onTap: controller.clearSearch,
                  child: Padding(
                    padding: EdgeInsets.symmetric(horizontal: 8.w),
                    child: Icon(
                      Icons.close,
                      size: 18.sp,
                      color: context.ghost,
                    ),
                  ),
                );
              }),
            ],
          ),
        ),
      ),
    );
  }
}
