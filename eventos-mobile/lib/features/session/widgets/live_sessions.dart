import 'package:expouse/utils/extension/theme_ext.dart';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../widgets/cards/session_card.dart';
import '../session_controller.dart';

class LiveSessions extends StatelessWidget {
  const LiveSessions({super.key});

  @override
  Widget build(BuildContext context) {
    final sessionCtrl = Get.find<SessionController>();

    return Obx(() {
      // Touch days so Obx rebuilds when agenda refreshes / filters change.
      final _ = sessionCtrl.days.length;
      final sessions = sessionCtrl.liveSessions;
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
              if (sessions.length == 1)
                Padding(
                  padding: EdgeInsets.symmetric(horizontal: 16.w),
                  child: SessionCard(
                    session: sessions.first,
                    isOnGoing: true,
                    fullWidth: true,
                    title: sessions.first.title,
                    startTime: sessions.first.startTime,
                    endTime: sessions.first.endTime,
                    dayLabel: sessions.first.day.title,
                    logoUrl: sessions.first.logoUrl,
                    speakerImageUrls:
                        sessions.first.speakers.map((sp) => sp.imageUrl).toList(),
                  ),
                )
              else
                SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  padding: EdgeInsets.only(left: 16.sp, right: 16.sp),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
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
                          speakerImageUrls:
                              s.speakers.map((sp) => sp.imageUrl).toList(),
                        ),
                      );
                    }).toList(),
                  ),
                ),
            ],
          ),
        ),
      );
    });
  }
}
