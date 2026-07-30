class FeedPollOptionModel {
  final int id;
  final String option;
  final int votes;

  const FeedPollOptionModel({
    required this.id,
    required this.option,
    required this.votes,
  });

  factory FeedPollOptionModel.fromJson(Map<String, dynamic> json) =>
      FeedPollOptionModel(
        id: json['id'] as int? ?? 0,
        option: json['option'] as String? ?? '',
        votes: json['votes'] as int? ?? 0,
      );
}
