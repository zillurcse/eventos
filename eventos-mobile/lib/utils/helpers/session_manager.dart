import 'package:flutter/foundation.dart';
import 'package:get/get.dart';
import 'package:get_storage/get_storage.dart';

import '../../features/auth/auth_view.dart';
import '../../features/notifications/push_notification_service.dart';
import '../../utils/bindings/auth_binding.dart';
import '../../utils/config/chat_config.dart';
import 'local_key.dart';
import 'secure_auth_storage.dart';

/// Centralized session teardown (logout / 401).
///
/// Clears secure token, local prefs, FCM registration, and chat sockets,
/// then routes to [AuthView] without stacking duplicate navigations.
class SessionManager {
  SessionManager._();

  static bool _loggingOut = false;

  /// Clears all auth-related state and returns to the login screen.
  static Future<void> logout() async {
    if (_loggingOut) return;
    _loggingOut = true;
    try {
      try {
        await PushNotificationService.instance.unregisterOnLogout();
      } catch (e) {
        debugPrint('SessionManager: FCM unregister skipped: $e');
      }

      try {
        await ChatConfig.disconnect();
      } catch (_) {}

      await SecureAuthStorage.instance.clearToken();

      final box = GetStorage();
      await box.remove(LocalKeyHelper.userInfo);
      await box.remove(LocalKeyHelper.eventUuid);
      await box.remove(LocalKeyHelper.eventSubdomain);
      await box.remove(LocalKeyHelper.fcmToken);
      await box.remove(LocalKeyHelper.token);

      if (Get.key.currentState != null) {
        Get.offAll(
          () => const AuthView(),
          binding: AuthBinding(),
        );
      }
    } finally {
      _loggingOut = false;
    }
  }
}
