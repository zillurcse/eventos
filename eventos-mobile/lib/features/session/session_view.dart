import 'package:expouse/features/session/widgets/day_selector_widget.dart';
import 'package:expouse/features/session/widgets/session_filters_widget.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'session_controller.dart';
import 'widgets/day_wise_session.dart';
import 'widgets/live_sessions.dart';
import 'widgets/search_filter_widget.dart';
import 'widgets/ad_banner.dart';
import '../../../widgets/state_handler/api_state_handler.dart';
import '../../../widgets/loading_skeletons/session_list_skeleton.dart';

class SessionView extends StatefulWidget {
  const SessionView({super.key});

  @override
  State<SessionView> createState() => _SessionViewState();
}

class _SessionViewState extends State<SessionView> {
  late final SessionController controller;

  @override
  void initState() {
    super.initState();
    controller = Get.find<SessionController>();
  }

  @override
  Widget build(BuildContext context) {
    return Obx(
      () => ApiStateHandler(
        state: controller.dataStatus.value,
        onRetry: controller.fetchSessions,
        skeleton: const SessionListSkeleton(),
        loadedElement: CustomScrollView(
          slivers: [
            const AdBanner(),
            const SearchWidget(),
            const DaySelectorWidget(),
            const SessionFiltersWidget(),
            const LiveSessions(),
            const DayWiseSession(),
          ],
        ),
      ),
    );
  }
}
