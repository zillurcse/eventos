import '../../utils/helpers/type_helper.dart';

class SpeakerNoteModel {
  final int id;
  final String note;
  final String createdAt;
  final String updatedAt;

  const SpeakerNoteModel({
    this.id = 0,
    this.note = '',
    this.createdAt = '',
    this.updatedAt = '',
  });

  factory SpeakerNoteModel.fromJson(Map<String, dynamic> json) {
    return SpeakerNoteModel(
      id: TypeHelper.toInt(json['id']),
      note: json['note'] as String? ?? '',
      createdAt: json['created_at'] as String? ?? '',
      updatedAt: json['updated_at'] as String? ?? '',
    );
  }
}
