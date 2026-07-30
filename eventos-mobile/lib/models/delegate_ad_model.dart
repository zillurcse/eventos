import '../../utils/helpers/type_helper.dart';
import 'delegate_ad_image_model.dart';

class DelegateAdModel {
  final int id;
  final String title;
  final List<DelegateAdImageModel> images;
  final bool isActive;
  final int itemsAfter;

  const DelegateAdModel({
    this.id = 0,
    this.title = '',
    this.images = const [],
    this.isActive = true,
    this.itemsAfter = 2,
  });

  factory DelegateAdModel.fromJson(Map<String, dynamic> json) {
    final slots = json['add_slots'] as List? ?? [];
    final delegateSlot = slots.firstWhere(
      (s) => (s as Map)['page'] == 'delegates',
      orElse: () => null,
    );
    final itemsAfter = delegateSlot != null
        ? TypeHelper.toInt((delegateSlot as Map)['items_after'])
        : 2;

    return DelegateAdModel(
      id: TypeHelper.toInt(json['id']),
      title: json['title'] as String? ?? '',
      images: (json['images'] as List? ?? [])
          .map((e) => DelegateAdImageModel.fromJson(Map<String, dynamic>.from(e)))
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
