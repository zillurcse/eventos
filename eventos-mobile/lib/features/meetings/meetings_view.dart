import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../widgets/state_handler/api_state_handler.dart';
import 'meetings_controller.dart';
import 'widgets/meeting_card.dart';
import 'widgets/meetings_ad_banner.dart';
import 'widgets/meetings_list.dart';
import 'widgets/meetings_toolbar.dart';

class MeetingsView extends StatefulWidget {
  const MeetingsView({super.key});

  @override
  State<MeetingsView> createState() => _MeetingsViewState();
}

class _MeetingsViewState extends State<MeetingsView> {
  late final MeetingsController controller;

  @override
  void initState() {
    super.initState();
    controller = Get.find<MeetingsController>();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      controller.fetchMeetings();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF7F7FB),
      body: Obx(
        () => ApiStateHandler(
          state: controller.dataStatus.value,
          onRetry: controller.fetchMeetings,
          skeleton: const _MeetingsSkeleton(),
          loadedElement: const CustomScrollView(
            slivers: [
              MeetingsAdBanner(),
              MeetingsToolbar(),
              MeetingsList(),
            ],
          ),
        ),
      ),
    );
  }
}

class _MeetingsSkeleton extends StatelessWidget {
  const _MeetingsSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView.builder(
      padding: EdgeInsets.fromLTRB(16.w, 16.h, 16.w, 24.h),
      itemCount: 3,
      itemBuilder: (_, __) => const MeetingCardSkeleton(),
    );
  }
}
