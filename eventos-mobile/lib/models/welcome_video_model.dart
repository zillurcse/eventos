import '../../utils/helpers/type_helper.dart';

class WelcomeVideoModel {
  final String videoType;
  final String videoUrl;
  final bool showOnHomeScreen;

  const WelcomeVideoModel({
    this.videoType = '',
    this.videoUrl = '',
    this.showOnHomeScreen = false,
  });

  factory WelcomeVideoModel.fromJson(Map<String, dynamic> json) {
    return WelcomeVideoModel(
      videoType: json['video_type'] as String? ?? '',
      videoUrl: json['video_url'] as String? ?? '',
      showOnHomeScreen: TypeHelper.toBool(json['welcome_video_show_on_home_screen']),
    );
  }
}
