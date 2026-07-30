class SocialLinksModel {
  final String hashtagLink;
  final String facebookLink;
  final String twitterLink;
  final String linkedinLink;
  final String youtubeLink;
  final String instagramLink;

  const SocialLinksModel({
    this.hashtagLink = '',
    this.facebookLink = '',
    this.twitterLink = '',
    this.linkedinLink = '',
    this.youtubeLink = '',
    this.instagramLink = '',
  });

  factory SocialLinksModel.fromJson(Map<String, dynamic> json) {
    return SocialLinksModel(
      hashtagLink: json['hashtag_link'] as String? ?? '',
      facebookLink: json['fadebook_link'] as String? ?? '',
      twitterLink: json['twitter_link'] as String? ?? '',
      linkedinLink: json['linkedin_link'] as String? ?? '',
      youtubeLink: json['youtube_link'] as String? ?? '',
      instagramLink: json['instagram_link'] as String? ?? '',
    );
  }
}
