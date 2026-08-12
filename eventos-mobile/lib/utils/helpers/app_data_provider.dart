import 'package:dio/dio.dart';
import 'package:expouse/features/auth/auth_view.dart';
import 'package:expouse/features/root/mobile_branding_service.dart';
import 'package:expouse/models/mobile_branding_model.dart';
import 'package:expouse/utils/theme/app_colors.dart';
import 'package:flutter/foundation.dart';
import 'package:get/get_core/src/get_main.dart';
import 'package:get/get_navigation/src/extension_navigation.dart';
import 'package:get_storage/get_storage.dart';

import '../../features/auth/auth_service.dart';
import '../../features/root/root_view.dart';
import 'package:expouse/utils/bindings/auth_binding.dart';
import 'package:expouse/utils/bindings/route_bindings.dart';
import '../config/app_config.dart';
import '../config/dio_config.dart';
import 'local_key.dart';
import 'secure_auth_storage.dart';

class AppDataProvider {
  AppDataProvider._();
  static AppDataProvider obj = AppDataProvider._();
  factory AppDataProvider() => obj;

  final localDb = GetStorage();
  MobileBrandingService get _brandingService => MobileBrandingService();

  late String _subdomain = _resolveInitialSubdomain();

  /// Active mobile brand (platform default or organizer override).
  MobileBranding? mobileBranding;

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

  /// Welcome video payload from the last `/public/site` bootstrap.
  /// Avoids a second site round-trip when loading home.
  Map<String, dynamic>? cachedWelcomeVideo;

  /// Restore cached brand immediately (no network) so splash paints branded.
  void restoreCachedBranding() {
    final raw = localDb.read(LocalKeyHelper.mobileBranding);
    if (raw is Map) {
      try {
        applyMobileBranding(
          MobileBranding.fromJson(Map<String, dynamic>.from(raw)),
          persist: false,
        );
      } catch (e) {
        debugPrint('Cached mobile branding ignored: $e');
      }
    }
  }

  /// Cold-start: fetch platform brand (Expouse default).
  Future<void> loadPlatformBranding() async {
    restoreCachedBranding();
    try {
      final response = await _brandingService.getPlatformBranding();
      final data = _unwrapData(response.data);
      if (data != null) {
        applyMobileBranding(MobileBranding.fromJson(data));
      }
    } catch (e) {
      debugPrint('Platform mobile branding skipped: $e');
    }
  }

  /// Apply resolved brand from `/public/site` or `/public/mobile-branding`.
  void applyMobileBranding(
    MobileBranding branding, {
    bool persist = true,
  }) {
    mobileBranding = branding;
    final color = branding.brand.primaryColor;
    if (color.isNotEmpty) {
      updateAppColors(color);
    }
    if (persist) {
      localDb.write(LocalKeyHelper.mobileBranding, branding.toJson());
    }
  }

  /// Parse `mobile_branding` from a `/public/site` payload when present.
  void applyMobileBrandingFromSite(Map<String, dynamic> siteData) {
    final raw = siteData['mobile_branding'];
    if (raw is Map) {
      applyMobileBranding(
        MobileBranding.fromJson(Map<String, dynamic>.from(raw)),
      );
    }
  }

  /// Splash entry: restore a saved session when the Sanctum token is still valid.
  /// Call [loadPlatformBranding] first from SplashView so the UI can rebuild.
  Future<void> initialRoute() async {
    await SecureAuthStorage.instance.init();
    DioConfig.obj.init();
    // Brief brand splash only - long artificial delays block time-to-interactive.
    await Future.delayed(const Duration(milliseconds: 400));

    if (!SecureAuthStorage.instance.hasToken) {
      _goAuth();
      return;
    }

    final sessionOk = await _validateStoredSession();
    if (sessionOk) {
      Get.offAll(
        () => const RootView(),
        binding: RootBinding(),
      );
    } else {
      await _clearInvalidSession();
      _goAuth();
    }
  }

  void _goAuth() {
    Get.offAll(
      () => const AuthView(),
      binding: AuthBinding(),
    );
  }

  /// Returns true when the stored token is accepted by the API, or when the
  /// device is offline (keep the local session and let the shell retry later).
  Future<bool> _validateStoredSession() async {
    try {
      final response = await AuthService().me(skipAuthLogout: true);
      final data = response.data;
      if (data is Map && data['user'] is Map) {
        await localDb.write(
          LocalKeyHelper.userInfo,
          Map<String, dynamic>.from(data['user'] as Map),
        );
      }
      return true;
    } on DioException catch (e) {
      final status = e.response?.statusCode;
      if (status == 401 || status == 403) {
        debugPrint('Stored session rejected ($status) - requiring login');
        return false;
      }
      // Network / server hiccup: keep the token and enter the app.
      debugPrint('Session validate skipped (offline/error): $e');
      return true;
    } catch (e) {
      debugPrint('Session validate skipped: $e');
      return true;
    }
  }

  Future<void> _clearInvalidSession() async {
    await SecureAuthStorage.instance.clearToken();
    await localDb.remove(LocalKeyHelper.userInfo);
    await localDb.remove(LocalKeyHelper.token);
  }

  Map<String, dynamic>? _unwrapData(dynamic body) {
    if (body is Map && body['data'] is Map) {
      return Map<String, dynamic>.from(body['data'] as Map);
    }
    if (body is Map) {
      return Map<String, dynamic>.from(body);
    }
    return null;
  }
}
