import 'package:expouse/utils/extension/theme_ext.dart';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../widgets/cards/session_card.dart';
import '../../home/home_controller.dart';

class LiveSessions extends StatelessWidget {
  const LiveSessions({super.key});

  @override
  Widget build(BuildContext context) {
    final homeCtrl = Get.find<HomeController>();

    return Obx(() {
      final sessions = homeCtrl.currentSessions;
      if (sessions.isEmpty) {
        return const SliverToBoxAdapter(child: SizedBox.shrink());
      }

      return SliverToBoxAdapter(
        child: Padding(
          padding: EdgeInsets.only(top: 8.h),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  SizedBox(width: 16.w),
                  Container(
                    padding: EdgeInsets.symmetric(vertical: 1.sp, horizontal: 3.sp),
                    decoration: BoxDecoration(
                      color: context.redError,
                      borderRadius: BorderRadius.circular(4.r),
                    ),
                    child: Text(
                      "LIVE",
                      style: context.specialCaption2?.copyWith(
                        color: context.tertiaryText,
                      ),
                    ),
                  ),
                  SizedBox(width: 8.w),
                  Text(
                    "${sessions.length} Session${sessions.length > 1 ? 's' : ''} Live Now",
                    style: context.h1,
                  ),
                ],
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
                          isOnGoing: true,
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
            ],
          ),
        ),
      );
    });
  }
}
