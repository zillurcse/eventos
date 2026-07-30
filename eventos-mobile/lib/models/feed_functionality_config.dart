import 'feed_tab_model.dart';

class FeedFunctionalityConfig {
  final List<String> permissions;
  final List<FeedTabModel> tabs;

  const FeedFunctionalityConfig({
    required this.permissions,
    required this.tabs,
  });

  factory FeedFunctionalityConfig.fromJson(Map<String, dynamic> json) =>
      FeedFunctionalityConfig(
        permissions: (json['permissions'] as List? ?? [])
            .map((e) => e as String)
            .toList(),
        tabs: (json['tabs'] as List? ?? [])
            .map((e) => FeedTabModel.fromJson(Map<String, dynamic>.from(e as Map)))
            .toList(),
      );
}
