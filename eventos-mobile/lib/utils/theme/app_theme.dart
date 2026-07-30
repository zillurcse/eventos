import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

import 'app_colors.dart';

class AppTheme {
  static ThemeData get lightTheme => ThemeData(
    brightness: Brightness.light,
    scaffoldBackgroundColor: backgroundColorLight,
    cardColor: accentPrimary,
    dividerColor: accentHover,
    colorScheme: ColorScheme(
      brightness: Brightness.light,
      onSurfaceVariant: backgroundColorLight,
      primary: primaryTheme,
      onPrimary: primaryHover,
      primaryContainer: primaryFocused,

      surfaceContainerLowest: heading,
      surfaceContainerLow: body,
      surfaceContainer: caption,
      surfaceContainerHigh: ghost,

      tertiary: tertiaryText,
      onTertiary: stroke,
      onTertiaryContainer: strokeLight,

      secondary: yellowWarning,
      onSecondary: yellowWarningLight,
      surface: greenPositive,
      onSurface: greenPositiveLight,
      error: redError,
      onError: redErrorLight,
      outline: blueInfo,
      outlineVariant: blueInfoLight,
    ),

    checkboxTheme: CheckboxThemeData(
      fillColor: WidgetStateProperty.resolveWith((Set<WidgetState> states) {
        if (states.contains(WidgetState.selected)) {
          return primaryTheme;
        }
        return Colors.transparent;
      }),
      checkColor: WidgetStateProperty.all(accentPrimary),
      side: BorderSide(
        color: ghost,
        width: 1.0,
      ),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(4),
      ),
    ),
    dividerTheme: DividerThemeData(
      color: strokeLight,
      thickness: 1.sp,
    ),

    fontFamily: "Cairo",
    textTheme: _textTheme(body),
  );

  static TextTheme _textTheme(Color color) {
    return TextTheme(
      displayLarge: TextStyle(
        color: color,
        fontSize: 22.sp,
        fontWeight: FontWeight.w700,
        height: 1.3,
        letterSpacing: 0.0,
      ),
      displayMedium: TextStyle(
        color: color,
        fontSize: 20.sp,
        fontWeight: FontWeight.w700,
        height: 1.3,
        letterSpacing: 0.0,
      ),
      displaySmall: TextStyle(
        color: color,
        fontSize: 18.sp,
        fontWeight: FontWeight.w700,
        height: 1.3,
        letterSpacing: 0.0,
      ),
      headlineLarge: TextStyle(
        color: color,
        fontSize: 16.sp,
        fontWeight: FontWeight.w700,
        height: 1.3,
        letterSpacing: 0.0,
      ),
      headlineMedium: TextStyle(
        color: color,
        fontSize: 14.sp,
        fontWeight: FontWeight.w700,
        height: 1.3,
        letterSpacing: 0.0,
      ),
      headlineSmall: TextStyle(
        color: color,
        fontSize: 16.sp,
        fontWeight: FontWeight.w600,
        height: 1.3,
        letterSpacing: 0.0,
      ),
      titleLarge: TextStyle(
        color: color,
        fontSize: 14.sp,
        fontWeight: FontWeight.w600,
        height: 1.3,
        letterSpacing: 0.0,
      ),
      titleMedium: TextStyle(
        color: color,
        fontSize: 16.sp,
        fontWeight: FontWeight.w400,
        height: 1.3,
        letterSpacing: 0.0,
      ),
      titleSmall: TextStyle(
        color: color,
        fontSize: 14.sp,
        fontWeight: FontWeight.w400,
        height: 1.3,
        letterSpacing: 0.0,
      ),
      bodyLarge: TextStyle(
        color: color,
        fontSize: 12.sp,
        fontWeight: FontWeight.w400,
        height: 1.3,
        letterSpacing: 0.0,
      ),
      bodyMedium: TextStyle(
        color: color,
        fontSize: 12.sp,
        fontWeight: FontWeight.w600,
        height: 1.3,
        letterSpacing: 0.0,
      ),
      bodySmall: TextStyle(
        color: color,
        fontSize: 12.sp,
        fontWeight: FontWeight.w400,
        height: 1.3,
        letterSpacing: 0.0,
      ),
      labelLarge: TextStyle(
        color: color,
        fontSize: 16.sp,
        fontWeight: FontWeight.w700,
        height: 1.3,
        letterSpacing: 0.0,
      ),
      labelMedium: TextStyle(
        color: color,
        fontSize: 14.sp,
        fontWeight: FontWeight.w700,
        height: 1.3,
        letterSpacing: 0.0,
      ),
      labelSmall: TextStyle(
        color: color,
        fontSize: 12.sp,
        fontWeight: FontWeight.w700,
        height: 1.3,
        letterSpacing: 0.0,
      ),
    );
  }
}
