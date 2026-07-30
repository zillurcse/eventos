import '../../utils/helpers/type_helper.dart';

class InternalContact {
  final String iso2;
  final String email;
  final String position;
  final String fullName;
  final String companyName;
  final String countryCode;
  final String mobileNumber;

  const InternalContact({
    this.iso2 = '',
    this.email = '',
    this.position = '',
    this.fullName = '',
    this.companyName = '',
    this.countryCode = '',
    this.mobileNumber = '',
  });

  factory InternalContact.fromJson(Map<String, dynamic> json) {
    return InternalContact(
      iso2: json['iso2'] as String? ?? '',
      email: json['email'] as String? ?? '',
      position: json['position'] as String? ?? '',
      fullName: json['full_name'] as String? ?? '',
      companyName: json['company_name'] as String? ?? '',
      countryCode: json['country_code']?.toString() ?? '',
      mobileNumber: json['mobile_number']?.toString() ?? '',
    );
  }
}

class PackageInfo {
  final String name;
  final String slug;
  final int count;
  final bool selected;
  final bool isDefault;
  final bool hasCounter;

  const PackageInfo({
    this.name = '',
    this.slug = '',
    this.count = 0,
    this.selected = false,
    this.isDefault = false,
    this.hasCounter = false,
  });

  factory PackageInfo.fromJson(Map<String, dynamic> json) {
    return PackageInfo(
      name: json['name'] as String? ?? '',
      slug: json['slug'] as String? ?? '',
      count: TypeHelper.toInt(json['count']),
      selected: TypeHelper.toBool(json['selected']),
      isDefault: TypeHelper.toBool(json['isDefault']),
      hasCounter: TypeHelper.toBool(json['hasCounter']),
    );
  }
}

class Review {
  final int id;
  final String? review;
  final int rating;
  final int exhibitorId;
  final int eventId;
  final int userId;
  final String? createdAt;
  final String? updatedAt;

  const Review({
    this.id = 0,
    this.review,
    this.rating = 0,
    this.exhibitorId = 0,
    this.eventId = 0,
    this.userId = 0,
    this.createdAt,
    this.updatedAt,
  });

  factory Review.fromJson(Map<String, dynamic> json) {
    return Review(
      id: TypeHelper.toInt(json['id']),
      review: json['review'] as String?,
      rating: TypeHelper.toInt(json['rating']),
      exhibitorId: TypeHelper.toInt(json['exhibitor_id']),
      eventId: TypeHelper.toInt(json['event_id']),
      userId: TypeHelper.toInt(json['user_id']),
      createdAt: json['created_at'] as String?,
      updatedAt: json['updated_at'] as String?,
    );
  }
}

class ExhibitorModel {
  final int id;
  final int userId;
  final int exhibitorPackageId;
  final String name;
  final String? dataSource;
  final String? image;
  final String? email;
  final String? accessCode;
  final String? address;
  final String? location;
  final List<dynamic> ctas;
  final String? mobileNumber;
  final String? mobileCountryCode;
  final String? mobileCountryIso2;
  final String? landline;
  final String? brochure;
  final String? facebook;
  final String? youtube;
  final String? linkedin;
  final String? twitter;
  final String? instagram;
  final String? whatsapp;
  final String? website;
  final String? streetAddress;
  final String? city;
  final String? state;
  final String? zipCode;
  final int? countryId;
  final String? description;
  final List<dynamic> tags;
  final List<dynamic> peoples;
  final String? spotlightBannerImage;
  final String? spotlightBannerVideoYoutube;
  final String? spotlightBannerVideoVimeo;
  final String? spotlightBannerVideoYouku;
  final String? customCta;
  final String? customCtaDescription;
  final String? buttonLabel;
  final String? buttonUrl;
  final int? priorityNumber;
  final String? boothSize;
  final List<dynamic> boothAgenda;
  final int allowRating;
  final bool isFeatured;
  final bool isPremium;
  final int eventId;
  final int? categoryId;
  final String? createdAt;
  final String? updatedAt;
  final String? displayPosition;
  final int descriptionReadMore;
  final String? productTitle;
  final String? indexPageBanner;
  final int order;
  final bool isPrivate;
  final String? deletedAt;
  final bool isActive;
  final int loungeTable;
  final int loungeNumberOfTable;
  final int loungeMeetingLimit;
  final int loungeTableCapacity;
  final String? loungeTableName;
  final String? loungeTableTopic;
  final String? loungeTableLogo;
  final String? roomId;
  final String? roomLink;
  final String? roomName;
  final int roomOrder;
  final String stallNo;
  final String? venue;
  final List<dynamic> videosData;
  final String? spotlightType;
  final int status;
  final String? aboutTitle;
  final InternalContact? internalContact;
  final String? videoTitle;
  final String? representativesTitle;
  final String? documentsTitle;
  final bool isShareDetails;
  final String slug;
  final int? userGroupId;
  final int? addressCountryId;
  final String exhibitorType;
  final List<PackageInfo> packageInfo;
  final String logoUrl;
  final String spotlightBannerUrl;
  final dynamic categoryData;
  final List<dynamic> documents;
  final dynamic category;
  final List<dynamic> products;
  final List<dynamic> videos;
  final List<dynamic> representative;
  final Review? review;
  final bool isExhibitorAdmin;
  final bool isBookmarked;
  final List<dynamic> exhibitorMembers;
  final List<dynamic> exhibitorRepresentatives;
  final List<dynamic> projects;

