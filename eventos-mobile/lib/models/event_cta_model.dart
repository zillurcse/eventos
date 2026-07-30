import '../../utils/helpers/type_helper.dart';

class EventCtaModel {
  final int id;
  final String ctaName;
  final String ctaType; // image | text | video
  final String ctaVideoLink;
  final String ctaImageUrl;
  final String ctaDescription;
  final String buttonLabel;
  final String buttonLink;

  const EventCtaModel({
    this.id = 0,
    this.ctaName = '',
    this.ctaType = '',
    this.ctaVideoLink = '',
    this.ctaImageUrl = '',
    this.ctaDescription = '',
    this.buttonLabel = '',
    this.buttonLink = '',
  });

  factory EventCtaModel.fromJson(Map<String, dynamic> json) {
    final rawImage = json['cta_image'] as String? ?? '';
    final imageUrl = rawImage.isNotEmpty
        ? 'https://admin.expouse.com/storage/$rawImage'
        : '';

    return EventCtaModel(
      id: TypeHelper.toInt(json['id']),
      ctaName: json['cta_name'] as String? ?? '',
      ctaType: json['cta_type'] as String? ?? '',
      ctaVideoLink: json['cta_video_link'] as String? ?? '',
      ctaImageUrl: imageUrl,
      ctaDescription: json['cta_description'] as String? ?? '',
      buttonLabel: json['button_label'] as String? ?? '',
      buttonLink: json['button_link'] as String? ?? '',
    );
  }
}
