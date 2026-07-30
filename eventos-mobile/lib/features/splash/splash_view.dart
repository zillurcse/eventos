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
    AppDataProvider.obj.initialRoute();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: context.primaryTheme,
      body: Center(child: CustomImage("assets/svg/img/logo.svg", height: 50.h)),
    );
  }
}