  const ExhibitorModel({
    this.id = 0,
    this.userId = 0,
    this.exhibitorPackageId = 0,
    this.name = '',
    this.dataSource,
    this.image,
    this.email,
    this.accessCode,
    this.address,
    this.location,
    this.ctas = const [],
    this.mobileNumber,
    this.mobileCountryCode,
    this.mobileCountryIso2,
    this.landline,
    this.brochure,
    this.facebook,
    this.youtube,
    this.linkedin,
    this.twitter,
    this.instagram,
    this.whatsapp,
    this.website,
    this.streetAddress,
    this.city,
    this.state,
    this.zipCode,
    this.countryId,
    this.description,
    this.tags = const [],
    this.peoples = const [],
    this.spotlightBannerImage,
    this.spotlightBannerVideoYoutube,
    this.spotlightBannerVideoVimeo,
    this.spotlightBannerVideoYouku,
    this.customCta,
    this.customCtaDescription,
    this.buttonLabel,
    this.buttonUrl,
    this.priorityNumber,
    this.boothSize,
    this.boothAgenda = const [],
    this.allowRating = 0,
    this.isFeatured = false,
    this.isPremium = false,
    this.eventId = 0,
    this.categoryId,
    this.createdAt,
    this.updatedAt,
    this.displayPosition,
    this.descriptionReadMore = 0,
    this.productTitle,
    this.indexPageBanner,
    this.order = 0,
    this.isPrivate = false,
    this.deletedAt,
    this.isActive = false,
    this.loungeTable = 0,
    this.loungeNumberOfTable = 0,
    this.loungeMeetingLimit = 0,
    this.loungeTableCapacity = 0,
    this.loungeTableName,
    this.loungeTableTopic,
    this.loungeTableLogo,
    this.roomId,
    this.roomLink,
    this.roomName,
    this.roomOrder = 0,
    this.stallNo = '',
    this.venue,
    this.videosData = const [],
    this.spotlightType,
    this.status = 0,
    this.aboutTitle,
    this.internalContact,
    this.videoTitle,
    this.representativesTitle,
    this.documentsTitle,
    this.isShareDetails = false,
    this.slug = '',
    this.userGroupId,
    this.addressCountryId,
    this.exhibitorType = '',
    this.packageInfo = const [],
    this.logoUrl = '',
    this.spotlightBannerUrl = '',
    this.categoryData,
    this.documents = const [],
    this.category,
    this.products = const [],
    this.videos = const [],
    this.representative = const [],
    this.review,
    this.isExhibitorAdmin = false,
    this.isBookmarked = false,
    this.exhibitorMembers = const [],
    this.exhibitorRepresentatives = const [],
    this.projects = const [],
  });

