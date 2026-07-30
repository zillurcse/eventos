import '../utils/helpers/type_helper.dart';

class FeedPollOptionModel {
  final int id;
  /// EventOS option id (e.g. `o1`) — used for poll vote API.
  final String uuid;
  final String option;
  final int votes;

  const FeedPollOptionModel({
    required this.id,
    this.uuid = '',
    required this.option,
    required this.votes,
  });

  factory FeedPollOptionModel.fromJson(Map<String, dynamic> json) {
    final rawId = json['id'];
    final uuid = (json['uuid'] ?? rawId ?? '').toString();
    return FeedPollOptionModel(
      id: TypeHelper.toInt(rawId),
      uuid: uuid,
      option: (json['option'] ?? json['text'] ?? '').toString(),
      votes: TypeHelper.toInt(json['votes']),
    );
  }
}
