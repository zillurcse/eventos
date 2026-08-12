import 'package:expouse/utils/extension/theme_ext.dart';
import 'package:expouse/utils/helpers/app_data_provider.dart';
import 'package:expouse/widgets/custom_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

class SplashView extends StatefulWidget {
  const SplashView({super.key});

  @override
  State<SplashView> createState() => SplashViewState();
}

class SplashViewState extends State<SplashView> {
  @override
  void initState() {
    super.initState();
    AppDataProvider.obj.restoreCachedBranding();
    _bootstrap();
  }

  Future<void> _bootstrap() async {
    if (mounted) setState(() {});
    await AppDataProvider.obj.loadPlatformBranding();
    if (mounted) setState(() {});
    await AppDataProvider.obj.initialRoute();
  }

  @override
  Widget build(BuildContext context) {
    final brand = AppDataProvider.obj.mobileBranding?.brand;
    final splashUrl = brand?.splashUrl;
    final iconUrl = brand?.iconUrl;

    return Scaffold(
      backgroundColor: context.primaryTheme,
      body: splashUrl != null && splashUrl.isNotEmpty
          ? SizedBox.expand(
              child: CustomImage(
                splashUrl,
                fit: BoxFit.cover,
                height: double.infinity,
                width: double.infinity,
              ),
            )
          : Center(
              child: CustomImage(
                (iconUrl != null && iconUrl.isNotEmpty)
                    ? iconUrl
                    : 'assets/svg/img/logo.svg',
                height: 50.h,
              ),
            ),
    );
  }
}
