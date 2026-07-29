import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../core/theme/app_theme.dart';

/// Purple branded launch splash matching the Expouse design.
class SplashScreen extends StatelessWidget {
  const SplashScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light.copyWith(
        statusBarColor: Colors.transparent,
        systemNavigationBarColor: AppColors.brandPurple,
      ),
      child: Scaffold(
        backgroundColor: AppColors.brandPurple,
        body: SizedBox.expand(
          child: Image.asset(
            'assets/onboarding/splash_brand.png',
            fit: BoxFit.cover,
            errorBuilder: (context, error, stackTrace) {
              return const Column(
                children: [
                  Spacer(flex: 3),
                  Icon(Icons.graphic_eq_rounded, size: 72, color: Colors.white),
                  Spacer(flex: 3),
                  Padding(
                    padding: EdgeInsets.only(bottom: 28),
                    child: Text(
                      'Powered by Okum International',
                      style: TextStyle(color: Colors.white, fontSize: 13),
                    ),
                  ),
                ],
              );
            },
          ),
        ),
      ),
    );
  }
}
