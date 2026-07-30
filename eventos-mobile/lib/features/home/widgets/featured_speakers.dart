import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';


import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/cards/speaker_card.dart';
import '../../../widgets/custom_button.dart';
import '../../root/root_controller.dart';
import '../home_controller.dart';

class FeaturedSpeakers extends StatelessWidget {
  const FeaturedSpeakers({super.key});

  @override
  Widget build(BuildContext context) {
    final ctrl = Get.find<HomeController>();

    return Obx(() {
      final speakers = ctrl.featuredSpeakers;
      if (speakers.isEmpty) return const SliverToBoxAdapter(child: SizedBox.shrink());

      return SliverToBoxAdapter(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: EdgeInsets.symmetric(horizontal: 16.w),
              child: Text(
                "Featured Speakers (${speakers.length})",
                style: context.h3,
              ),
            ),
            SizedBox(height: 12.h),
            SizedBox(
              height: (MediaQuery.sizeOf(context).height * .38).sp,
              child: ListView.builder(
                padding: EdgeInsets.only(left: 16.sp),
                scrollDirection: Axis.horizontal,
                itemCount: speakers.length,
                itemBuilder: (context, index) {
                  final sp = speakers[index];
                  return SpeakerCard(
                    name: sp.name,
                    designation: sp.designation,
                    company: sp.company,
                    imageUrl: sp.imageUrl,
                  );
                },
              ),
            ),
            Padding(
              padding: EdgeInsets.symmetric(horizontal: 16.w),
              child: Button.roundedText(
                text: "View all speakers",
                style: context.buttonMediumBold
                    ?.copyWith(color: context.primaryTheme),
                backgroundColor: context.primaryFocused,
                borderColor: context.primaryTheme,
                onTap: () {
                  Get.find<RootController>().changeIndex(3);
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
