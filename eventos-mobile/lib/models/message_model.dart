import '../utils/helpers/type_helper.dart';

class MessageModel {
  final int id;
  final int userId;
  final String? body;
  final String? attach;
  final String? attachType;
  final String createdAt;
  final DateTime? updatedAt;
  final String? previewUrl;

  MessageModel({
    this.id = 0,
    this.userId = 0,
    this.body,
    this.attach,
    this.attachType,
    this.createdAt = '',
    this.updatedAt,
    this.previewUrl = '',
  });

  factory MessageModel.fromJson(Map<String, dynamic> json) {
    return MessageModel(
      id: TypeHelper.toInt(json['id']),
      userId: TypeHelper.toInt(json['user_id']),
      body: json['body'] as String? ?? json['context'] as String?,
      attach: json['attach'] as String?,
      previewUrl: json['previewUrl'] as String?,
      attachType: json['attach_type'] as String?,
      createdAt: json['created_at'] as String? ?? '',
      updatedAt: json['updated_at'] != null ? DateTime.parse(json['updated_at']) : null,
    );
  }
  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'user_id': userId,
      'body': body,
      'attach': attach,
      'attach_type': attachType,
      'created_at': createdAt,
    };
  }
}
