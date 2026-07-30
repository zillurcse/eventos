import '../../utils/helpers/type_helper.dart';

class ExhibitorAdImageModel {
  final bool isActive;
  final String imageUrl;
  final String redirectType;

  const ExhibitorAdImageModel({
    this.isActive = true,
    this.imageUrl = '',
    this.redirectType = 'no-url',
  });

  factory ExhibitorAdImageModel.fromJson(Map<String, dynamic> json) {
    return ExhibitorAdImageModel(
      isActive: TypeHelper.toBool(json['is_active']),
      imageUrl: json['image_url'] as String? ?? '',
      redirectType: json['redirect_type'] as String? ?? 'no-url',
    );
  }
}
