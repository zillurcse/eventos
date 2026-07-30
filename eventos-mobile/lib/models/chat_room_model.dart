import 'dart:convert';
import '../utils/helpers/type_helper.dart';
import 'message_model.dart';
import 'user.dart';

class ChatRoomModel {
  final int id;
  final MessageModel? latestMessage;
  final User? partner;
  final bool hasNewMessages;

  ChatRoomModel({
    this.id = 0,
    this.latestMessage,
    this.partner,
    this.hasNewMessages = false,
  });

  /// The API places `has_new_messages` inside the nested `user` object as a
  /// JSON-encoded array of room IDs, e.g. "[421]".  A room is unread when its
  /// own `id` appears in that array.
  factory ChatRoomModel.fromJson(Map<String, dynamic> json) {
    final int roomId = TypeHelper.toInt(json['id']);
    final partnerData = json['user'] ?? json['target_user'];

    // Parse the has_new_messages string from the partner user object
    bool hasUnread = false;
    if (partnerData is Map) {
      final raw = partnerData['has_new_messages'];
      if (raw != null) {
        try {
          // It comes as a JSON string like "[421]"
          final decoded = raw is String ? jsonDecode(raw) : raw;
          if (decoded is List) {
            hasUnread = decoded.any((id) => TypeHelper.toInt(id) == roomId);
          }
        } catch (_) {
          // Fallback: treat any non-null non-empty value as "has unread"
          hasUnread = raw.toString().isNotEmpty && raw.toString() != 'null' && raw.toString() != '[]';
        }
      }
    }

    return ChatRoomModel(
      id: roomId,
      latestMessage: json['latest_message'] != null
          ? MessageModel.fromJson(json['latest_message'])
          : null,
      partner: partnerData != null ? User.fromJson(partnerData) : null,
      hasNewMessages: hasUnread,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'latest_message': latestMessage?.toJson(),
      'user': partner?.toJson(),
      'has_new_messages': hasNewMessages,
    };
  }
}

