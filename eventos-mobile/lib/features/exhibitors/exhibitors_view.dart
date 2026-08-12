import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/loading_skeletons/exhibitor_list_skeleton.dart';
import '../../../widgets/state_handler/api_state_handler.dart';
import 'exhibitor_controller.dart';
import 'widgets/exhibitor_content_ad_banner.dart';
import 'widgets/exhibitor_search_sort_widget.dart';
import 'widgets/exhibitors_list.dart';

class ExhibitorsView extends StatefulWidget {
  const ExhibitorsView({super.key});

  @override
  State<ExhibitorsView> createState() => _ExhibitorsViewState();
}

class _ExhibitorsViewState extends State<ExhibitorsView> {
  final controller = Get.find<ExhibitorController>();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      controller.fetchExhibitors();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Obx(
        () => ApiStateHandler(
          state: controller.dataStatus.value,
          onRetry: controller.fetchExhibitors,
          skeleton: const ExhibitorListSkeleton(),
          loadedElement: RefreshIndicator(
            elevation: 0.5,
            color: Theme.of(context).colorScheme.primary,
            backgroundColor: Theme.of(context).colorScheme.primary.withOpacity(0.1),
            onRefresh: controller.fetchExhibitors,
            child: const CustomScrollView(
              slivers: [
                ExhibitorContentAdBanner(),
                ExhibitorSearchSortWidget(),
                ExhibitorsList(),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
