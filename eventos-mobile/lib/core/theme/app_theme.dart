import 'package:flutter/material.dart';

class AppColors {
  static const Color brandPurple = Color(0xFF635BEB);
  static const Color brandBlue = Color(0xFF5B6FE8);
  static const Color headline = Color(0xFF2B2F3A);
  static const Color body = Color(0xFF8A90A5);
  static const Color label = Color(0xFF6B7280);
  static const Color placeholder = Color(0xFFB0B5C3);
  static const Color inputBorder = Color(0xFFE2E5EE);
  static const Color screenBg = Color(0xFFF7F7FB);
  static const Color otpButtonBg = Color(0xFFECEBFF);
  static const Color divider = Color(0xFFE6E8F0);
  static const Color dotInactive = Color(0xFFD4D7E3);
  static const Color link = brandPurple;
}

class AppTheme {
  static ThemeData light({Color seedColor = AppColors.brandPurple}) {
    final colorScheme = ColorScheme.fromSeed(seedColor: seedColor);

    return ThemeData(
      colorScheme: colorScheme,
      useMaterial3: true,
      scaffoldBackgroundColor: AppColors.screenBg,
      appBarTheme: const AppBarTheme(
        centerTitle: true,
        backgroundColor: AppColors.brandPurple,
        foregroundColor: Colors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: AppColors.brandPurple,
          foregroundColor: Colors.white,
          minimumSize: const Size.fromHeight(50),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(10),
          ),
        ),
      ),
    );
  }
}
