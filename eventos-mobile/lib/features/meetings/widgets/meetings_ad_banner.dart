import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../widgets/custom_image.dart';
import '../meetings_controller.dart';

class MeetingsAdBanner extends StatelessWidget {
  const MeetingsAdBanner({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<MeetingsController>();

    return Obx(() {
      final url = controller.adImageUrl.value;
      if (url.isEmpty) {
        return const SliverToBoxAdapter(child: SizedBox.shrink());
      }

      return SliverToBoxAdapter(
        child: Padding(
          padding: EdgeInsets.fromLTRB(16.w, 12.h, 16.w, 0),
          child: CustomImage(
            url,
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
