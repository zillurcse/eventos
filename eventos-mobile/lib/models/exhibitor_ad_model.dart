import '../../utils/helpers/type_helper.dart';
import 'exhibitor_ad_image_model.dart';

class ExhibitorAdModel {
  final int id;
  final String title;
  final List<ExhibitorAdImageModel> images;
  final bool isActive;
  final int itemsAfter;

  const ExhibitorAdModel({
    this.id = 0,
    this.title = '',
    this.images = const [],
    this.isActive = true,
    this.itemsAfter = 2,
  });

  factory ExhibitorAdModel.fromJson(Map<String, dynamic> json) {
    final slots = json['add_slots'] as List? ?? [];
    final exhibitorSlot = slots.firstWhere(
      (s) => (s as Map)['page'] == 'exhibitors',
      orElse: () => null,
    );
    final itemsAfter = exhibitorSlot != null
        ? TypeHelper.toInt((exhibitorSlot as Map)['items_after'])
        : 2;

    return ExhibitorAdModel(
      id: TypeHelper.toInt(json['id']),
      title: json['title'] as String? ?? '',
      images: (json['images'] as List? ?? [])
          .map((e) => ExhibitorAdImageModel.fromJson(Map<String, dynamic>.from(e)))
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
