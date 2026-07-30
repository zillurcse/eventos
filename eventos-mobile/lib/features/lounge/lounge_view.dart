import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../widgets/state_handler/api_state_handler.dart';
import 'lounge_controller.dart';
import 'widgets/lounge_header_controls.dart';
import 'widgets/lounge_tables_list.dart';

class LoungeView extends StatefulWidget {
  const LoungeView({super.key});

  @override
  State<LoungeView> createState() => _LoungeViewState();
}

class _LoungeViewState extends State<LoungeView> {
  late final LoungeController controller;

  @override
  void initState() {
    super.initState();
    controller = Get.find<LoungeController>();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      controller.fetchTables();
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
          onRetry: controller.fetchTables,
          loadedElement: const CustomScrollView(
            slivers: [
              LoungeHeaderControls(),
              LoungeTablesList(),
            ],
          ),
        ),
      ),
    );
  }
}
