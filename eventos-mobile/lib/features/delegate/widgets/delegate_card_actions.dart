import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

import 'package:get/get.dart';

import '../../../models/delegate_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_image.dart';
import '../../bookmarks/bookmark_controller.dart';
import '../delegate_controller.dart';

class DelegateCardActions extends StatelessWidget {
  final DelegateItemModel? delegate;

  const DelegateCardActions({super.key, this.delegate});

  @override
  Widget build(BuildContext context) {
    final delegateId = delegate?.id;
    final name = delegate?.name ?? '';
    final image = delegate?.image ?? '';
    final designation = delegate?.designation ?? '';
    final company = delegate?.company ?? '';

    return Padding(
      padding: EdgeInsets.only(top: 12.h),
      child: Row(
        children: [
          // Bookmark / favourite toggle
          Obx(() {
            final isFavorite = () {
              if (delegateId == null) return false;
              if (Get.isRegistered<BookmarkController>()) {
                final bm = Get.find<BookmarkController>();
                bm.bookmarkedDelegates.length;
                return bm.isOnHashed('delegate', delegateId);
              }
              return delegate?.isFavorite ?? false;
            }();

            return GestureDetector(
              onTap: () {
                if (delegateId != null) {
                  Get.find<DelegateController>().toggleBookmark(delegateId);
                }
              },
              child: Container(
                padding: EdgeInsets.symmetric(
                  horizontal: 8.sp,
                  vertical: 6.sp,
                ),
                decoration: BoxDecoration(
                  color: context.tertiaryText,
                  borderRadius: BorderRadius.circular(8.r),
                  border: Border.all(
                    color: context.primaryTheme,
                  ),
                ),
                child: Icon(
                  isFavorite ? Icons.bookmark : Icons.bookmark_border,
                  color: context.primaryTheme,
                  size: 26.sp,
                ),
              ),
            );
          }),
          SizedBox(width: 8.w),
          // Chat
          Expanded(
            child: GestureDetector(
              onTap: () {
                if (delegateId == null) return;
                Get.find<DelegateController>().startChat(
                  delegateId,
                  name: name,
                  imageUrl: image.isEmpty ? null : image,
                );
              },
              child: Container(
                height: 42.sp,
                decoration: BoxDecoration(
                  color: context.tertiaryText,
                  borderRadius: BorderRadius.circular(8.r),
                  border: Border.all(color: context.primaryTheme),
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    CustomImage(
                      "assets/svg/icons/chat.svg",
                      color: context.primaryTheme,
                    ),
                    SizedBox(width: 8.w),
                    Text(
                      "Chat",
                      style: context.buttonMediumBold?.copyWith(
                        color: context.primaryTheme,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
          SizedBox(width: 8.w),
          // Meet
          Expanded(
            child: GestureDetector(
              onTap: () {
                if (delegateId == null) return;
                Get.find<DelegateController>().requestMeet(
                  delegateId,
                  name: name,
                  designation: designation,
                  company: company,
                  imageUrl: image.isEmpty ? null : image,
                );
              },
              child: Container(
                height: 42.sp,
                decoration: BoxDecoration(
                  color: context.tertiaryText,
                  borderRadius: BorderRadius.circular(8.r),
                  border: Border.all(color: context.primaryTheme),
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    CustomImage(
                      "assets/svg/icons/video.svg",
                      color: context.primaryTheme,
                    ),
                    SizedBox(width: 8.w),
                    Text(
                      "Meet",
                      style: context.buttonMediumBold?.copyWith(
                        color: context.primaryTheme,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
