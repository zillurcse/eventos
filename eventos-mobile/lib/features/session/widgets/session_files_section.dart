import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:get/get.dart';
import '../../../models/session_detail_response_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../features/briefcase/briefcase_controller.dart';

class SessionFilesSection extends StatelessWidget {
  final SessionDetailModel detail;

  const SessionFilesSection({super.key, required this.detail});

  Future<void> _openFile(String? fileUrl) async {
    if (fileUrl == null || fileUrl.isEmpty) return;
    final url = fileUrl.startsWith('http') ? fileUrl : 'https://admin.expouse.com/storage/$fileUrl';
    final uri = Uri.tryParse(url);
    if (uri != null) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (detail.file == null || detail.file!.isEmpty) return const SizedBox.shrink();

    final briefcaseCtrl = Get.find<BriefcaseController>();

    return Container(
      width: double.infinity,
      padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 16.h),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Files and Documents',
            style: context.h2?.copyWith(
              color: context.heading,
              fontWeight: FontWeight.bold,
            ),
          ),
          SizedBox(height: 12.h),
          Container(
            padding: EdgeInsets.all(12.sp),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(12.r),
              border: Border.all(color: context.strokeLight),
            ),
            child: Row(
              children: [
                Icon(
                  Icons.picture_as_pdf_outlined,
                  size: 36.sp,
                  color: Colors.redAccent,
                ),
                SizedBox(width: 12.w),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        detail.file!.split('/').last,
                        style: context.bodyRegular?.copyWith(
                          fontWeight: FontWeight.bold,
                          color: context.heading,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                      SizedBox(height: 4.h),
                      Text(
                        'PDF FILE',
                        style: context.specialCaption2?.copyWith(color: context.caption),
                      ),
                    ],
                  ),
                ),
                Obx(() {
                  final isSaved = briefcaseCtrl.isFileInBriefcase(detail.file!);
                  return GestureDetector(
                    onTap: () {
                      final fileName = detail.file!.split('/').last;
                      final fileUrl = detail.file!;
                      if (isSaved) {
                        briefcaseCtrl.removeFileByUrl(fileUrl);
                        Get.rawSnackbar(message: "Removed from briefcase");
                      } else {
                        briefcaseCtrl.addFile(fileName, fileUrl);
                        Get.rawSnackbar(message: "Added to briefcase");
                      }
                    },
                    child: Container(
                      padding: EdgeInsets.all(6.sp),
                      child: Icon(
                        isSaved ? Icons.work : Icons.work_outline,
                        color: isSaved ? context.primaryTheme : context.caption,
                        size: 20.sp,
                      ),
                    ),
                  );
                }),
                SizedBox(width: 8.w),
                GestureDetector(
                  onTap: () => _openFile(detail.file),
                  child: Container(
                    padding: EdgeInsets.all(6.sp),
                    child: Icon(
                      Icons.download_outlined,
                      color: context.primaryTheme,
                      size: 20.sp,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
