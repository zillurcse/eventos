import 'dart:ui';
import 'package:get/get.dart';
import 'app_theme.dart';

const backgroundColorLight = Color(0xfff7f7fb);

const accentPrimary = Color(0xffffffff);
const accentHover = Color(0xfff4f4f4);

Color primaryTheme = const Color(0xff6452e7);
Color primaryHover = const Color(0xff5041B8);
Color primaryFocused = const Color(0xffF0EEFD);

void updateAppColors(String hexString) {
  final hex = hexString.replaceAll('#', '');
  if (hex.length == 6 || hex.length == 8) {
    final parsedColor = Color(int.parse('0xff$hex'));
    primaryTheme = parsedColor;
    primaryHover = Color.lerp(parsedColor, const Color(0xFF000000), 0.1) ?? parsedColor;
    primaryFocused = parsedColor.withValues(alpha: 0.10);
    
    // Trigger a complete UI rebuild with the new theme colors
    Get.changeTheme(AppTheme.lightTheme);
  }
}

const heading = Color(0xff212529);
const body = Color(0xff4d5154);
const caption = Color(0xff64676a);
const ghost = Color(0xff9b9d9e);

const tertiaryText = Color(0xffffffff);
const stroke = Color(0xffd1d2de);
const strokeLight = Color(0xffe8e8ee);

const yellowWarning = Color(0xffF4BE32);
const yellowWarningLight = Color(0xffFEF8EA);
const greenPositive = Color(0xff4CBB3E);
const greenPositiveLight = Color(0xffEDF8EC);
const redError = Color(0xffF24822);
const redErrorLight = Color(0xffFEEDE9);
const blueInfo = Color(0xff3280F4);
const blueInfoLight = Color(0xffEAF2FE);






