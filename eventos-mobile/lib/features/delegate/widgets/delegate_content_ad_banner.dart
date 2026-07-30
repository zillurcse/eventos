import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../delegate_controller.dart';
import '../../../widgets/custom_image.dart';

class DelegateContentAdBanner extends StatelessWidget {
  const DelegateContentAdBanner({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<DelegateController>();
    return Obx(() {
      final contentAd = controller.contentAd;
      final imageUrl = contentAd?.firstImageUrl ?? '';
      if (contentAd == null || !contentAd.isActive || imageUrl.isEmpty) {
        return const SliverToBoxAdapter(child: SizedBox.shrink());
      }
      return SliverToBoxAdapter(
        child: Padding(
          padding: EdgeInsets.fromLTRB(16.w, 12.h, 16.w, 0),
          child: CustomImage(
            imageUrl,
            fit: BoxFit.cover,
            height: 60.sp,
            width: double.infinity,
            radius: 8.r,
          ),
        ),
      );
    });
  }
}
