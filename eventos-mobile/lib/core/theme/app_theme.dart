import 'package:flutter/material.dart';

/// Brand colors from the Expouse splash / onboarding designs.
class AppColors {
  static const Color brandPurple = Color(0xFF635BEB);
  static const Color brandBlue = Color(0xFF5B6FE8);
  static const Color headline = Color(0xFF1A1F36);
  static const Color body = Color(0xFF8A90A5);
  static const Color dotInactive = Color(0xFFD4D7E3);
}

class AppTheme {
  static ThemeData light({Color seedColor = AppColors.brandBlue}) {
    final colorScheme = ColorScheme.fromSeed(seedColor: seedColor);

    return ThemeData(
      colorScheme: colorScheme,
      useMaterial3: true,
      scaffoldBackgroundColor: Colors.white,
      appBarTheme: AppBarTheme(
        centerTitle: false,
        backgroundColor: colorScheme.surface,
        foregroundColor: colorScheme.onSurface,
        elevation: 0,
        scrolledUnderElevation: 1,
      ),
      inputDecorationTheme: InputDecorationTheme(
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
        filled: true,
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: AppColors.brandBlue,
          foregroundColor: Colors.white,
          minimumSize: const Size.fromHeight(52),
          shape: const StadiumBorder(),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: AppColors.brandBlue,
          minimumSize: const Size.fromHeight(52),
          side: const BorderSide(color: AppColors.brandBlue, width: 1.5),
          shape: const StadiumBorder(),
        ),
      ),
    );
  }
}
