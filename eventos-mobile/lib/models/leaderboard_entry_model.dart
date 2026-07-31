import '../../utils/helpers/type_helper.dart';

class LeaderboardEntryModel {
  final int id;
  final int points;
  final String userName;
  final String userPhotoUrl;
  final int rank;
  final bool isMe;
  final String? role;

  const LeaderboardEntryModel({
    this.id = 0,
    this.points = 0,
    this.userName = '',
    this.userPhotoUrl = '',
    this.rank = 0,
    this.isMe = false,
    this.role,
  });

  factory LeaderboardEntryModel.fromJson(Map<String, dynamic> json, {int rank = 0}) {
    // V1 gamification: { rank, name, role, avatar_url, points, is_me }
    if (json.containsKey('name') || json.containsKey('avatar_url') || json.containsKey('is_me')) {
      final name = json['name']?.toString() ?? '';
      return LeaderboardEntryModel(
        id: TypeHelper.toInt(json['id'] ?? name),
        points: TypeHelper.toInt(json['points']),
        userName: name,
        userPhotoUrl: json['avatar_url']?.toString() ?? '',
        rank: TypeHelper.toInt(json['rank']).clamp(0, 9999) == 0
            ? rank
            : TypeHelper.toInt(json['rank']),
        isMe: TypeHelper.toBool(json['is_me']),
        role: json['role']?.toString(),
      );
    }

    // Legacy nested shape (attendee.user / user).
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
