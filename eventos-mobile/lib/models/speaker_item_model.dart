import '../../utils/helpers/type_helper.dart';
import 'speaker_note_model.dart';

class SpeakerItemModel {
  final int id;
  final String name;
  final String? image;
  final String designation;
  final int? categoryId;
  final String? category;
  final String? presentationTitle;
  final bool isLoved;
  final bool haveNotes;
  final List<SpeakerNoteModel> notes;

  const SpeakerItemModel({
    this.id = 0,
    this.name = '',
    this.image,
    this.designation = '',
    this.categoryId,
    this.category,
    this.presentationTitle,
    this.isLoved = false,
    this.haveNotes = false,
    this.notes = const [],
  });

  factory SpeakerItemModel.fromJson(Map<String, dynamic> json) {
    final String rawImage = json['image'] as String? ?? '';
    final String rawImageUrl = json['image_url'] as String? ?? '';
    final String finalRaw = rawImageUrl.isNotEmpty ? rawImageUrl : rawImage;
    final String? imageUrl = finalRaw.isEmpty
        ? null
        : (finalRaw.startsWith('http')
            ? finalRaw
            : 'https://admin.expouse.com/storage/$finalRaw');

    return SpeakerItemModel(
      id: TypeHelper.toInt(json['id']),
      name: json['name'] as String? ?? '',
      image: imageUrl,
      designation: json['designation'] as String? ?? '',
      categoryId: json['category_id'] as int?,
      category: json['category'] as String?,
      presentationTitle: json['presentation_title'] as String?,
      isLoved: TypeHelper.toBool(json['is_loved']),
      haveNotes: TypeHelper.toBool(json['haveNotes']),
      notes: (json['notes'] as List? ?? [])
          .map((e) => SpeakerNoteModel.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
    );
  }
}
