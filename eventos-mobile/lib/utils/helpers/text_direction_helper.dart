import 'package:flutter/painting.dart';

/// Detects right-to-left scripts (Arabic, Hebrew, etc.) for feed text.
class TextDirectionHelper {
  TextDirectionHelper._();

  static final RegExp _rtlScript = RegExp(
    r'[\u0590-\u05FF\u0600-\u06FF\u0700-\u074F\u0750-\u077F'
    r'\u08A0-\u08FF\uFB1D-\uFB4F\uFB50-\uFDFF\uFE70-\uFEFF]',
  );

  static bool isRtl(String? text) {
    if (text == null || text.isEmpty) return false;
    return _rtlScript.hasMatch(text);
  }

  static TextDirection directionOf(String? text) =>
      isRtl(text) ? TextDirection.rtl : TextDirection.ltr;

  static TextAlign alignOf(String? text) =>
      isRtl(text) ? TextAlign.right : TextAlign.left;
}
