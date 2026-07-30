class BriefcaseFileModel {
  final String id;
  final String name;
  final String url;
  final DateTime createdAt;

  BriefcaseFileModel({
    required this.id,
    required this.name,
    required this.url,
    required this.createdAt,
  });

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'url': url,
        'createdAt': createdAt.toIso8601String(),
      };

  factory BriefcaseFileModel.fromJson(Map<String, dynamic> json) {
    // API provides 'file' as the url, and we can extract name from it
    String fileUrl = json['file']?.toString() ?? json['url']?.toString() ?? '';
    String fileName = json['name']?.toString() ?? fileUrl.split('/').last;
    
    return BriefcaseFileModel(
      id: json['id']?.toString() ?? '',
      name: fileName,
      url: fileUrl,
      createdAt: DateTime.tryParse(json['created_at']?.toString() ?? json['createdAt']?.toString() ?? '') ?? DateTime.now(),
    );
  }
}

class BriefcaseNoteModel {
  final String id;
  final String noteType; // 'Speaker' | 'Session' | etc.
  final int? entityId;
  final String entityName;
  final String entityRole;
  final String entityImage;
  final String noteText;
  final DateTime createdAt;

  BriefcaseNoteModel({
    required this.id,
    required this.noteType,
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
        'entityId': entityId,
        'entityName': entityName,
        'entityRole': entityRole,
        'entityImage': entityImage,
        'noteText': noteText,
        'createdAt': createdAt.toIso8601String(),
      };

  factory BriefcaseNoteModel.fromJson(Map<String, dynamic> json) => BriefcaseNoteModel(
        id: json['id']?.toString() ?? '',
        noteType: json['noteType']?.toString() ?? '',
        entityId: json['entityId'] as int?,
        entityName: json['entityName']?.toString() ?? '',
        entityRole: json['entityRole']?.toString() ?? '',
        entityImage: json['entityImage']?.toString() ?? '',
        noteText: json['noteText']?.toString() ?? '',
        createdAt: DateTime.tryParse(json['createdAt']?.toString() ?? '') ?? DateTime.now(),
      );

  factory BriefcaseNoteModel.fromApiResponse(Map<String, dynamic> json, String type) {
    String entityName = '';
    String entityRole = '';
    String entityImage = '';
    int? entityId;

    if (type.toLowerCase() == 'speaker') {
      final speaker = json['speaker'] as Map<String, dynamic>?;
      if (speaker != null) {
        entityId = speaker['id'] as int?;
        entityName = speaker['name']?.toString() ?? '';
        entityRole = speaker['designation']?.toString() ?? '';
        entityImage = speaker['image_url']?.toString() ?? '';
      }
    } else if (type.toLowerCase() == 'session') {
      final session = json['session'] as Map<String, dynamic>?;
      if (session != null) {
        entityId = session['id'] as int?;
        entityName = session['title']?.toString() ?? '';
      }
    } else if (type.toLowerCase() == 'delegate') {
      final delegate = json['delegate'] as Map<String, dynamic>?;
      if (delegate != null) {
        entityId = delegate['id'] as int?;
        entityName = delegate['name']?.toString() ?? '';
        entityRole = delegate['designation']?.toString() ?? '';
        entityImage = delegate['image_url']?.toString() ?? '';
      }
    }

    return BriefcaseNoteModel(
      id: json['id']?.toString() ?? '',
      noteType: type,
      entityId: entityId,
      entityName: entityName,
      entityRole: entityRole,
      entityImage: entityImage,
      noteText: json['note']?.toString() ?? '',
      createdAt: DateTime.tryParse(json['created_at']?.toString() ?? '') ?? DateTime.now(),
    );
  }
}

