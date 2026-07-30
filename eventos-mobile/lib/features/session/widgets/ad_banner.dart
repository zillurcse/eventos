import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import '../../../widgets/custom_image.dart';
import '../../home/home_controller.dart';

class AdBanner extends StatelessWidget {
  const AdBanner({super.key});

  @override
  Widget build(BuildContext context) {
    final homeCtrl = Get.find<HomeController>();

    return Obx(() {
      final ads = homeCtrl.addData.images;
      if (ads.isEmpty) {
        return const SliverToBoxAdapter(child: SizedBox.shrink());
      }
      final imageUrl = ads.first.imageUrl;
      if (imageUrl.isEmpty) {
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
