import '../utils/helpers/type_helper.dart';
import 'session_day_model.dart';
import 'reception_speaker_model.dart';
import 'exhibitor_model.dart';

class SessionDetailModel {
  final int id;
  final String startTime;
  final String endTime;
  final String title;
  final String sessionPlace;
  final String description;
  final String logo;
  final String? file;
  final List<String> tags;
  final bool isFeatured;
  final bool isAllowedToRate;
  final bool isAllowToRegister;
  final bool isStream;
  final String? streamLink;
  final List<ReceptionSpeakerModel> speakers;
  final List<ExhibitorModel> sponsors;
  final SessionDayModel day;
  final String track;

  const SessionDetailModel({
    this.id = 0,
    this.startTime = '',
    this.endTime = '',
    this.title = '',
    this.sessionPlace = '',
    this.description = '',
    this.logo = '',
    this.file,
    this.tags = const [],
    this.isFeatured = false,
    this.isAllowedToRate = false,
    this.isAllowToRegister = false,
    this.isStream = false,
    this.streamLink,
    this.speakers = const [],
    this.sponsors = const [],
    this.day = const SessionDayModel(),
    this.track = '',
  });

  factory SessionDetailModel.fromJson(Map<String, dynamic> json) {
    return SessionDetailModel(
      id: TypeHelper.toInt(json['id']),
      startTime: json['start_time'] as String? ?? '',
      endTime: json['end_time'] as String? ?? '',
      title: json['title'] as String? ?? '',
      sessionPlace: json['session_place'] as String? ?? '',
      description: json['description'] as String? ?? '',
      logo: json['logo'] as String? ?? '',
      file: json['file'] as String?,
      tags: (json['tags'] as List? ?? []).map((e) => e.toString()).toList(),
      isFeatured: TypeHelper.toBool(json['is_featured']),
      isAllowedToRate: TypeHelper.toBool(json['is_allowed_to_rate']),
      isAllowToRegister: TypeHelper.toBool(json['is_allow_to_register']),
      isStream: TypeHelper.toBool(json['is_stream']),
      streamLink: json['stream_link'] as String?,
      speakers: (json['speakers'] as List? ?? [])
          .map((e) => ReceptionSpeakerModel.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
      sponsors: (json['sponsors'] as List? ?? [])
          .map((e) => ExhibitorModel.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
      day: json['day'] is Map
          ? SessionDayModel.fromJson(Map<String, dynamic>.from(json['day']))
          : const SessionDayModel(),
      track: json['track'] as String? ?? '',
    );
  }
}
