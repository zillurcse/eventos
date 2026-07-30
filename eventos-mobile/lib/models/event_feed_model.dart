import 'feed_functionality_config.dart';
import 'feed_post_model.dart';

export 'feed_comment_model.dart';
export 'feed_functionality_config.dart';
export 'feed_poll_option_model.dart';
export 'feed_post_model.dart';
export 'feed_tab_model.dart';
export 'feed_user_model.dart';

class EventFeedModel {
  final List<FeedPostModel> posts;
  final String? nextPageUrl;
  final int currentPage;
  final FeedFunctionalityConfig? functionalityConfig;

  const EventFeedModel({
    required this.posts,
    this.nextPageUrl,
    required this.currentPage,
    this.functionalityConfig,
  });

  factory EventFeedModel.fromJson(Map<String, dynamic> json) => EventFeedModel(
        posts: (json['posts'] as List? ?? [])
            .map((e) => FeedPostModel.fromJson(Map<String, dynamic>.from(e as Map)))
            .toList(),
        nextPageUrl: json['next_page_url'] as String?,
        currentPage: json['current_page'] as int? ?? 1,
        functionalityConfig: json['functionality_config'] != null
            ? FeedFunctionalityConfig.fromJson(
                Map<String, dynamic>.from(json['functionality_config'] as Map))
            : null,
      );
}
