import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../widgets/state_handler/api_state_handler.dart';
import '../../../widgets/loading_skeletons/room_list_skeleton.dart';
import '../session/widgets/ad_banner.dart';
import 'rooms_controller.dart';
import 'widgets/rooms_list.dart';
import 'widgets/rooms_search_filter.dart';

class RoomsView extends StatefulWidget {
  const RoomsView({super.key});

  @override
  State<RoomsView> createState() => _RoomsViewState();
}

class _RoomsViewState extends State<RoomsView> {
  late final RoomsController controller;

  @override
  void initState() {
    super.initState();
    controller = Get.find<RoomsController>();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      controller.fetchRooms();
      controller.startPolling();
    });
  }

  @override
  void dispose() {
    controller.stopPolling();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF7F7FB),
      body: Obx(
        () => ApiStateHandler(
          state: controller.dataStatus.value,
          onRetry: controller.fetchRooms,
          skeleton: const RoomListSkeleton(),
          loadedElement: RefreshIndicator(
            elevation: 0.5,
            color: Theme.of(context).colorScheme.primary,
            backgroundColor: Theme.of(context).colorScheme.primary.withOpacity(0.1),
            onRefresh: controller.fetchRooms,
            child: const CustomScrollView(
              slivers: [
                AdBanner(),
                RoomsSearchFilter(),
                RoomsList(),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