  factory ExhibitorModel.fromJson(Map<String, dynamic> json) {
    return ExhibitorModel(
      id: TypeHelper.toInt(json['id']),
      userId: TypeHelper.toInt(json['user_id']),
      exhibitorPackageId: TypeHelper.toInt(json['exhibitor_package_id']),
      name: json['name'] as String? ?? '',
      dataSource: json['data_source'] as String?,
      image: json['image'] as String?,
      email: json['email'] as String?,
      accessCode: json['access_code'] as String?,
      address: json['address'] as String?,
      location: json['location'] as String?,
      ctas: TypeHelper.toList(json['ctas']),
      mobileNumber: json['mobile_number']?.toString(),
      mobileCountryCode: json['mobile_country_code']?.toString(),
      mobileCountryIso2: json['mobile_country_iso2'] as String?,
      landline: json['landline']?.toString(),
      brochure: json['brochure'] as String?,
      facebook: json['facebook'] as String?,
      youtube: json['youtube'] as String?,
      linkedin: json['linkedin'] as String?,
      twitter: json['twitter'] as String?,
      instagram: json['instagram'] as String?,
      whatsapp: json['whatsapp'] as String?,
      website: json['website'] as String?,
      streetAddress: json['street_address'] as String?,
      city: json['city'] as String?,
      state: json['state'] as String?,
      zipCode: json['zip_code']?.toString(),
      countryId: json['country_id'] != null ? TypeHelper.toInt(json['country_id']) : null,
      description: json['description'] as String?,
      tags: TypeHelper.toList(json['tags']),
      peoples: TypeHelper.toList(json['peoples']),
      spotlightBannerImage: json['spotlight_banner_image'] as String?,
      spotlightBannerVideoYoutube: json['spotlight_banner_video_youtube'] as String?,
      spotlightBannerVideoVimeo: json['spotlight_banner_video_vimeo'] as String?,
      spotlightBannerVideoYouku: json['spotlight_banner_video_youku'] as String?,
      customCta: json['custom_cta'] as String?,
      customCtaDescription: json['custom_cta_description'] as String?,
      buttonLabel: json['button_label'] as String?,
      buttonUrl: json['button_url'] as String?,
      priorityNumber: json['priority_number'] != null ? TypeHelper.toInt(json['priority_number']) : null,
      boothSize: json['booth_size'] as String?,
      boothAgenda: TypeHelper.toList(json['booth_agenda']),
      allowRating: TypeHelper.toInt(json['allow_reating']),
      isFeatured: TypeHelper.toBool(json['is_featured']),
      isPremium: TypeHelper.toBool(json['is_premium']),
      eventId: TypeHelper.toInt(json['event_id']),
      categoryId: json['category_id'] != null ? TypeHelper.toInt(json['category_id']) : null,
      createdAt: json['created_at'] as String?,
      updatedAt: json['updated_at'] as String?,
      displayPosition: json['display_position'] as String?,
      descriptionReadMore: TypeHelper.toInt(json['description_read_more']),
      productTitle: json['product_title'] as String?,
      indexPageBanner: json['index_page_banner'] as String?,
      order: TypeHelper.toInt(json['order']),
      isPrivate: TypeHelper.toBool(json['is_private']),
      deletedAt: json['deleted_at'] as String?,
      isActive: TypeHelper.toBool(json['is_active']),
      loungeTable: TypeHelper.toInt(json['lounge_table']),
      loungeNumberOfTable: TypeHelper.toInt(json['lounge_number_of_table']),
      loungeMeetingLimit: TypeHelper.toInt(json['lounge_meeting_limit']),
      loungeTableCapacity: TypeHelper.toInt(json['lounge_table_capacity']),
      loungeTableName: json['lounge_table_name'] as String?,
      loungeTableTopic: json['lounge_table_topic'] as String?,
      loungeTableLogo: json['lounge_table_logo'] as String?,
      roomId: json['room_id'] as String?,
      roomLink: json['room_link'] as String?,
      roomName: json['room_name'] as String?,
      roomOrder: TypeHelper.toInt(json['room_order']),
      stallNo: json['stall_no']?.toString() ?? '',
      venue: json['venue'] as String?,
      videosData: TypeHelper.toList(json['videos_data']),
      spotlightType: json['spotlight_type'] as String?,
      status: TypeHelper.toInt(json['status']),
      aboutTitle: json['about_title'] as String?,
      internalContact: json['internal_contact'] is Map
          ? InternalContact.fromJson(Map<String, dynamic>.from(json['internal_contact']))
          : null,
      videoTitle: json['video_title'] as String?,
      representativesTitle: json['representatives_title'] as String?,
      documentsTitle: json['documents_title'] as String?,
      isShareDetails: TypeHelper.toBool(json['is_share_details']),
      slug: json['slug'] as String? ?? '',
      userGroupId: json['user_group_id'] != null ? TypeHelper.toInt(json['user_group_id']) : null,
      addressCountryId: json['address_country_id'] != null ? TypeHelper.toInt(json['address_country_id']) : null,
      exhibitorType: json['exhibitor_type'] as String? ?? '',
      packageInfo: TypeHelper.toList(json['package_info'])
          .map((e) => PackageInfo.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
      logoUrl: json['logo_url'] as String? ?? '',
      spotlightBannerUrl: json['spotlight_banner_url'] as String? ?? '',
      categoryData: json['category_data'],
      documents: TypeHelper.toList(json['documents']),
      category: json['category'],
      products: TypeHelper.toList(json['products']),
      videos: TypeHelper.toList(json['videos']),
      representative: TypeHelper.toList(json['representative']),
      review: json['review'] is Map ? Review.fromJson(Map<String, dynamic>.from(json['review'])) : null,
      isExhibitorAdmin: TypeHelper.toBool(json['is_exhibitor_admin']),
      isBookmarked: TypeHelper.toBool(json['is_bookmarked']),
      exhibitorMembers: TypeHelper.toList(json['exhibitor_members']),
      exhibitorRepresentatives: TypeHelper.toList(json['exhibitor_representatives']),
      projects: TypeHelper.toList(json['projects']),
    );
  }
}
