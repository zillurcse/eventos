class FeedUserModel {
  final int id;
  final String name;
  final String? profilePhotoPath;
  final String profilePhotoUrl;

  const FeedUserModel({
    required this.id,
    required this.name,
    this.profilePhotoPath,
    required this.profilePhotoUrl,
  });

  factory FeedUserModel.fromJson(Map<String, dynamic> json) => FeedUserModel(
        id: json['id'] as int? ?? 0,
        name: json['name'] as String? ?? '',
        profilePhotoPath: json['profile_photo_path'] as String?,
        profilePhotoUrl: json['profile_photo_url'] as String? ?? '',
      );
}
