import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import 'package:url_launcher/url_launcher.dart';

import '../home_controller.dart';
import 'image_slider.dart';

/// Reception sidebar ads (after featured speakers). Strip ads are not shown here.
class AddCard extends StatelessWidget {
  const AddCard({super.key});

  @override
  Widget build(BuildContext context) {
    final ctrl = Get.find<HomeController>();

    return SliverToBoxAdapter(
      child: Obx(() {
        final banners = ctrl.addData.sidebar
            .where((b) => b.imageUrl.isNotEmpty && b.status)
            .toList();
        if (banners.isEmpty) return const SizedBox.shrink();

        return Column(
          children: [
            ImageSlider(
              height: MediaQuery.sizeOf(context).height * .18,
              width: MediaQuery.sizeOf(context).width - 32.sp,
              imageUrls: banners.map((b) => b.imageUrl).toList(),
              onTap: (index) async {
                if (index < 0 || index >= banners.length) return;
                final link = banners[index].url.trim();
                if (link.isEmpty) return;
                final uri = Uri.tryParse(link);
                if (uri == null) return;
                await launchUrl(uri, mode: LaunchMode.externalApplication);
              },
            ),
            SizedBox(height: 16.h),
          ],
        );
      }),
    );
  }
}
