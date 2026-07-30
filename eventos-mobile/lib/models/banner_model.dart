import '../../utils/helpers/type_helper.dart';

class BannerModel {
  final int id;
  final String title;
  final String url;
  final String imageUrl;
  final bool status;

  const BannerModel({
    this.id = 0,
    this.title = '',
    this.url = '',
    this.imageUrl = '',
    this.status = true,
  });

  factory BannerModel.fromJson(Map<String, dynamic> json) {
    return BannerModel(
      id: TypeHelper.toInt(json['id']),
      title: json['title'] as String? ?? '',
      url: json['url'] as String? ?? '',
      imageUrl: json['image_url'] as String? ?? '',
      status: TypeHelper.toBool(json['status']),
    );
  }
}
