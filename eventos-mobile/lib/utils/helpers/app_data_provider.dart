import 'package:expouse/features/auth/auth_view.dart';
import 'package:get/get_core/src/get_main.dart';
import 'package:get/get_navigation/src/extension_navigation.dart';
import 'package:get_storage/get_storage.dart';

import '../../features/root/root_view.dart';
import 'package:expouse/utils/bindings/auth_binding.dart';
import 'package:expouse/utils/bindings/route_bindings.dart';
import '../config/dio_config.dart';
import 'local_key.dart';

class AppDataProvider {
  AppDataProvider._();
  static AppDataProvider obj = AppDataProvider._();
  factory AppDataProvider() => obj;

  final localDb = GetStorage();

  String _subdomain = "edu";

  String get subdomain => _subdomain;

  set setSubDomain(String value) {
    _subdomain = value;
  }

  Future<void> initialRoute() async {
    DioConfig.obj.init();
    String? token = localDb.read(LocalKeyHelper.token);
    await Future.delayed(Duration(seconds: 2));
    if (token == null) {
      Get.offAll(
        () => AuthView(),
        binding: AuthBinding(),
      );
    } else {
      Get.offAll(
        () => RootView(),
        binding: RootBinding(),
      );
    }
  }
}
