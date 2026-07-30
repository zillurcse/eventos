import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../models/delegate_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_image.dart';
import 'delegate_card_actions.dart';
import '../pages/delegate_details.dart';
import '../delegate_controller.dart';

class DelegateCard extends StatefulWidget {
  final DelegateItemModel delegate;

  const DelegateCard({super.key, required this.delegate});

  @override
  State<DelegateCard> createState() => _DelegateCardState();
}

class _DelegateCardState extends State<DelegateCard> {
  @override
  Widget build(BuildContext context) {
    final delegate = widget.delegate;
    final hasImage = delegate.image.isNotEmpty;
    final ctrl = Get.find<DelegateController>();

    return Obx(() {
      final showDetail = ctrl.expandedDelegateId.value == delegate.id;

      return GestureDetector(
        onTap: () {
          Get.to(() => DelegateDetails(delegate: delegate));
        },
        child: Container(
          margin: EdgeInsets.fromLTRB(16.w, 0, 16.w, 8.h),
          padding: EdgeInsets.all(12.sp),
          decoration: BoxDecoration(
            color: context.tertiaryText,
            borderRadius: BorderRadius.circular(12.r),
            border: Border.all(
              color: showDetail ? context.primaryTheme : context.tertiaryText,
            ),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Row(
                children: [
                  // Avatar or initial fallback
                  hasImage
                      ? CustomImage(
                          delegate.image,
                          height: 44.sp,
                          width: 44.sp,
                          radius: 8.r,
                        )
                      : Container(
                          height: 44.sp,
                          width: 44.sp,
                          decoration: BoxDecoration(
                            color: context.primaryFocused,
                            borderRadius: BorderRadius.circular(8.r),
                          ),
                          child: Center(
                            child: Text(
                              delegate.name.isNotEmpty
                                  ? delegate.name[0].toUpperCase()
                                  : '?',
                              style: context.titleLarge?.copyWith(
                                color: context.primaryTheme,
                              ),
                            ),
                          ),
                        ),
                  SizedBox(width: 8.w),
                  // Name, designation, company
                  Expanded(
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          delegate.name,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: context.titleLarge?.copyWith(
                            color: context.heading,
                          ),
                        ),
                        if (delegate.designation.isNotEmpty) ...[
                          SizedBox(height: 2.h),
                          Text(
                            delegate.designation,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: context.specialCaption1?.copyWith(
                              color: context.caption,
                            ),
                          ),
                        ],
                        if (delegate.company.isNotEmpty) ...[
                          SizedBox(height: 2.h),
                          Text(
                            delegate.company,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: context.specialCaption1?.copyWith(
                              color: context.primaryTheme,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  SizedBox(width: 8.w),
                  // Favourite indicator + expand toggle
                  Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      GestureDetector(
                        onTap: () {
                          if (ctrl.expandedDelegateId.value == delegate.id) {
                            ctrl.expandedDelegateId.value = null;
                          } else {
                            ctrl.expandedDelegateId.value = delegate.id;
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
                ],
              ),
              if (showDetail)
                DelegateCardActions(delegate: delegate),
            ],
          ),
        ),
      );
    });
  }
}
