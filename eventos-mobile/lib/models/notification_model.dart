class NotificationResponseModel {
  final int unreadCount;
  final List<NotificationItemModel> data;

  NotificationResponseModel({
    this.unreadCount = 0,
    this.data = const [],
  });

  factory NotificationResponseModel.fromJson(Map<String, dynamic> json) {
    final unread = json['unread'] is int
        ? json['unread'] as int
        : int.tryParse('${json['unread']}') ?? 0;

    final raw = json['data'];
    final items = raw is List
        ? raw
            .whereType<Map>()
            .map((e) =>
                NotificationItemModel.fromJson(Map<String, dynamic>.from(e)))
            .toList()
        : <NotificationItemModel>[];

    return NotificationResponseModel(unreadCount: unread, data: items);
  }
}

class NotificationItemModel {
  final String id;
  final String title;
  final String body;
  final String status;
  final String? readAt;
  final DateTime? createdAt;
  final String? templateKey;
  final Map<String, dynamic> data;

  NotificationItemModel({
    required this.id,
    required this.title,
    this.body = '',
    this.status = '',
    this.readAt,
    this.createdAt,
    this.templateKey,
    this.data = const {},
  });

  /// Legacy alias used by the notifications list UI.
  String get context => body.isNotEmpty ? body : title;

  factory NotificationItemModel.fromJson(Map<String, dynamic> json) {
    return NotificationItemModel(
      id: json['id']?.toString() ?? '',
      title: json['title']?.toString() ?? '',
      body: json['body']?.toString() ?? json['context']?.toString() ?? '',
      status: json['status']?.toString() ?? '',
      readAt: json['read_at']?.toString(),
      createdAt: json['created_at'] != null
          ? DateTime.tryParse(json['created_at'].toString())
          : null,
      templateKey: json['template_key']?.toString(),
      data: json['data'] is Map
          ? Map<String, dynamic>.from(json['data'] as Map)
          : const {},
    );
  }

  bool get isRead =>
      (readAt != null && readAt!.isNotEmpty) || status == 'read';

  String? get conversationId => data['conversation_id']?.toString();
  String? get eventUuid => data['event_uuid']?.toString();
  String get type => data['type']?.toString() ?? templateKey ?? '';
}
