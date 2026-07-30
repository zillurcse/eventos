import 'ads_model.dart';
import 'event_cta_model.dart';
import 'exhibitor_model.dart';
import 'leaderboard_entry_model.dart';
import 'reception_event_model.dart';
import 'reception_speaker_model.dart';
import 'session_model.dart';
import 'welcome_video_model.dart';

class ReceptionModel {
  final ReceptionEventModel event;
  final AdsModel ads;
  final WelcomeVideoModel welcomeVideo;
  final List<SessionModel> currentSessions;
  final List<SessionModel> featuredSessions;
  final List<ReceptionSpeakerModel> featuredSpeakers;
  final List<ExhibitorModel> featuredExhibitors;
  final List<ExhibitorModel> featuredSponsors;
  final List<LeaderboardEntryModel> leaderboard;
  final List<EventCtaModel> eventCtas;

  const ReceptionModel({
    this.event = const ReceptionEventModel(),
    this.ads = const AdsModel(),
    this.welcomeVideo = const WelcomeVideoModel(),
    this.currentSessions = const [],
    this.featuredSessions = const [],
    this.featuredSpeakers = const [],
    this.featuredExhibitors = const [],
    this.featuredSponsors = const [],
    this.leaderboard = const [],
    this.eventCtas = const [],
  });

  factory ReceptionModel.fromJson(Map<String, dynamic> json) {
    final lbRaw = json['leaderboard'];
    List<LeaderboardEntryModel> lb = [];
    if (lbRaw is Map) {
      final original = lbRaw['original'];
      if (original is Map) {
        final entries = original['user_leaderboard'] as List? ?? [];
        lb = entries
            .asMap()
            .entries
            .map((e) => LeaderboardEntryModel.fromJson(
                Map<String, dynamic>.from(e.value),
                rank: e.key + 1))
            .toList();
      }
    }

    return ReceptionModel(
      event: json['event'] is Map
          ? ReceptionEventModel.fromJson(Map<String, dynamic>.from(json['event']))
          : const ReceptionEventModel(),
      ads: json['adds'] is Map
          ? AdsModel.fromJson(Map<String, dynamic>.from(json['adds']))
          : const AdsModel(),
      welcomeVideo: json['welcome_video'] is Map
          ? WelcomeVideoModel.fromJson(Map<String, dynamic>.from(json['welcome_video']))
          : const WelcomeVideoModel(),
      currentSessions: (json['current_sessions'] as List? ?? [])
          .map((e) => SessionModel.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
      featuredSessions: (json['featured_sessions'] as List? ?? [])
          .map((e) => SessionModel.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
      featuredSpeakers: (json['featured_speakers'] as List? ?? [])
          .map((e) => ReceptionSpeakerModel.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
      featuredExhibitors: (json['featured_exhibitors'] as List? ?? [])
          .map((e) => ExhibitorModel.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
      featuredSponsors: (json['featured_sponsors'] as List? ?? [])
          .map((e) => ExhibitorModel.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
      leaderboard: lb,
      eventCtas: (json['event_ctas'] as List? ?? [])
          .map((e) => EventCtaModel.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
    );
  }
}
