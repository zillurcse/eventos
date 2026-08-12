import 'feed_user_model.dart';

class FeedCommentModel {
  final int id;
  final int? parentId;
  final String body;
  final FeedUserModel user;
  final DateTime diff;

  const FeedCommentModel({
    required this.id,
    this.parentId,
    required this.body,
    required this.user,
    required this.diff,
  });

  factory FeedCommentModel.fromJson(Map<String, dynamic> json) =>
      FeedCommentModel(
        id: json['id'] as int? ?? 0,
        parentId: json['parent_id'] as int?,
        body: json['body'] as String? ?? '',
        user: FeedUserModel.fromJson(
            Map<String, dynamic>.from(json['user'] as Map)),
        diff: DateTime.parse(json['diff'] as String? ?? ''),
      );
}
