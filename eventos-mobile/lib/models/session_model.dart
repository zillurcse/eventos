import '../../utils/helpers/type_helper.dart';
import 'session_day_model.dart';
import 'reception_speaker_model.dart';
import 'session_sponsor_model.dart';

class SessionModel {
  final int id;
  final String title;
  final String startTime;
  final String endTime;
  final String logoUrl;
  final SessionDayModel day;
  final List<ReceptionSpeakerModel> speakers;
  final List<SessionSponsorModel> sponsors;
  final List<String> tags;
  final String sessionPlace;
  final bool isFavorite;
  final bool inMySchedule;

  const SessionModel({
    this.id = 0,
    this.title = '',
    this.startTime = '',
    this.endTime = '',
    this.logoUrl = '',
    this.day = const SessionDayModel(),
    this.speakers = const [],
    this.sponsors = const [],
    this.tags = const [],
    this.sessionPlace = '',
    this.isFavorite = false,
    this.inMySchedule = false,
  });

  factory SessionModel.fromJson(Map<String, dynamic> json) {
    return SessionModel(
      id: TypeHelper.toInt(json['id']),
      title: json['title'] as String? ?? '',
      startTime: json['start_time'] as String? ?? '',
      endTime: json['end_time'] as String? ?? '',
      logoUrl: json['logo_url'] as String? ?? '',
      day: json['day'] is Map
          ? SessionDayModel.fromJson(Map<String, dynamic>.from(json['day']))
          : const SessionDayModel(),
      speakers: (json['speakers'] as List? ?? [])
          .map((e) => ReceptionSpeakerModel.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
      sponsors: (json['sponsors'] as List? ?? [])
          .map((e) => SessionSponsorModel.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
      tags: (json['tags'] as List? ?? [])
          .map((e) => e.toString())
          .toList(),
      sessionPlace: json['session_place'] as String? ?? '',
      isFavorite: TypeHelper.toBool(json['is_favorite']),
      inMySchedule: TypeHelper.toBool(json['in_my_schedule']),
    );
  }
}
