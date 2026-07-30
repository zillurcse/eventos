import 'package:get/get.dart';

import '../../models/ads_model.dart';
import '../../models/banner_model.dart';
import '../../models/event_cta_model.dart';
import '../../models/exhibitor_model.dart';
import '../../models/leaderboard_entry_model.dart';
import '../../models/reception_event_model.dart';
import '../../models/reception_model.dart';
import '../../models/reception_speaker_model.dart';
import '../../models/session_model.dart';
import '../../models/welcome_video_model.dart';
import '../../utils/enum/enums.dart';
import '../../utils/helpers/helper_functions.dart';
import 'home_service.dart';

class HomeController extends GetxController {
  final _service = HomeService();

  // ── State ─────────────────────────────────────────────────────────────────
  final dataStatus = ApiState.initial.obs;

  // ── Data ──────────────────────────────────────────────────────────────────
  final Rx<ReceptionModel> reception = const ReceptionModel().obs;

  // Convenience getters so widgets reference short paths
  ReceptionEventModel get event => reception.value.event;
  List<SessionModel>  get currentSessions  => reception.value.currentSessions;
  List<SessionModel>  get featuredSessions => reception.value.featuredSessions;
  List<ReceptionSpeakerModel>  get featuredSpeakers => reception.value.featuredSpeakers;
  List<ExhibitorModel> get featuredExhibitors => reception.value.featuredExhibitors;
  List<ExhibitorModel> get featuredSponsors   => reception.value.featuredSponsors;
  List<LeaderboardEntryModel> get leaderboard => reception.value.leaderboard;
  List<BannerModel>   get banners  => reception.value.event.communityBanners;
  List<EventCtaModel> get eventCtas => reception.value.eventCtas;
  AdsModel get addData => reception.value.ads;
  WelcomeVideoModel   get welcomeVideo => reception.value.welcomeVideo;

  // ── API Call ──────────────────────────────────────────────────────────────
  Future<void> fetchHomeData() async {
    await handleApiClient(
      onStateChanged: (state) => dataStatus(state),
      handleApiCall: () async {
        final response = await _service.getReception();
        if (response.data is Map) {
          reception.value = ReceptionModel.fromJson(
            Map<String, dynamic>.from(response.data as Map),
          );
        }
      },
    );
  }
}
