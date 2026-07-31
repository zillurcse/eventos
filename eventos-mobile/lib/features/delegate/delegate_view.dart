import 'package:expouse/widgets/loading_skeletons/delegate_list_skeleton.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../widgets/state_handler/api_state_handler.dart';
import 'delegate_controller.dart';
import 'widgets/delegate_add_banner.dart';
import 'widgets/delegate_search_filter_widget.dart';
import 'widgets/delegate_list.dart';

class DelegateView extends StatefulWidget {
  const DelegateView({super.key});

  @override
  State<DelegateView> createState() => _DelegateViewState();
}

class _DelegateViewState extends State<DelegateView> {
  late final DelegateController controller;

  @override
  void initState() {
    super.initState();
    controller = Get.find<DelegateController>();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      controller.fetchDelegates();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Obx(
        () => ApiStateHandler(
          state: controller.dataStatus.value,
          onRetry: controller.fetchDelegates,
          skeleton: const DelegateListSkeleton(),
          loadedElement: const CustomScrollView(
            slivers: [
              DelegateAddBanner(),
              DelegateSearchFilterWidget(),
              DelegateList(),
            ],
          ),
        ),
      ),
    );
  }
}
