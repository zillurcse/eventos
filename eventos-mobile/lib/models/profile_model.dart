import '../utils/helpers/type_helper.dart';

class ProfileData {
  final String? firstName;
  final String? lastName;
  final String? gender;
  final String? email;
  final String? password;
  final String? about;
  final String? designation;
  final String? organisation;
  final String? country;
  final String? state;
  final String? cityTown;
  final String? website;
  final String? sectors;
  final String? involvementInIndustry;
  final String? purposeOfVisit;
  final String? purchasingDecision;
  final String? dropdown;

  const ProfileData({
    this.firstName,
    this.lastName,
    this.gender,
    this.email,
    this.password,
    this.about,
    this.designation,
    this.organisation,
    this.country,
    this.state,
    this.cityTown,
    this.website,
    this.sectors,
    this.involvementInIndustry,
    this.purposeOfVisit,
    this.purchasingDecision,
    this.dropdown,
  });

  factory ProfileData.fromJson(Map<String, dynamic> json) {
    return ProfileData(
      firstName: json['First name'] as String?,
      lastName: json['Last name'] as String?,
      gender: json['Gender'] as String?,
      email: json['email'] as String?,
      password: json['password'] as String?,
      about: json['About'] as String?,
      designation: json['Designation'] as String?,
      organisation: json['Organisation'] as String?,
      country: json['Country'] as String?,
      state: json['State'] as String?,
      cityTown: json['City/Town'] as String?,
      website: json['Website'] as String?,
      sectors: json['Sectors'] as String?,
      involvementInIndustry: json['Involvement in the Industry'] as String?,
      purposeOfVisit: json['Purpose of visit'] as String?,
      purchasingDecision: json['Purchasing decision'] as String?,
      dropdown: json['Dropdown'] as String?,
    );
  }
}

class ProfileModel {
  final int id;
  final String? firstName;
  final String? lastName;
  final String? name;
  final String? email;
  final String? designation;
  final String? company;
  final String? about;
  final String? mobileNumber;
  final String? address;
  final String? country;
  final String? state;
  final String? cityTown;
  final String? industry;
  final String? interests;
  final String? gender;
  final String? website;
  final String? timezone;
  final String? profilePhotoUrl;
  final String? role;
  final bool isOnboarded;
  final ProfileData? profileData;

  const ProfileModel({
    this.id = 0,
    this.firstName,
    this.lastName,
    this.name,
    this.email,
    this.designation,
    this.company,
    this.about,
    this.mobileNumber,
    this.address,
    this.country,
    this.state,
    this.cityTown,
    this.industry,
    this.interests,
    this.gender,
    this.website,
    this.timezone,
    this.profilePhotoUrl,
    this.role,
    this.isOnboarded = false,
    this.profileData,
  });

  factory ProfileModel.fromJson(Map<String, dynamic> json) {
    return ProfileModel(
      id: TypeHelper.toInt(json['id']),
      firstName: json['first_name'] as String?,
      lastName: json['last_name'] as String?,
      name: json['name'] as String?,
      email: json['email'] as String?,
      designation: json['designation'] as String?,
      company: json['company'] as String?,
      about: json['about'] as String?,
      mobileNumber: json['mobile_number'] as String?,
      address: json['address'] as String?,
      country: json['country'] as String?,
      state: json['state'] as String?,
      cityTown: json['city_town'] as String?,
      industry: json['industry'] as String?,
      interests: json['interests'] as String?,
      gender: json['gender'] as String?,
      website: json['website'] as String?,
      timezone: json['timezone'] as String?,
      profilePhotoUrl: json['profile_photo_url'] as String?,
      role: json['role'] as String?,
      isOnboarded: TypeHelper.toBool(json['is_onborded']),
      profileData: json['profile_data'] != null
          ? ProfileData.fromJson(Map<String, dynamic>.from(json['profile_data']))
          : null,
    );
  }
}
