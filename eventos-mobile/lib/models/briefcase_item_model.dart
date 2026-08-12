import '../../utils/helpers/type_helper.dart';

class BriefcaseFileModel {
  final String id;
  final String name;
  final String url;
  final String kind;
  final DateTime createdAt;

  BriefcaseFileModel({
    required this.id,
    required this.name,
    required this.url,
    this.kind = 'file',
    required this.createdAt,
  });

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'url': url,
        'kind': kind,
        'createdAt': createdAt.toIso8601String(),
      };

  factory BriefcaseFileModel.fromJson(Map<String, dynamic> json) {
    // V1: { id, title, url, kind } - legacy also had file/name/created_at.
    final fileUrl = json['url']?.toString() ?? json['file']?.toString() ?? '';
    final fileName = json['title']?.toString() ??
        json['name']?.toString() ??
        (fileUrl.isNotEmpty ? fileUrl.split('/').last : 'File');

    return BriefcaseFileModel(
      id: json['id']?.toString() ?? '',
      name: fileName,
      url: fileUrl,
      kind: json['kind']?.toString() ?? 'file',
      createdAt: DateTime.tryParse(
            json['created_at']?.toString() ?? json['createdAt']?.toString() ?? '',
          ) ??
          DateTime.now(),
    );
  }
}

class BriefcaseNoteModel {
  final String id;
  final String noteType; // 'Speaker' | 'Session' | 'Delegate'
  final String targetId;
  final int? entityId;
  final String entityName;
  final String entityRole;
  final String entityImage;
  final String noteText;
  final DateTime createdAt;

  BriefcaseNoteModel({
    required this.id,
    required this.noteType,
    this.targetId = '',
    this.entityId,
    required this.entityName,
    required this.entityRole,
    required this.entityImage,
    required this.noteText,
    required this.createdAt,
  });

  Map<String, dynamic> toJson() => {
        'id': id,
        'noteType': noteType,
        'targetId': targetId,
        'entityId': entityId,
        'entityName': entityName,
        'entityRole': entityRole,
        'entityImage': entityImage,
        'noteText': noteText,
        'createdAt': createdAt.toIso8601String(),
      };

  factory BriefcaseNoteModel.fromJson(Map<String, dynamic> json) =>
      BriefcaseNoteModel(
        id: json['id']?.toString() ?? '',
        noteType: json['noteType']?.toString() ?? '',
        targetId: json['targetId']?.toString() ?? '',
        entityId: json['entityId'] as int?,
        entityName: json['entityName']?.toString() ?? '',
        entityRole: json['entityRole']?.toString() ?? '',
        entityImage: json['entityImage']?.toString() ?? '',
        noteText: json['noteText']?.toString() ?? '',
        createdAt:
            DateTime.tryParse(json['createdAt']?.toString() ?? '') ??
                DateTime.now(),
      );

  /// V1 notes: { id, target_id, text, created_at, updated_at }
  factory BriefcaseNoteModel.fromApiResponse(
    Map<String, dynamic> json,
    String type, {
    String entityName = '',
    String entityRole = '',
    String entityImage = '',
  }) {
    final targetId = json['target_id']?.toString() ?? '';
    final displayType = type.isEmpty
        ? type
        : '${type[0].toUpperCase()}${type.substring(1)}';

    // Legacy nested shapes (speaker/session/delegate objects).
    String name = entityName;
    String role = entityRole;
    String image = entityImage;
    int? entityId = targetId.isNotEmpty ? TypeHelper.toInt(targetId) : null;

    if (type.toLowerCase() == 'speaker') {
      final speaker = json['speaker'] as Map<String, dynamic>?;
      if (speaker != null) {
        entityId = TypeHelper.toInt(speaker['id']);
        name = speaker['name']?.toString() ?? name;
        role = speaker['designation']?.toString() ?? role;
        image = speaker['image_url']?.toString() ?? image;
      }
    } else if (type.toLowerCase() == 'session') {
      final session = json['session'] as Map<String, dynamic>?;
      if (session != null) {
        entityId = TypeHelper.toInt(session['id']);
        name = session['title']?.toString() ?? name;
      }
    } else if (type.toLowerCase() == 'delegate') {
      final delegate = json['delegate'] as Map<String, dynamic>?;
      if (delegate != null) {
        entityId = TypeHelper.toInt(delegate['id']);
        name = delegate['name']?.toString() ?? name;
        role = delegate['designation']?.toString() ??
            delegate['job_title']?.toString() ??
            role;
        image = delegate['image_url']?.toString() ??
            delegate['avatar_url']?.toString() ??
            image;
      }
    }

    return BriefcaseNoteModel(
      id: json['id']?.toString() ?? '',
      noteType: displayType,
      targetId: targetId,
      entityId: entityId,
      entityName: name.isNotEmpty ? name : 'Note',
      entityRole: role,
      entityImage: image,
      noteText: json['text']?.toString() ?? json['note']?.toString() ?? '',
      createdAt: DateTime.tryParse(
            json['created_at']?.toString() ?? json['updated_at']?.toString() ?? '',
          ) ??
          DateTime.now(),
    );
  }
}
