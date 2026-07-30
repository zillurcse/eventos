import 'package:expouse/features/auth/auth_view.dart';
import 'package:get/get_core/src/get_main.dart';
import 'package:get/get_navigation/src/extension_navigation.dart';
import 'package:get_storage/get_storage.dart';

import '../../features/root/root_view.dart';
import 'package:expouse/utils/bindings/auth_binding.dart';
import 'package:expouse/utils/bindings/route_bindings.dart';
import '../config/app_config.dart';
import '../config/dio_config.dart';
import 'local_key.dart';

class AppDataProvider {
  AppDataProvider._();
  static AppDataProvider obj = AppDataProvider._();
  factory AppDataProvider() => obj;

  final localDb = GetStorage();

  late String _subdomain = _resolveInitialSubdomain();

  static String _resolveInitialSubdomain() {
    final stored =
        GetStorage().read(LocalKeyHelper.eventSubdomain) as String?;
    // Legacy Expouse default + unseeded local demo subdomain.
    if (stored == null ||
        stored.isEmpty ||
        stored == 'edu' ||
        stored == 'demo') {
      return AppConfig.defaultEventSubdomain;
    }
    return stored;
  }

  String get subdomain => _subdomain;

  set setSubDomain(String value) {
    _subdomain = value;
    localDb.write(LocalKeyHelper.eventSubdomain, value);
    DioConfig.obj.refreshEventHeaders();
  }

  String? get eventUuid => localDb.read(LocalKeyHelper.eventUuid) as String?;

  set eventUuid(String? value) {
    if (value == null || value.isEmpty) {
      localDb.remove(LocalKeyHelper.eventUuid);
    } else {
      localDb.write(LocalKeyHelper.eventUuid, value);
    }
  }

  Future<void> initialRoute() async {
    DioConfig.obj.init();
    final token = localDb.read(LocalKeyHelper.token);
    await Future.delayed(const Duration(seconds: 2));
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
