import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../widgets/loading_skeletons/speaker_list_skeleton.dart';
import '../../../widgets/state_handler/api_state_handler.dart';
import 'speaker_controller.dart';
import 'widgets/add_banner.dart';
import 'widgets/search_filter_widget.dart';
import 'widgets/speaker_content_ad_banner.dart';
import 'widgets/speaker_list.dart';

class SpeakerView extends StatefulWidget {
  const SpeakerView({super.key});

  @override
  State<SpeakerView> createState() => _SpeakerViewState();
}

class _SpeakerViewState extends State<SpeakerView> {
  final controller = Get.find<SpeakerController>();

  @override
  void initState() {
    super.initState();
  }

  @override
  Widget build(BuildContext context) {
    return Obx(
      () => ApiStateHandler(
        state: controller.dataStatus.value,
        onRetry: controller.fetchSpeakers,
        skeleton: const SpeakerListSkeleton(),
        loadedElement: const CustomScrollView(
          slivers: [
            AddBanner(),
            SpeakerContentAdBanner(),
            SearchFilterWidget(),
            SpeakerList(),
          ],
        ),
      ),
    );
  }
}
