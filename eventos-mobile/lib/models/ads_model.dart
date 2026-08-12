import '../../utils/helpers/type_helper.dart';
import 'banner_model.dart';

class AdsModel {
  final int id;

  /// Reception content ads (after featured speakers).
  final List<BannerModel> sidebar;

  /// Horizontal strip ads - reserved for other pages, not reception.
  final List<BannerModel> strip;

  const AdsModel({
    this.id = 0,
    this.sidebar = const [],
    this.strip = const [],
  });

  /// Alias for reception UI that historically read `images`.
  List<BannerModel> get images => sidebar;

  factory AdsModel.fromJson(Map<String, dynamic> json) {
    return AdsModel(
      id: TypeHelper.toInt(json['id']),
      sidebar: (json['sidebar'] as List? ?? json['images'] as List? ?? [])
          .map((e) => BannerModel.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
      strip: (json['strip'] as List? ?? [])
          .map((e) => BannerModel.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
    );
  }
}
