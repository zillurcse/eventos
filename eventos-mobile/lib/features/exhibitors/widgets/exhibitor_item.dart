import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../models/exhibitor_models.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_image.dart';
import '../pages/exhibitor_details.dart';
import 'exhibitor_card_actions.dart';
import '../exhibitor_controller.dart';
import '../../bookmarks/bookmark_controller.dart';

class ExhibitorItem extends StatefulWidget {
  final ExhibitorModel exhibitor;

  const ExhibitorItem({
    super.key,
    required this.exhibitor,
  });

  @override
  State<ExhibitorItem> createState() => _ExhibitorItemState();
}

class _ExhibitorItemState extends State<ExhibitorItem> {
  @override
  Widget build(BuildContext context) {
    final e = widget.exhibitor;
    final ctrl = Get.find<ExhibitorController>();

    return Obx(() {
      final showDetail = ctrl.expandedExhibitorId.value == e.id;
      final isBookmarked = () {
        if (Get.isRegistered<BookmarkController>()) {
          final bm = Get.find<BookmarkController>();
          bm.bookmarkedExhibitors.length; // Touch to trigger Obx
          return bm.isOnHashed('exhibitor', e.id);
        }
        return ctrl.exhibitorPage.value.bookmarkedExhibitors.contains(e.id);
      }();

      return GestureDetector(
        onTap: () {
          Get.to(() => ExhibitorDetails(exhibitor: e));
        },
        child: Padding(
          padding: EdgeInsets.only(bottom: 12.h, left: 16.w, right: 16.w),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(12.r),
              child: Column(
                children: [
                  // Banner
                  Stack(
                    children: [
                      CustomImage(
                        e.spotlightBannerUrl.isNotEmpty
                            ? e.spotlightBannerUrl
                            : e.image ?? "https://learn.zoner.com/wp-content/uploads/2025/04/zoner-ai-image-creator.jpg",
                        height: (context.height * .15).sp,
                        width: double.infinity,
                        fit: BoxFit.cover,
                      ),
                      Positioned(
                        top: 4.h,
                        right: 8.w,
                        child: GestureDetector(
                          onTap: () => ctrl.toggleBookmark(e.id),
                          child: Container(
                            padding: EdgeInsets.symmetric(
                              horizontal: 14.sp,
                              vertical: 10.sp,
                            ),
                            decoration: BoxDecoration(
                              color: context.primaryFocused,
                              borderRadius: BorderRadius.circular(8.r),
                            ),
                            child: Icon(
                              isBookmarked ? Icons.bookmark : Icons.bookmark_border,
                              color: context.primaryTheme,
                              size: 26.sp,
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                  // Footer row
                  Container(
                    color: context.tertiaryText,
                    width: double.infinity,
                    padding: EdgeInsets.fromLTRB(12.w, 0, 12.w, 4.h),
                    child: Column(
                      children: [
                        Row(
                          children: [
                            CustomImage(
                              e.logoUrl.isNotEmpty
                                  ? e.logoUrl
                                  : "https://ui-avatars.com/api/?name=${e.name}&color=5B73E8&background=FFFF&length=1",
                              height: 48.sp,
                              width: 48.sp,
                              radius: 8.r,
                            ),
                            SizedBox(width: 12.w),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  SizedBox(height: 12.sp),
                                  Text(
                                    e.name,
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: context.h2,
                                  ),
                                  SizedBox(height: 6.sp),
                                  Text(
                                    [
                                      if (e.stallNo.isNotEmpty) e.stallNo,
                                      if (e.exhibitorType.isNotEmpty)
                                        e.exhibitorType.capitalizeFirst,
                                    ].join(', '),
                                    style: context.bodyRegular?.copyWith(
                                      color: context.caption,
                                    ),
                                  ),
                                  SizedBox(height: 12.sp),
                                ],
                              ),
                            ),
                            GestureDetector(
                              onTap: () {
                                if (ctrl.expandedExhibitorId.value == e.id) {
                                  ctrl.expandedExhibitorId.value = null;
                                } else {
                                  ctrl.expandedExhibitorId.value = e.id;
                                }
                              },
                              child: Container(
                                height: 40.sp,
                                width: 40.sp,
                                padding: EdgeInsets.all(8.sp),
                                decoration: BoxDecoration(
                                  color: showDetail
                                      ? context.backgroundColor
                                      : context.tertiaryText,
                                  borderRadius: BorderRadius.circular(8.r),
                                ),
                                child: Icon(
                                  showDetail ? Icons.close : Icons.more_horiz,
                                  color: context.caption,
                                ),
                              ),
                            ),
                          ],
                        ),
                        if (showDetail)
                          const ExhibitorCardActions(),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
      );
    });
  }
}
