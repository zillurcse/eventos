import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../models/contest_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_button.dart';
import '../../../widgets/custom_image.dart';
import '../contest_details_controller.dart';

class ContestEntryComposer extends StatelessWidget {
  final Contest contest;

  const ContestEntryComposer({super.key, required this.contest});

  @override
  Widget build(BuildContext context) {
    final ctrl = Get.find<ContestDetailsController>();
    final takesMedia = contest.isEntryType &&
        (contest.allowPhotos || contest.allowVideos || contest.allowSelfie);

    return Container(
      margin: EdgeInsets.only(bottom: 16.h),
      padding: EdgeInsets.all(16.w),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16.r),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 6,
            offset: const Offset(0, 1),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            contest.isEntryType ? 'Your entry' : 'Your response',
            style: context.h2?.copyWith(
              fontWeight: FontWeight.w800,
              fontSize: 16.sp,
              color: const Color(0xFF1E293B),
            ),
          ),
          SizedBox(height: 12.h),
          Obx(() {
            // Rebuild counter when bodyText changes.
            final _ = ctrl.bodyText.value;
            return TextField(
              controller: ctrl.bodyController,
              maxLines: 3,
              maxLength: contest.characterLimit,
              decoration: InputDecoration(
                hintText: contest.isEntryType
                    ? 'Describe your entry…'
                    : 'Write your response…',
                filled: true,
                fillColor: const Color(0xFFF8FAFC),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12.r),
                  borderSide: BorderSide.none,
                ),
                counterText:
                    '${contest.characterLimit - ctrl.bodyText.value.length}',
              ),
            );
          }),
          Obx(() {
            final media = ctrl.draftAttachments;
            if (media.isEmpty) return const SizedBox.shrink();
            return Padding(
              padding: EdgeInsets.only(bottom: 10.h),
              child: SizedBox(
                height: 72.h,
                child: ListView.separated(
                  scrollDirection: Axis.horizontal,
                  itemCount: media.length,
                  separatorBuilder: (context, index) => SizedBox(width: 8.w),
                  itemBuilder: (_, i) {
                    final a = media[i];
                    return Stack(
                      children: [
                        ClipRRect(
                          borderRadius: BorderRadius.circular(10.r),
                          child: a.isVideo
                              ? Container(
                                  width: 72.w,
                                  height: 72.h,
                                  color: const Color(0xFF0F172A),
                                  child: Icon(Icons.videocam,
                                      color: Colors.white, size: 28.sp),
                                )
                              : CustomImage(
                                  a.url,
                                  width: 72.w,
                                  height: 72.h,
                                  fit: BoxFit.cover,
                                ),
                        ),
                        Positioned(
                          top: 2,
                          right: 2,
                          child: GestureDetector(
                            onTap: () => ctrl.removeDraftAttachment(i),
                            child: Container(
                              decoration: const BoxDecoration(
                                color: Colors.black54,
                                shape: BoxShape.circle,
                              ),
                              padding: const EdgeInsets.all(2),
                              child: Icon(Icons.close,
                                  size: 14.sp, color: Colors.white),
                            ),
                          ),
                        ),
                      ],
                    );
                  },
                ),
              ),
            );
          }),
          Row(
            children: [
              if (takesMedia)
                IconButton(
                  onPressed: ctrl.pickMedia,
                  icon: Obx(
                    () => ctrl.uploading.value
                        ? SizedBox(
                            width: 20.sp,
                            height: 20.sp,
                            child: const CircularProgressIndicator(strokeWidth: 2),
                          )
                        : Icon(
                            Icons.attach_file,
                            color: context.primaryTheme,
                          ),
                  ),
                ),
              const Spacer(),
              Obx(() {
                final canSubmit = !ctrl.submitting.value &&
                    !ctrl.uploading.value &&
                    (ctrl.bodyText.value.trim().isNotEmpty ||
                        ctrl.draftAttachments.isNotEmpty) &&
                    (!contest.attachMandatory ||
                        ctrl.draftAttachments.isNotEmpty) &&
                    ctrl.bodyText.value.length <= contest.characterLimit;
                return Opacity(
                  opacity: canSubmit ? 1 : 0.45,
                  child: Button.roundedText(
                    text: ctrl.submitting.value ? 'Submitting…' : 'Submit',
                    width: 110.w,
                    onTap: canSubmit ? ctrl.submitEntry : () {},
                  ),
                );
              }),
            ],
          ),
          Obx(() {
            if (ctrl.composerError.value.isEmpty) {
              return const SizedBox.shrink();
            }
            return Padding(
              padding: EdgeInsets.only(top: 8.h),
              child: Text(
                ctrl.composerError.value,
                style: TextStyle(color: Colors.red, fontSize: 12.sp),
              ),
            );
          }),
          Obx(() {
            if (ctrl.composerMessage.value.isEmpty) {
              return const SizedBox.shrink();
            }
            return Padding(
              padding: EdgeInsets.only(top: 8.h),
              child: Text(
                ctrl.composerMessage.value,
                style: TextStyle(
                  color: context.primaryTheme,
                  fontSize: 12.sp,
                  fontWeight: FontWeight.w600,
                ),
              ),
            );
          }),
        ],
      ),
    );
  }
}
