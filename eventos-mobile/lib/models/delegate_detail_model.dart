import '../../utils/helpers/type_helper.dart';

class DelegateDetailModel {
  final int id;
  final String name;
  final String? firstName;
  final String? lastName;
  final String image;
  final String designation;
  final String company;
  final String country;
  final String? about;
  final String? industry;
  final String? interests;
  final String? gender;
  final String? state;
  final String? cityTown;
  final String? address;
  final String? website;
  final String? email;
  final String? mobileNumber;
  final String? timezone;
  final bool isFavorite;

  const DelegateDetailModel({
    this.id = 0,
    this.name = '',
    this.firstName,
    this.lastName,
    this.image = '',
    this.designation = '',
    this.company = '',
    this.country = '',
    this.about,
    this.industry,
    this.interests,
    this.gender,
    this.state,
    this.cityTown,
    this.address,
    this.website,
    this.email,
    this.mobileNumber,
    this.timezone,
    this.isFavorite = false,
  });

  factory DelegateDetailModel.fromJson(Map<String, dynamic> json) {
    return DelegateDetailModel(
      id: TypeHelper.toInt(json['id']),
      name: json['name'] as String? ?? '',
      firstName: json['first_name'] as String?,
      lastName: json['last_name'] as String?,
      image: json['image'] as String? ?? '',
      designation: json['designation'] as String? ?? '',
      company: json['company'] as String? ?? '',
      country: json['country'] as String? ?? '',
      about: json['about'] as String?,
      industry: json['industry'] as String?,
      interests: json['interests'] as String?,
      gender: json['gender'] as String?,
      state: json['state'] as String?,
      cityTown: json['city_town'] as String?,
      address: json['address'] as String?,
      website: json['website'] as String?,
      email: json['email'] as String?,
      mobileNumber: json['mobile_number'] as String?,
      timezone: json['timezone'] as String?,
      isFavorite: TypeHelper.toBool(json['is_favorite']),
    );
  }
}
