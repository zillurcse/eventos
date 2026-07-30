import '../../utils/helpers/type_helper.dart';
import 'social_links_model.dart';
import 'banner_model.dart';

class ReceptionEventModel {
  final int id;
  final String title;
  final String description;
  final String logo;
  final String logoUrl;
  final String timezone;
  final String startDate;
  final String endDate;
  final String formatedDate;
  final String formatedTime;
  final bool isOnline;
  final String address1;
  final String address2;
  final String city;
  final String state;
  final String country;
  final String eventSector;
  final String featureVideo;
  final String websiteUrl;
  final String embedUrl;
  final SocialLinksModel socialLinks;
  final List<BannerModel> communityBanners;

  const ReceptionEventModel({
    this.id = 0,
    this.title = '',
    this.description = '',
    this.logo = '',
    this.logoUrl = '',
    this.timezone = '',
    this.startDate = '',
    this.endDate = '',
    this.formatedDate = '',
    this.formatedTime = '',
    this.isOnline = false,
    this.address1 = '',
    this.address2 = '',
    this.city = '',
    this.state = '',
    this.country = '',
    this.eventSector = '',
    this.featureVideo = '',
    this.websiteUrl = '',
    this.embedUrl = '',
    this.socialLinks = const SocialLinksModel(),
    this.communityBanners = const [],
  });

  factory ReceptionEventModel.fromJson(Map<String, dynamic> json) {
    return ReceptionEventModel(
      id: TypeHelper.toInt(json['id']),
      title: json['title'] as String? ?? '',
      description: json['description'] as String? ?? '',
      logo: json['logo'] as String? ?? '',
      logoUrl: json['logo_url'] as String? ?? '',
      timezone: json['timezone'] as String? ?? '',
      startDate: json['start_date'] as String? ?? '',
      endDate: json['end_date'] as String? ?? '',
      formatedDate: json['formated_date'] as String? ?? '',
      formatedTime: json['formated_time'] as String? ?? '',
      isOnline: TypeHelper.toBool(json['is_online']),
      address1: json['address_1'] as String? ?? '',
      address2: json['address_2'] as String? ?? '',
      city: json['city'] as String? ?? '',
      state: json['state'] as String? ?? '',
      country: json['country'] as String? ?? '',
      eventSector: json['event_sector'] as String? ?? '',
      featureVideo: json['feature_video'] as String? ?? '',
      websiteUrl: json['website_url'] as String? ?? '',
      embedUrl: json['embedUrl'] as String? ?? '',
      socialLinks: json['social_links'] is Map
          ? SocialLinksModel.fromJson(Map<String, dynamic>.from(json['social_links']))
          : const SocialLinksModel(),
      communityBanners: (json['community_banners'] as List? ?? [])
          .map((e) => BannerModel.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
    );
  }
}
