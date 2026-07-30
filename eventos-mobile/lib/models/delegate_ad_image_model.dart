import '../../utils/helpers/type_helper.dart';

class DelegateAdImageModel {
  final bool isActive;
  final String imageUrl;
  final String redirectType;

  const DelegateAdImageModel({
    this.isActive = true,
    this.imageUrl = '',
    this.redirectType = 'no-url',
  });

  factory DelegateAdImageModel.fromJson(Map<String, dynamic> json) {
    return DelegateAdImageModel(
      isActive: TypeHelper.toBool(json['is_active']),
      imageUrl: json['image_url'] as String? ?? '',
      redirectType: json['redirect_type'] as String? ?? 'no-url',
    );
  }
}
