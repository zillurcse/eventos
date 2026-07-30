import 'package:expouse/widgets/cards/video_card.dart';
import 'package:expouse/utils/extension/string_ext.dart';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_image.dart';
import '../home_controller.dart';

/// Renders event CTA items (image / text / video).
/// Vanishes when the API returns no CTAs.
class EventCtaCard extends StatelessWidget {
  const EventCtaCard({super.key});

  @override
  Widget build(BuildContext context) {
    final ctrl = Get.find<HomeController>();

    return SliverToBoxAdapter(
      child: Obx(() {
        final ctas = ctrl.eventCtas;
        if (ctas.isEmpty) return const SizedBox.shrink();

        return Column(
          children: ctas.map((e) {
            switch (e.ctaType) {
              case "image":
                if (e.ctaImageUrl.isEmpty) return const SizedBox.shrink();
                return Padding(
                  padding: EdgeInsets.fromLTRB(16.w, 0, 16.w, 12.h),
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(8.r),
                    child: CustomImage(
                      e.ctaImageUrl,
                      width: double.infinity,
                      height: 160.h,
                      fit: BoxFit.cover,
                    ),
                  ),
                );
              case "text":
                if (e.ctaDescription.isEmpty) return const SizedBox.shrink();
                return Padding(
                  padding: EdgeInsets.fromLTRB(16.w, 0, 16.w, 12.h),
                  child: Text(
                    e.ctaDescription.htmlToPlainText(),
                    textAlign: TextAlign.start,
                    style: context.bodyRegular,
                  ),
                );
              case "video":
                if (e.ctaVideoLink.isEmpty) return const SizedBox.shrink();
                return Padding(
                  padding: EdgeInsets.only(bottom: 12.h),
                  child: VideoCard.box(youtubeVideoUrl: e.ctaVideoLink),
                );
              default:
                return const SizedBox.shrink();
            }
          }).toList(),
        );
      }),
    );
  }
}