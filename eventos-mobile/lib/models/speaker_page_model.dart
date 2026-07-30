import 'speaker_ad_model.dart';
import 'speaker_item_model.dart';

class SpeakerPageModel {
  final List<SpeakerItemModel> speakers;
  final SpeakerAdModel? featuredAd;
  final SpeakerAdModel? contentAd;

  const SpeakerPageModel({
    this.speakers = const [],
    this.featuredAd,
    this.contentAd,
  });

  factory SpeakerPageModel.fromJson(Map<String, dynamic> json) {
    return SpeakerPageModel(
      speakers: (json['speakers'] as List? ?? [])
          .map((e) => SpeakerItemModel.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
      featuredAd: json['featured_ad'] is Map
          ? SpeakerAdModel.fromJson(Map<String, dynamic>.from(json['featured_ad']))
          : null,
      contentAd: json['content_ad'] is Map
          ? SpeakerAdModel.fromJson(Map<String, dynamic>.from(json['content_ad']))
          : null,
    );
  }
}
