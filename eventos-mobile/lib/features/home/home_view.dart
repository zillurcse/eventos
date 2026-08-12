import 'package:expouse/features/home/widgets/add_card.dart';
import 'package:expouse/features/home/widgets/event_cta_card.dart';
import 'package:expouse/features/home/widgets/event_info.dart';
import 'package:expouse/features/home/widgets/featured_exhibitor.dart';
import 'package:expouse/features/home/widgets/featured_session.dart';
import 'package:expouse/features/home/widgets/featured_speakers.dart';
import 'package:expouse/features/home/widgets/featured_sponsors.dart';
import 'package:expouse/features/home/widgets/greeting_user.dart';
import 'package:expouse/features/home/widgets/leaderboard.dart';
import 'package:expouse/features/home/widgets/ongoing_session.dart';
import 'package:expouse/widgets/cards/video_card.dart';
import 'package:expouse/widgets/state_handler/api_state_handler.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../widgets/app_skeleton.dart';
import 'home_controller.dart';

class HomeView extends StatefulWidget {
  const HomeView({super.key});

  @override
  State<HomeView> createState() => _HomeViewState();
}

class _HomeViewState extends State<HomeView> {
  final controller = Get.find<HomeController>();
  final scrollController = ScrollController();

  @override
  void dispose() {
    scrollController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    // Obx only tracks dataStatus - section widgets own their own Obx scopes.
    return Obx(
      () => ApiStateHandler(
        state: controller.dataStatus.value,
        onRetry: controller.fetchHomeData,
        skeleton: const AppSkeleton(),
        loadedElement: RefreshIndicator(
          elevation: 0.5,
          color: Theme.of(context).colorScheme.primary,
          backgroundColor: Theme.of(context).colorScheme.primary.withOpacity(0.1),
          onRefresh: controller.fetchHomeData,
          child: CustomScrollView(
            controller: scrollController,
            slivers: const [
              GreetingUser(),
              EventInfo(),
              _HomeWelcomeVideo(),
              OngoingSessions(),
              FeaturedSessions(),
              FeaturedSpeakers(),
              AddCard(),
              FeaturedExhibitor(),
              FeaturedSponsor(),
              Leaderboard(),
              EventCtaCard(),
            ],
          ),
        ),
      ),
    );
  }
}

class _HomeWelcomeVideo extends StatelessWidget {
  const _HomeWelcomeVideo();

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<HomeController>();
    return Obx(
      () => VideoCard.sliver(
        youtubeVideoUrl: controller.welcomeVideo.videoUrl,
      ),
    );
  }
}
