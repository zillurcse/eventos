import 'package:get/get.dart';

import '../../models/ads_model.dart';
import '../../models/banner_model.dart';
import '../../models/event_cta_model.dart';
import '../../models/exhibitor_model.dart';
import '../../models/leaderboard_entry_model.dart';
import '../../models/mappers/reception_mapper.dart';
import '../../models/reception_event_model.dart';
import '../../models/reception_model.dart';
import '../../models/reception_speaker_model.dart';
import '../../models/session_model.dart';
import '../../models/welcome_video_model.dart';
import '../../utils/enum/enums.dart';
import '../../utils/helpers/app_data_provider.dart';
import '../../utils/helpers/helper_functions.dart';
import 'home_service.dart';

class HomeController extends GetxController {
  final _service = HomeService();

  final dataStatus = ApiState.initial.obs;
  final Rx<ReceptionModel> reception = const ReceptionModel().obs;

  ReceptionEventModel get event => reception.value.event;
  List<SessionModel> get currentSessions => reception.value.currentSessions;
  List<SessionModel> get featuredSessions => reception.value.featuredSessions;
  List<ReceptionSpeakerModel> get featuredSpeakers =>
      reception.value.featuredSpeakers;
  List<ExhibitorModel> get featuredExhibitors =>
      reception.value.featuredExhibitors;
  List<ExhibitorModel> get featuredSponsors =>
      reception.value.featuredSponsors;
  List<LeaderboardEntryModel> get leaderboard => reception.value.leaderboard;
  List<BannerModel> get banners => reception.value.event.communityBanners;
  List<EventCtaModel> get eventCtas => reception.value.eventCtas;
  AdsModel get addData => reception.value.ads;
  WelcomeVideoModel get welcomeVideo => reception.value.welcomeVideo;

  Future<void> fetchHomeData() async {
    await handleApiClient(
      onStateChanged: (state) => dataStatus(state),
      handleApiCall: () async {
        final receptionResponse = await _service.getReception();
        final receptionBody = receptionResponse.data;
        if (receptionBody is! Map) {
          throw Exception('Unexpected reception response');
        }

        final payload = receptionBody['data'] is Map
            ? Map<String, dynamic>.from(receptionBody['data'] as Map)
            : Map<String, dynamic>.from(receptionBody);

        // Persist event UUID for later participant routes.
        final eventMeta = payload['event'];
        if (eventMeta is Map) {
          final uuid = eventMeta['uuid']?.toString();
          if (uuid != null && uuid.isNotEmpty) {
            AppDataProvider.obj.eventUuid = uuid;
          }
        }

        Map<String, dynamic>? welcomeVideo;
        try {
          final siteResponse = await _service.getSite();
          final siteBody = siteResponse.data;
          if (siteBody is Map) {
            final siteData = siteBody['data'] is Map
                ? Map<String, dynamic>.from(siteBody['data'] as Map)
                : null;
            final navigation = siteData?['navigation'];
            if (navigation is Map && navigation['welcome_video'] is Map) {
              welcomeVideo = Map<String, dynamic>.from(
                navigation['welcome_video'] as Map,
              );
            }
          }
        } catch (_) {
          // Welcome video is optional; reception still renders without it.
        }

        reception.value = ReceptionMapper.fromV1(
          data: payload,
          welcomeVideo: welcomeVideo,
        );
      },
    );
  }
}
