
import 'package:expouse/features/event_feed/create_post_controller.dart';
import 'package:expouse/features/event_feed/event_feed_controller.dart';
import 'package:get/get_core/src/get_main.dart';
import 'package:get/get_instance/src/bindings_interface.dart';
import 'package:get/get_instance/src/extension_instance.dart';


import '../../features/chat/chat_controller.dart';
import '../../features/delegate/delegate_controller.dart';
import '../../features/home/home_controller.dart';
import '../../features/root/root_controller.dart';
import '../../features/speaker/speaker_controller.dart';
import '../../features/exhibitors/exhibitor_controller.dart';
import '../../features/session/session_controller.dart';
import '../../features/briefcase/briefcase_controller.dart';
import '../../features/leaderboard/leaderboard_controller.dart';

class RootBinding implements Bindings {
  RootBinding();

  @override
  void dependencies() {
    Get.lazyPut<HomeController>(() => HomeController(), fenix: true);
    Get.lazyPut<ChatController>(() => ChatController(), fenix: true);
    Get.lazyPut<EventFeedController>(() => EventFeedController(), fenix: true);
    Get.lazyPut<CreatePostController>(() => CreatePostController(), fenix: true);
    Get.lazyPut<SpeakerController>(() => SpeakerController(), fenix: true);
    Get.lazyPut<DelegateController>(() => DelegateController(), fenix: true);
    Get.lazyPut<ExhibitorController>(() => ExhibitorController(), fenix: true);
    Get.lazyPut<SessionController>(() => SessionController(), fenix: true);
    Get.lazyPut<BriefcaseController>(() => BriefcaseController(), fenix: true);
    Get.lazyPut<LeaderboardController>(() => LeaderboardController(), fenix: true);
  }
}


