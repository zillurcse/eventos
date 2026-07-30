import 'package:get/get.dart';
import '../../features/auth/auth_controller.dart';

class AuthBinding implements Bindings {
  AuthBinding();

  @override
  void dependencies() {
    Get.lazyPut<AuthController>(() => AuthController(), fenix: true);
  }
}
