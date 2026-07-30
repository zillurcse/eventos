import 'delegate_ad_model.dart';
import 'delegate_item_model.dart';
import '../../utils/helpers/type_helper.dart';

class DelegatePageModel {
  final int currentPage;
  final List<DelegateItemModel> delegates;
  final String? nextPageUrl;
  final DelegateAdModel? featuredAd;
  final DelegateAdModel? contentAd;

  const DelegatePageModel({
    this.currentPage = 1,
    this.delegates = const [],
    this.nextPageUrl,
    this.featuredAd,
    this.contentAd,
  });

  factory DelegatePageModel.fromJson(Map<String, dynamic> json) {
    return DelegatePageModel(
      currentPage: TypeHelper.toInt(json['current_page']),
      delegates: (json['delegates'] as List? ?? [])
          .map((e) => DelegateItemModel.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
      nextPageUrl: json['next_page_url'] as String?,
      featuredAd: json['featured_ad'] is Map
          ? DelegateAdModel.fromJson(Map<String, dynamic>.from(json['featured_ad']))
          : null,
      contentAd: json['content_ad'] is Map
          ? DelegateAdModel.fromJson(Map<String, dynamic>.from(json['content_ad']))
          : null,
    );
  }
}
