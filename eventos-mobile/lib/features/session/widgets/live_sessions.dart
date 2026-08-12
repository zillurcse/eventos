import 'package:expouse/utils/extension/theme_ext.dart';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../widgets/cards/session_card.dart';
import '../../../widgets/cards/session_card_option_1.dart';
import '../../../widgets/cards/session_card_option_2.dart';
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
      final currentViewOption = sessionCtrl.viewOption.value;
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
                  child: _buildCard(
                    currentViewOption,
                    sessions.first,
                    true,
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
                        child: _buildCard(
                          currentViewOption,
                          s,
                          false,
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

  Widget _buildCard(int viewOption, dynamic session, bool fullWidth) {
    if (viewOption == 1) {
      return SessionCardOption1(
        session: session,
        fullWidth: fullWidth,
        title: session.title,
        startTime: session.startTime,
        endTime: session.endTime,
        location: session.sessionPlace,
        speakerImageUrls: session.speakers.map<String>((sp) => sp.imageUrl as String).toList(),
      );
    } else if (viewOption == 2) {
      return SessionCardOption2(
        session: session,
        fullWidth: fullWidth,
        title: session.title,
        startTime: session.startTime,
        endTime: session.endTime,
        dayLabel: session.day.title,
        location: session.sessionPlace,
        speakerImageUrls: session.speakers.map<String>((sp) => sp.imageUrl as String).toList(),
      );
    } else {
      return SessionCard(
        session: session,
        isOnGoing: true,
        fullWidth: fullWidth,
        title: session.title,
        startTime: session.startTime,
        endTime: session.endTime,
        dayLabel: session.day.title,
        logoUrl: session.logoUrl,
        speakerImageUrls: session.speakers.map<String>((sp) => sp.imageUrl as String).toList(),
      );
    }
  }
}
