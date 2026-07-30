import 'feed_user_model.dart';

class FeedCommentModel {
  final int id;
  final String body;
  final FeedUserModel user;
  final DateTime diff;

  const FeedCommentModel({
    required this.id,
    required this.body,
    required this.user,
    required this.diff,
  });

  factory FeedCommentModel.fromJson(Map<String, dynamic> json) =>
      FeedCommentModel(
        id: json['id'] as int? ?? 0,
        body: json['body'] as String? ?? '',
        user: FeedUserModel.fromJson(
            Map<String, dynamic>.from(json['user'] as Map)),
        diff: DateTime.parse(json['diff'] as String? ?? ''),
      );
}
