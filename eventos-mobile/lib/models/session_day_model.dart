import '../../utils/helpers/type_helper.dart';
import 'session_model.dart';
import 'session_track_model.dart';

class SessionDayModel {
  final int id;
  final String title;
  final String date;
  final String dateLabel;
  final String dayName;
  final List<SessionModel> schedules;
  final List<SessionTrackModel> tracks;

  const SessionDayModel({
    this.id = 0,
    this.title = '',
    this.date = '',
    this.dateLabel = '',
    this.dayName = '',
    this.schedules = const [],
    this.tracks = const [],
  });

  factory SessionDayModel.fromJson(Map<String, dynamic> json) {
    return SessionDayModel(
      id: TypeHelper.toInt(json['id']),
      title: json['title'] as String? ?? '',
      date: json['date'] as String? ?? '',
      dateLabel: (json['date_label'] ?? '') as String,
      dayName: (json['day_name'] ?? '') as String,
      schedules: (json['schedules'] as List? ?? [])
          .map((e) => SessionModel.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
      tracks: (json['tracks'] as List? ?? [])
          .map((e) => SessionTrackModel.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
    );
  }
}
