import 'feed_comment_model.dart';
import 'feed_poll_option_model.dart';
import 'feed_user_model.dart';

class FeedPostModel {
  final int id;
  final String? body;
  final int userId;
  final int like;
  final String? attach;
  final String? attachUrl;
  final String? attachType;
  final String? question;
  final String type;
  final bool isLive;
  final bool isResultPublished;
  final bool isScheduled;
  final String? scheduleAt;
  final String? pollEndAt;
  final String status;
  final FeedUserModel user;
  final DateTime createdAtTime;
  final DateTime createdAtDate;
  final bool isLiked;
  final List<FeedCommentModel> comments;
  final bool commentOpen;

  // Poll-specific
  final int? totalVotes;
  final bool? voteByThisUser;
  final int? myVote;
  final List<FeedPollOptionModel> options;

  const FeedPostModel({
    required this.id,
    this.body,
    required this.userId,
    required this.like,
    this.attach,
    this.attachUrl,
    this.attachType,
    this.question,
    required this.type,
    required this.isLive,
    required this.isResultPublished,
    required this.isScheduled,
    this.scheduleAt,
    this.pollEndAt,
    required this.status,
    required this.user,
    required this.createdAtTime,
    required this.createdAtDate,
    required this.isLiked,
    required this.comments,
    required this.commentOpen,
    this.totalVotes,
    this.voteByThisUser,
    this.myVote,
    required this.options,
  });

  factory FeedPostModel.fromJson(Map<String, dynamic> json) => FeedPostModel(
        id: json['id'] as int? ?? 0,
        body: json['body'] as String?,
        userId: json['user_id'] as int? ?? 0,
        like: json['like'] as int? ?? 0,
        attach: json['attach'] as String?,
        attachUrl: json['attach_url'] as String?,
        attachType: json['attach_type'] as String?,
        question: json['question'] as String?,
        type: json['type'] as String? ?? 'post',
        isLive: (json['is_live'] as int? ?? 0) == 1,
        isResultPublished: (json['is_result_published'] as int? ?? 0) == 1,
        isScheduled: (json['is_scheduled'] as int? ?? 0) == 1,
        scheduleAt: json['schedule_at'] as String?,
        pollEndAt: json['poll_end_at'] as String?,
        status: json['status'] as String? ?? '',
        user: FeedUserModel.fromJson(
            Map<String, dynamic>.from(json['user'] as Map)),
        createdAtTime: DateTime.parse(json['created_at_time'] as String? ?? ''),
        createdAtDate: DateTime.parse(json['created_at_date'] as String? ?? ''),
        isLiked: json['is_liked'] as bool? ?? false,
        comments: (json['comments'] as List? ?? [])
            .map((e) => FeedCommentModel.fromJson(
                Map<String, dynamic>.from(e as Map)))
            .toList(),
        commentOpen: json['comment_open'] as bool? ?? false,
        totalVotes: json['total_votes'] as int?,
        voteByThisUser: json['vote_by_this_user'] as bool?,
        myVote: json['my_vote'] as int?,
        options: (json['options'] as List? ?? [])
            .map((e) => FeedPollOptionModel.fromJson(
                Map<String, dynamic>.from(e as Map)))
            .toList(),
      );
}
