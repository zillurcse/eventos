import '../../utils/helpers/type_helper.dart';

class LeaderboardEntryModel {
  final int id;
  final int points;
  final String userName;
  final String userPhotoUrl;
  final int rank;

  const LeaderboardEntryModel({
    this.id = 0,
    this.points = 0,
    this.userName = '',
    this.userPhotoUrl = '',
    this.rank = 0,
  });

  factory LeaderboardEntryModel.fromJson(Map<String, dynamic> json, {int rank = 0}) {
    final attendee = json['attendee'] as Map?;
    final user = attendee?['user'] as Map?;
    final directUser = json['user'] as Map?;
    final resolvedUser = user ?? directUser ?? {};

    return LeaderboardEntryModel(
      id: TypeHelper.toInt(json['id']),
      points: TypeHelper.toInt(json['points']),
      userName: resolvedUser['name'] as String? ?? '',
      userPhotoUrl: resolvedUser['profile_photo_url'] as String? ?? '',
      rank: rank,
    );
  }
}
