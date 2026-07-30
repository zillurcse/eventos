import '../../utils/helpers/type_helper.dart';

class SessionTrackModel {
  final int id;
  final String title;

  const SessionTrackModel({
    this.id = 0,
    this.title = '',
  });

  factory SessionTrackModel.fromJson(Map<String, dynamic> json) {
    return SessionTrackModel(
      id: TypeHelper.toInt(json['id']),
      title: json['title'] as String? ?? '',
    );
  }
}
