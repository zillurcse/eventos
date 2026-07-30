import '../../utils/helpers/type_helper.dart';

class DelegateItemModel {
  final int id;
  final String name;
  final String image;
  final String designation;
  final String company;
  final String country;
  final bool isFavorite;

  const DelegateItemModel({
    this.id = 0,
    this.name = '',
    this.image = '',
    this.designation = '',
    this.company = '',
    this.country = '',
    this.isFavorite = false,
  });

  factory DelegateItemModel.fromJson(Map<String, dynamic> json) {
    return DelegateItemModel(
      id: TypeHelper.toInt(json['id']),
      name: json['name'] as String? ?? '',
      image: json['image'] as String? ?? '',
      designation: json['designation'] as String? ?? '',
      company: json['company'] as String? ?? '',
      country: json['country'] as String? ?? '',
      isFavorite: TypeHelper.toBool(json['is_favorite']),
    );
  }
}
