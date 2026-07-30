class FeedTabModel {
  final String name;
  final String key;
  final String icon;
  final String changedName;
  final int order;
  final bool status;

  const FeedTabModel({
    required this.name,
    required this.key,
    required this.icon,
    required this.changedName,
    required this.order,
    required this.status,
  });

  factory FeedTabModel.fromJson(Map<String, dynamic> json) => FeedTabModel(
        name: json['name'] as String? ?? '',
        key: json['key'] as String? ?? '',
        icon: json['icon'] as String? ?? '',
        changedName: json['changed_name'] as String? ?? '',
        order: json['order'] as int? ?? 0,
        status: json['status'] as bool? ?? false,
      );
}
