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
  void initState() {
    super.initState();
  }

  @override
  Widget build(BuildContext context) {
    return Obx(
          () => ApiStateHandler(
        state: controller.dataStatus.value,
        onRetry: controller.fetchHomeData,
        skeleton: const AppSkeleton(),
        loadedElement: CustomScrollView(
          controller: scrollController,
          slivers: [
            GreetingUser(),
            EventInfo(),
            VideoCard.sliver(youtubeVideoUrl: controller.welcomeVideo.videoUrl),
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
    );
  }
}

