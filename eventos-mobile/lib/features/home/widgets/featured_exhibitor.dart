import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';


import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/cards/exhibitor_card.dart';
import '../../../widgets/custom_button.dart';
import '../../exhibitors/exhibitor_controller.dart';
import '../../exhibitors/exhibitors_view.dart';
import '../home_controller.dart';

class FeaturedExhibitor extends StatelessWidget {
  const FeaturedExhibitor({super.key});

  @override
  Widget build(BuildContext context) {
    final ctrl = Get.find<HomeController>();

    return Obx(() {
      final exhibitors = ctrl.featuredExhibitors;
      if (exhibitors.isEmpty) return const SliverToBoxAdapter(child: SizedBox.shrink());

      return SliverToBoxAdapter(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: EdgeInsets.symmetric(horizontal: 16.w),
              child: Text(
                "Featured Exhibitors (${exhibitors.length})",
                style: context.h3,
              ),
            ),
            SizedBox(height: 12.h),
            SizedBox(
              height: (MediaQuery.sizeOf(context).height * .26).sp,
              child: ListView.builder(
                padding: EdgeInsets.only(left: 16.sp),
                scrollDirection: Axis.horizontal,
                itemCount: exhibitors.length,
                itemBuilder: (context, index) {
                  final ex = exhibitors[index];
                  return ExhibitorCard(
                    name: ex.name,
                    stallNo: ex.stallNo,
                    exhibitorType: ex.exhibitorType,
                    bannerUrl: ex.spotlightBannerUrl,
                    logoUrl: ex.logoUrl,
                  );
                },
              ),
            ),
            Padding(
              padding: EdgeInsets.symmetric(horizontal: 16.w),
              child: Button.roundedText(
                text: "View all exhibitors",
                style: context.buttonMediumBold
                    ?.copyWith(color: context.primaryTheme),
                backgroundColor: context.primaryFocused,
                borderColor: context.primaryTheme,
                onTap: () {
                  Get.find<ExhibitorController>().setType('exhibitor');
                  Get.to(() => const ExhibitorsView());
                },
              ),
            ),
            SizedBox(height: 16.h),
          ],
        ),
      );
    });
  }
}
