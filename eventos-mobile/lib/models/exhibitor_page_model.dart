import 'exhibitor_ad_model.dart';
import 'exhibitor_model.dart';

class ExhibitorPageModel {
  final List<ExhibitorModel> exhibitors;
  final List<int> bookmarkedExhibitors;
  final ExhibitorAdModel? featuredAd;
  final ExhibitorAdModel? contentAd;

  const ExhibitorPageModel({
    this.exhibitors = const [],
    this.bookmarkedExhibitors = const [],
    this.featuredAd,
    this.contentAd,
  });

  factory ExhibitorPageModel.fromJson(Map<String, dynamic> json) {
    return ExhibitorPageModel(
      exhibitors: (json['exhibitors'] as List? ?? [])
          .map((e) => ExhibitorModel.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
      bookmarkedExhibitors: (json['bookmarked_exhibitors'] as List? ?? [])
          .map((e) => int.tryParse(e.toString()) ?? 0)
          .toList(),
      featuredAd: json['featured_ad'] is Map
          ? ExhibitorAdModel.fromJson(Map<String, dynamic>.from(json['featured_ad']))
          : null,
      contentAd: json['content_ad'] is Map
          ? ExhibitorAdModel.fromJson(Map<String, dynamic>.from(json['content_ad']))
          : null,
    );
  }
}
