class NotificationResponseModel {
  final int currentPage;
  final int unreadCount;
  final int lastPage;
  final List<NotificationItemModel> data;

  NotificationResponseModel({
    this.currentPage = 1,
    this.unreadCount = 0,
    this.lastPage = 1,
    this.data = const [],
  });

  factory NotificationResponseModel.fromJson(Map<String, dynamic> json) {
    int unreadCount = json['unread_count'] is int ? json['unread_count'] : 0;
    
    int currentPage = 1;
    int lastPage = 1;
    List<NotificationItemModel> data = [];

    if (json['notifications'] is Map) {
      final notifs = json['notifications'] as Map<String, dynamic>;
      currentPage = notifs['current_page'] is int ? notifs['current_page'] : 1;
      lastPage = notifs['last_page'] is int ? notifs['last_page'] : 1;
      
      if (notifs['data'] is List) {
        data = (notifs['data'] as List)
            .map((e) => NotificationItemModel.fromJson(Map<String, dynamic>.from(e)))
            .toList();
      }
    } else {
      currentPage = json['current_page'] is int ? json['current_page'] : 1;
    }

    return NotificationResponseModel(
      currentPage: currentPage,
      unreadCount: unreadCount,
      lastPage: lastPage,
      data: data,
    );
  }
}

class NotificationItemModel {
  final int id;
  final String title;
  final String context;
  final String type;
  final String url;
  final String? readAt;
  final DateTime? createdAt;
  final NotificationUserModel? user;

  NotificationItemModel({
    required this.id,
    required this.title,
    required this.context,
    required this.type,
    required this.url,
    this.readAt,
    this.createdAt,
    this.user,
  });

  factory NotificationItemModel.fromJson(Map<String, dynamic> json) {
    return NotificationItemModel(
      id: json['id'] is int ? json['id'] : 0,
      title: json['title']?.toString() ?? '',
      context: json['context']?.toString() ?? '',
      type: json['type']?.toString() ?? '',
      url: json['url']?.toString() ?? '',
      readAt: json['read_at']?.toString(),
      createdAt: json['created_at'] != null ? DateTime.tryParse(json['created_at'].toString()) : null,
      user: json['user'] is Map ? NotificationUserModel.fromJson(Map<String, dynamic>.from(json['user'])) : null,
    );
  }

  bool get isRead => readAt != null && readAt!.isNotEmpty;
}

class NotificationUserModel {
  final int id;
  final String name;
  final String? designation;
  final String profilePhotoUrl;

  NotificationUserModel({
    required this.id,
    required this.name,
    this.designation,
    required this.profilePhotoUrl,
  });

  factory NotificationUserModel.fromJson(Map<String, dynamic> json) {
    return NotificationUserModel(
      id: json['id'] is int ? json['id'] : 0,
      name: json['name']?.toString() ?? '',
      designation: json['designation']?.toString(),
      profilePhotoUrl: json['profile_photo_url']?.toString() ?? '',
    );
  }
}
