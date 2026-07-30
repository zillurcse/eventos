import '../../utils/helpers/type_helper.dart';

class SpeakerAdImageModel {
  final String? title;
  final bool isActive;
  final String? redirectUrl;
  final String redirectType;
  final String imageUrl;

  const SpeakerAdImageModel({
    this.title,
    this.isActive = true,
    this.redirectUrl,
    this.redirectType = 'no-url',
    this.imageUrl = '',
  });

  factory SpeakerAdImageModel.fromJson(Map<String, dynamic> json) {
    return SpeakerAdImageModel(
      title: json['title'] as String?,
      isActive: TypeHelper.toBool(json['is_active']),
      redirectUrl: json['redirect_url'] as String?,
      redirectType: json['redirect_type'] as String? ?? 'no-url',
      imageUrl: json['image_url'] as String? ?? '',
    );
  }
}
