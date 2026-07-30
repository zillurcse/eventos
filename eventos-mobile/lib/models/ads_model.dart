import '../../utils/helpers/type_helper.dart';
import 'banner_model.dart';

class AdsModel {
  final int id;
  final List<BannerModel> images;

  const AdsModel({
    this.id = 0,
    this.images = const [],
  });

  factory AdsModel.fromJson(Map<String, dynamic> json) {
    return AdsModel(
      id: TypeHelper.toInt(json['id']),
      images: (json['images'] as List? ?? [])
          .map((e) => BannerModel.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
    );
  }
}
