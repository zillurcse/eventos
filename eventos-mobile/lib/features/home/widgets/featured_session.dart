import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';


import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/cards/session_card.dart';
import '../../../widgets/custom_button.dart';
import '../../root/root_controller.dart';
import '../home_controller.dart';

class FeaturedSessions extends StatelessWidget {
  const FeaturedSessions({super.key});

  @override
  Widget build(BuildContext context) {
    final ctrl = Get.find<HomeController>();

    return Obx(() {
      final sessions = ctrl.featuredSessions;
      if (sessions.isEmpty) return const SliverToBoxAdapter(child: SizedBox.shrink());

      return SliverToBoxAdapter(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: EdgeInsets.fromLTRB(16.w, 12.h, 16.w, 0),
              child: Text(
                "Featured Sessions (${sessions.length})",
                style: context.h3,
              ),
            ),
            SizedBox(height: 2.h),
            Padding(
              padding: EdgeInsets.symmetric(horizontal: 16.w),
              child: Text(
                sessions.first.day.date.isNotEmpty
                    ? sessions.first.day.date
                    : sessions.first.day.title,
                style: context.specialCaption1
                    ?.copyWith(color: context.caption),
              ),
            ),
            SizedBox(height: 12.h),
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              padding: EdgeInsets.only(left: 16.sp, right: 16.sp),
              child: IntrinsicHeight(
                child: Row(
                  children: sessions.map((s) {
                    return Padding(
                      padding: EdgeInsets.only(right: 12.sp),
                      child: SessionCard(
                        session: s,
                        title: s.title,
                        startTime: s.startTime,
                        endTime: s.endTime,
                        dayLabel: s.day.title,
                        logoUrl: s.logoUrl,
                        speakerImageUrls: s.speakers.map((sp) => sp.imageUrl).toList(),
                      ),
                    );
                  }).toList(),
                ),
              ),
            ),
            SizedBox(height: 12.h),
            Padding(
              padding: EdgeInsets.symmetric(horizontal: 16.w),
              child: Button.roundedText(
                text: "View all sessions",
                style: context.buttonMediumBold
                    ?.copyWith(color: context.primaryTheme),
                backgroundColor: context.primaryFocused,
                borderColor: context.primaryTheme,
                onTap: () {
                  Get.find<RootController>().changeIndex(2);
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