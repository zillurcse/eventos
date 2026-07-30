import '../../utils/helpers/type_helper.dart';

class SessionSponsorModel {
  final int id;
  final String name;
  final String logoUrl;

  const SessionSponsorModel({
    this.id = 0,
    this.name = '',
    this.logoUrl = '',
  });

  factory SessionSponsorModel.fromJson(Map<String, dynamic> json) {
    return SessionSponsorModel(
      id: TypeHelper.toInt(json['id']),
      name: json['name'] as String? ?? '',
      logoUrl: json['logo_url'] as String? ?? '',
    );
  }
}
