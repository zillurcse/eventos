import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../utils/enum/enums.dart';
import '../../../widgets/custom_image.dart';
import '../../../widgets/state_handler/api_state_handler.dart';
import '../home/home_controller.dart';
import '../session/widgets/ad_banner.dart';
import 'contests_controller.dart';
import 'widgets/contests_list.dart';
import 'widgets/contests_phase_filter.dart';

class ContestsView extends StatefulWidget {
  const ContestsView({super.key});

  @override
  State<ContestsView> createState() => _ContestsViewState();
}

class _ContestsViewState extends State<ContestsView> {
  late final ContestsController controller;

  @override
  void initState() {
    super.initState();
    controller = Get.isRegistered<ContestsController>()
        ? Get.find<ContestsController>()
        : Get.put(ContestsController());
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (controller.dataStatus.value == ApiState.initial ||
          controller.contests.isEmpty) {
        controller.fetchContests();
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF7F7FB),
      body: Obx(
        () => ApiStateHandler(
          state: controller.dataStatus.value,
          onRetry: controller.fetchContests,
          loadedElement: CustomScrollView(
            slivers: [
              _ContestsAdBanner(url: controller.adImageUrl.value),
              if (controller.adImageUrl.value.isEmpty &&
                  Get.isRegistered<HomeController>())
                const AdBanner(),
              const ContestsPhaseFilter(),
              const ContestsList(),
            ],
          ),
        ),
      ),
    );
  }
}

class _ContestsAdBanner extends StatelessWidget {
  final String url;

  const _ContestsAdBanner({required this.url});

  @override
  Widget build(BuildContext context) {
    if (url.isEmpty) {
      return const SliverToBoxAdapter(child: SizedBox.shrink());
    }
    return SliverToBoxAdapter(
      child: Padding(
        padding: EdgeInsets.fromLTRB(16.w, 12.h, 16.w, 0),
        child: CustomImage(
          url,
          fit: BoxFit.cover,
          height: 60.sp,
          width: double.infinity,
          radius: 8.r,
        ),
      ),
    );
  }
}
