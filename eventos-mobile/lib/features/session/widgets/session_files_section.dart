import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:get/get.dart';
import 'package:expouse/utils/config/app_config.dart';
import 'package:expouse/utils/service/engagement_service.dart';
import '../../../models/session_detail_response_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../features/briefcase/briefcase_controller.dart';

class SessionFilesSection extends StatelessWidget {
  final SessionDetailModel detail;

  const SessionFilesSection({super.key, required this.detail});

  Future<void> _openFile(String? fileUrl, {String? title}) async {
    if (fileUrl == null || fileUrl.isEmpty) return;
    final url = AppConfig.resolveAssetUrl(fileUrl);
    final uri = Uri.tryParse(url);
    if (uri != null) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
      final eng = EngagementService.instance;
      eng.track(
        actionType: 'session.resource_downloaded',
        objectType: 'document',
        objectUuid: detail.uuid,
        idempotencyKey:
            eng.onceKey('session.resource_downloaded', '${detail.uuid}:$fileUrl'),
        metadata: {'title': title, 'url': fileUrl},
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final docs = detail.documents;
    if (docs.isEmpty && (detail.file == null || detail.file!.isEmpty)) {
      return const SizedBox.shrink();
    }

    final items = docs.isNotEmpty
        ? docs
            .map((d) => (name: d.name.isNotEmpty ? d.name : d.url.split('/').last, url: d.url))
            .toList()
        : [
            (
              name: detail.file!.split('/').last,
              url: detail.file!,
            )
          ];

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
          ...items.map((item) {
            return Padding(
              padding: EdgeInsets.only(bottom: 8.h),
              child: Container(
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
                            item.name,
                            style: context.bodyRegular?.copyWith(
                              fontWeight: FontWeight.bold,
                              color: context.heading,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                          SizedBox(height: 4.h),
                          Text(
                            'DOCUMENT',
                            style: context.specialCaption2
                                ?.copyWith(color: context.caption),
                          ),
                        ],
                      ),
                    ),
                    Obx(() {
                      final isSaved =
                          briefcaseCtrl.isFileInBriefcase(item.url);
                      return GestureDetector(
                        onTap: () {
                          if (isSaved) {
                            briefcaseCtrl.removeFileByUrl(item.url);
                            Get.rawSnackbar(
                                message: "Removed from briefcase");
                          } else {
                            briefcaseCtrl.addFile(item.name, item.url);
                            Get.rawSnackbar(message: "Added to briefcase");
                          }
                        },
                        child: Container(
                          padding: EdgeInsets.all(6.sp),
                          child: Icon(
                            isSaved ? Icons.work : Icons.work_outline,
                            color: isSaved
                                ? context.primaryTheme
                                : context.caption,
                            size: 20.sp,
                          ),
                        ),
                      );
                    }),
                    SizedBox(width: 8.w),
                    GestureDetector(
                      onTap: () => _openFile(item.url, title: item.name),
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
            );
          }),
        ],
      ),
    );
  }
}
