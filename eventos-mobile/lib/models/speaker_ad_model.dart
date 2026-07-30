import '../../utils/helpers/type_helper.dart';
import 'speaker_ad_image_model.dart';

class SpeakerAdModel {
  final int id;
  final String title;
  final List<SpeakerAdImageModel> images;
  final bool isActive;
  final int itemsAfter;

  const SpeakerAdModel({
    this.id = 0,
    this.title = '',
    this.images = const [],
    this.isActive = true,
    this.itemsAfter = 2,
  });

  factory SpeakerAdModel.fromJson(Map<String, dynamic> json) {
    final slots = json['add_slots'] as List? ?? [];
    final speakerSlot = slots.firstWhere(
      (s) => (s as Map)['page'] == 'speakers',
      orElse: () => null,
    );
    final itemsAfter = speakerSlot != null
        ? TypeHelper.toInt((speakerSlot as Map)['items_after'])
        : 2;

    return SpeakerAdModel(
      id: TypeHelper.toInt(json['id']),
      title: json['title'] as String? ?? '',
      images: (json['images'] as List? ?? [])
          .map((e) => SpeakerAdImageModel.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
      isActive: TypeHelper.toBool(json['is_active']),
      itemsAfter: itemsAfter,
    );
  }

  String get firstImageUrl {
    final active = images.where((img) => img.isActive).toList();
    return active.isNotEmpty ? active.first.imageUrl : '';
  }
}
