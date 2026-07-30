import 'chat_attachment.dart';

class MessageModel {
  final String id;
  final String? body;
  final List<ChatAttachment> attachments;
  final bool mine;
  final String? readAt;
  final String createdAt;
  final DateTime? updatedAt;

  /// Local-only optimistic path before upload finishes.
  final String? localFilePath;

  MessageModel({
    this.id = '',
    this.body,
    this.attachments = const [],
    this.mine = false,
    this.readAt,
    this.createdAt = '',
    this.updatedAt,
    this.localFilePath,
  });

  String? get attachType {
    if (attachments.isEmpty) return null;
    return attachments.first.kind;
  }

  String? get attach {
    if (localFilePath != null) return localFilePath;
    if (attachments.isEmpty) return null;
    return attachments.first.url;
  }

  String? get previewUrl => attach;

  factory MessageModel.fromJson(Map<String, dynamic> json) {
    final rawAttachments = json['attachments'];
    final attachments = rawAttachments is List
        ? rawAttachments
            .whereType<Map>()
            .map((e) => ChatAttachment.fromJson(Map<String, dynamic>.from(e)))
            .toList()
        : <ChatAttachment>[];

    final created = json['created_at']?.toString() ?? '';

    return MessageModel(
      id: json['id']?.toString() ?? '',
      body: json['body'] as String? ?? json['context'] as String?,
      attachments: attachments,
      mine: json['mine'] == true,
      readAt: json['read_at']?.toString(),
      createdAt: created,
      updatedAt: created.isNotEmpty ? DateTime.tryParse(created) : null,
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'body': body,
        'attachments': attachments.map((a) => a.toJson()).toList(),
        'mine': mine,
        'read_at': readAt,
        'created_at': createdAt,
      };

  bool get isOptimistic => id.isEmpty || id.startsWith('temp-');
}
