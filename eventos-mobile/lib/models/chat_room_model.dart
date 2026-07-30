import 'chat_person.dart';
import 'message_model.dart';

class ChatRoomModel {
  final String id;
  final ChatPerson? partner;
  final int unread;
  final MessageModel? latestMessage;

  ChatRoomModel({
    this.id = '',
    this.partner,
    this.unread = 0,
    this.latestMessage,
  });

  bool get hasNewMessages => unread > 0;

  factory ChatRoomModel.fromJson(Map<String, dynamic> json) {
    MessageModel? latest;
    final last = json['last_message'];
    if (last is Map) {
      latest = MessageModel(
        id: '',
        body: last['body']?.toString(),
        mine: last['mine'] == true,
        createdAt: last['created_at']?.toString() ?? '',
        updatedAt: last['created_at'] != null
            ? DateTime.tryParse(last['created_at'].toString())
            : null,
      );
    }

    return ChatRoomModel(
      id: json['id']?.toString() ?? '',
      partner: json['with'] is Map
          ? ChatPerson.fromJson(Map<String, dynamic>.from(json['with'] as Map))
          : null,
      unread: json['unread'] is int
          ? json['unread'] as int
          : int.tryParse('${json['unread']}') ?? 0,
      latestMessage: latest,
    );
  }

  ChatRoomModel copyWith({
    String? id,
    ChatPerson? partner,
    int? unread,
    MessageModel? latestMessage,
  }) {
    return ChatRoomModel(
      id: id ?? this.id,
      partner: partner ?? this.partner,
      unread: unread ?? this.unread,
      latestMessage: latestMessage ?? this.latestMessage,
    );
  }
}
