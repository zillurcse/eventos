import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../home_controller.dart';
import 'image_slider.dart';

/// Community banner slider. Vanishes when the API returns no banners.
class AddCard extends StatelessWidget {
  const AddCard({super.key});

  @override
  Widget build(BuildContext context) {
    final ctrl = Get.find<HomeController>();

    return SliverToBoxAdapter(
      child: Obx(() {
        final banners = ctrl.addData.images;
        if (banners.isEmpty) return const SizedBox.shrink();

        return Column(
          children: [
            ImageSlider(
              height: MediaQuery.sizeOf(context).height * .18,
              width: MediaQuery.sizeOf(context).width - 32.sp,
              imageUrls: banners.map((b) => b.imageUrl).toList(),
            ),
            SizedBox(height: 16.h),
          ],
        );
      }),
    );
  }
}