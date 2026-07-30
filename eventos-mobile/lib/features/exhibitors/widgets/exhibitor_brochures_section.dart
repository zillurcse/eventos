import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:get/get.dart';
import '../../../models/exhibitor_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_image.dart';
import '../../briefcase/briefcase_controller.dart';

class ExhibitorBrochuresSection extends StatelessWidget {
  final ExhibitorModel exhibitor;

  const ExhibitorBrochuresSection({super.key, required this.exhibitor});

  Future<void> _openFile(String fileUrl) async {
    if (fileUrl.isEmpty) return;
    final url = fileUrl.startsWith('http') ? fileUrl : 'https://admin.expouse.com/storage/$fileUrl';
    final uri = Uri.tryParse(url);
    if (uri != null) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  @override
  Widget build(BuildContext context) {
    final List<dynamic> brochureList = [];

    // Try reading actual documents
    if (exhibitor.documents.isNotEmpty) {
      brochureList.addAll(exhibitor.documents);
    } else if (exhibitor.brochure?.isNotEmpty == true) {
      brochureList.add(exhibitor.brochure!);
    }

    // Default mock list matching the UI mockup if none are provided
    if (brochureList.isEmpty) {
      brochureList.addAll([
        {
          'name': 'Sample.pdf',
          'url': 'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf',
        },
        {
          'name': 'Sample.pdf',
          'url': 'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf',
        },
      ]);
    }

    final briefcaseCtrl = Get.find<BriefcaseController>();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Brochure (${brochureList.length})',
          style: context.h2?.copyWith(color: context.heading),
        ),
        SizedBox(height: 16.h),
        ...brochureList.map((item) {
          final String name = _getDocName(item);
          final String url = _getDocUrl(item);

          return Obx(() {
            final isSaved = briefcaseCtrl.isFileInBriefcase(url);

            return Container(
              margin: EdgeInsets.only(bottom: 12.h),
              padding: EdgeInsets.all(12.sp),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(12.r),
                border: Border.all(color: context.strokeLight, width: 1.sp),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.02),
                    blurRadius: 6,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
              child: Row(
                children: [
                  // PDF Icon (matching chat style)
                  Container(
                    padding: EdgeInsets.all(4.sp),
                    child: CustomImage(
                      "assets/svg/icons/pdf.svg",
                      height: 28.sp,
                    ),
                  ),
                  SizedBox(width: 12.w),
                  // File Name and Type
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          name,
                          style: context.bodyRegular?.copyWith(
                            fontWeight: FontWeight.bold,
                            color: context.heading,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                        SizedBox(height: 4.h),
                        Text(
                          "PDF FILE",
                          style: context.specialCaption2?.copyWith(
                            color: context.caption,
                            fontSize: 10.sp,
                          ),
                        ),
                      ],
                    ),
                  ),
                  // Action Buttons: Save/Download, Delete
                  GestureDetector(
                    onTap: () {
                      if (isSaved) {
                        briefcaseCtrl.removeFileByUrl(url);
                        Get.rawSnackbar(message: "File removed from briefcase");
                      } else {
                        briefcaseCtrl.addFile(name, url);
                        Get.rawSnackbar(message: "File saved to briefcase");
                      }
                    },
                    child: Container(
                      padding: EdgeInsets.all(8.sp),
                      child: Icon(
                        isSaved ? Icons.business_center : Icons.business_center_outlined,
                        color: isSaved ? context.primaryTheme : context.caption,
                        size: 20.sp,
                      ),
                    ),
                  ),
                  SizedBox(width: 4.w),
                  GestureDetector(
                    onTap: () => _openFile(url),
                    child: Container(
                      padding: EdgeInsets.all(8.sp),
                      child: Icon(
                        Icons.file_download_outlined,
                        color: context.caption,
                        size: 20.sp,
                      ),
                    ),
                  ),
                ],
              ),
            );
          });
        }),
      ],
    );
  }

  String _getDocName(dynamic item) {
    if (item == null) return 'Document.pdf';
    if (item is String) return item.split('/').last;
    if (item is Map) {
      final name = item['name'];
      if (name != null) return name.toString();
      final title = item['title'];
      if (title != null) return title.toString();
      final file = item['file']?.toString();
      if (file != null) return file.split('/').last;
      final doc = item['document']?.toString();
      if (doc != null) return doc.split('/').last;
    }
    return 'Document.pdf';
  }

  String _getDocUrl(dynamic item) {
    if (item == null) return '';
    if (item is String) return item;
    if (item is Map) {
      return item['url']?.toString() ??
          item['file']?.toString() ??
          item['document']?.toString() ??
          item['path']?.toString() ??
          '';
    }
    return '';
  }
}
