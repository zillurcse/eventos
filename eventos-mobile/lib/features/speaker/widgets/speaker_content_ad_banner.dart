import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../speaker_controller.dart';
import '../../../widgets/custom_image.dart';

class SpeakerContentAdBanner extends StatelessWidget {
  const SpeakerContentAdBanner({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<SpeakerController>();
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
