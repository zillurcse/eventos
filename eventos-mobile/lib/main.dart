import 'dart:async';

import 'package:expouse/utils/bindings/initial_binding.dart';
import 'package:expouse/utils/enum/enums.dart';
import 'package:expouse/utils/helpers/responsive.dart';
import 'package:expouse/utils/theme/app_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import 'package:get_storage/get_storage.dart';
import 'package:expouse/widgets/connectivity_wrapper.dart';
import 'package:expouse/features/root/root_controller.dart';
import 'package:expouse/utils/config/dio_config.dart';
import 'package:expouse/features/notifications/push_notification_service.dart';
import 'package:expouse/utils/helpers/secure_auth_storage.dart';

import 'features/splash/splash_view.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await GetStorage.init();
  await SecureAuthStorage.instance.init();
  DioConfig.obj.init();
  // Keep RootController alive without wrapping GetMaterialApp in GetBuilder
  // (which remounted the entire navigator on every theme update()).
  Get.put(RootController(), permanent: true);
  unawaited(
    SystemChrome.setPreferredOrientations([
      DeviceOrientation.portraitUp,
      DeviceOrientation.portraitDown,
    ]),
  );
  SystemChrome.setSystemUIOverlayStyle(
    const SystemUiOverlayStyle(
      statusBarColor: Colors.transparent,
      statusBarBrightness: Brightness.light,
      statusBarIconBrightness: Brightness.dark,
    ),
  );
  runApp(const MyApp());
  // Defer Firebase / FCM / notification permission until after first frame.
  WidgetsBinding.instance.addPostFrameCallback((_) {
    unawaited(PushNotificationService.instance.init());
  });
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    final window = View.of(context);
    final size = window.physicalSize / window.devicePixelRatio;
    final isTab =
        Responsive.obj.getResponsiveState(size.width) != ResponsiveState.mobile;
    return ScreenUtilInit(
      designSize: isTab ? size : const Size(360, 690),
      minTextAdapt: true,
      useInheritedMediaQuery: true,
      builder: (context, child) {
        return SafeArea(
          top: false,
          bottom: true,
          child: GetMaterialApp(
            title: "Expouse",
            debugShowCheckedModeBanner: false,
            initialBinding: initialBinding(),
            themeMode: ThemeMode.light,
            theme: AppTheme.lightTheme,
            // Keep a stable home so theme reloads (after event select)
            // don't reset the navigator back to splash.
            home: const SplashView(),
            builder: (context, child) {
              return MediaQuery(
                data: MediaQuery.of(
                  context,
                ).copyWith(textScaler: const TextScaler.linear(1.0)),
                child: ConnectivityWrapper(child: child!),
              );
            },
          ),
        );
      },
    );
  }
}
