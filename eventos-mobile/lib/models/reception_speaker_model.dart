import '../../utils/helpers/type_helper.dart';

/// Lightweight speaker model used within session/reception responses.
/// For the full speaker list, see [SpeakerItemModel] in speaker_item_model.dart.
class ReceptionSpeakerModel {
  final int id;
  final String name;
  final String imageUrl;
  final String designation;
  final String company;
  final bool isFeatured;

  const ReceptionSpeakerModel({
    this.id = 0,
    this.name = '',
    this.imageUrl = '',
    this.designation = '',
    this.company = '',
    this.isFeatured = false,
  });

  factory ReceptionSpeakerModel.fromJson(Map<String, dynamic> json) {
    final String rawImageUrl = json['image_url'] as String? ?? '';
    final String rawImage = json['image'] as String? ?? '';
    final String finalRaw = rawImageUrl.isNotEmpty ? rawImageUrl : rawImage;
    final String imageUrl = finalRaw.isEmpty
        ? ''
        : (finalRaw.startsWith('http')
            ? finalRaw
            : 'https://admin.expouse.com/storage/$finalRaw');

    return ReceptionSpeakerModel(
      id: TypeHelper.toInt(json['id']),
      name: json['name'] as String? ?? '',
      imageUrl: imageUrl,
      designation: json['designation'] as String? ?? '',
      company: json['company'] as String? ?? '',
      isFeatured: TypeHelper.toBool(json['is_featured']),
    );
  }
}
