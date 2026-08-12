import 'dart:math' as math;

import 'package:flutter/material.dart';

/// Badge design → layout helpers.
///
/// Faithful port of `eventos-event/app/utils/badgeDesign.ts` so an attendee's
/// on-screen badge matches the organizer's approved design.

const badgeTextTypes = {'h1', 'h2', 'h3', 'h4', 'h6', 'p', 'a', 'span'};

/// CSS's fixed 96dpi: 1mm = 96/25.4 px.
const _pxPerMm = 96 / 25.4;

const _keyAliases = {'name': 'full_name'};

Map<String, dynamic> _obj(dynamic v) {
  if (v is Map) return Map<String, dynamic>.from(v);
  return const {};
}

class BadgePageSize {
  final double width;
  final double height;
  final double widthMm;
  final double heightMm;

  const BadgePageSize({
    required this.width,
    required this.height,
    required this.widthMm,
    required this.heightMm,
  });
}

BadgePageSize badgePageSize(Map<String, dynamic>? badgeJson) {
  final cfg = _obj(badgeJson?['page_config']);
  final widthMm = _asDouble(cfg['presetWidth']) ?? 105;
  final heightMm = _asDouble(cfg['presetHeight']) ?? 148;
  return BadgePageSize(
    width: (widthMm * _pxPerMm).roundToDouble(),
    height: (heightMm * _pxPerMm).roundToDouble(),
    widthMm: widthMm,
    heightMm: heightMm,
  );
}

List<Map<String, dynamic>> badgeBoxes(
  Map<String, dynamic>? badgeJson,
  String side,
) {
  final list = side == 'back'
      ? (badgeJson?['backBoxes'])
      : (badgeJson?['frontBoxes']);
  if (list is! List) return const [];
  return list
      .whereType<Map>()
      .map((e) => Map<String, dynamic>.from(e))
      .where((b) => b['visible'] == true)
      .toList();
}

String badgeBackground(Map<String, dynamic>? badgeJson, String side) {
  final raw = side == 'back'
      ? (badgeJson?['backBackground'])
      : (badgeJson?['frontBackground']);
  if (raw == null || '$raw'.isEmpty) return 'white';
  return '$raw';
}

class BadgePunch {
  final String? long;
  final String? circle;
  const BadgePunch({this.long, this.circle});
}

BadgePunch badgePunch(Map<String, dynamic>? badgeJson) {
  return BadgePunch(
    long: badgeJson?['punchLong']?.toString(),
    circle: badgeJson?['punchCircle']?.toString(),
  );
}

String? badgeKey(Map<String, dynamic> box) {
  final key = box['key']?.toString();
  if (key == null || key.isEmpty) return null;
  return _keyAliases[key] ?? key;
}

String? _merged(Map<String, dynamic> box, Map<String, String>? data) {
  final key = badgeKey(box);
  if (data == null || key == null) return null;
  return data[key];
}

String badgeText(Map<String, dynamic> box, Map<String, String>? data) {
  final value = _merged(box, data);
  if (value != null) return value;
  final props = _obj(box['properties']);
  return (box['text'] ?? props['text'] ?? '').toString();
}

String badgeImage(Map<String, dynamic> box, Map<String, String>? data) {
  final value = _merged(box, data);
  if (value != null && value.isNotEmpty) return value;
  final props = _obj(box['properties']);
  final src = _obj(props['src'])['url']?.toString();
  if (src != null && src.isNotEmpty) return src;
  final text = box['text']?.toString() ?? '';
  if (text.startsWith('http')) return text;
  return '';
}

class BadgeQrOptions {
  final String value;
  final double radius;
  final Color blackColor;
  final Color whiteColor;

  const BadgeQrOptions({
    required this.value,
    this.radius = 0,
    this.blackColor = Colors.black,
    this.whiteColor = Colors.transparent,
  });
}

BadgeQrOptions badgeQr(Map<String, dynamic> box, Map<String, String>? data) {
  final props = _obj(box['properties']);
  final qr = _obj(props['qrcode']);
  final merged = _merged(box, data);
  final value = (data?['qrcode']?.isNotEmpty == true
          ? data!['qrcode']
          : null) ??
      (merged != null && merged.isNotEmpty ? merged : null) ??
      qr['value']?.toString() ??
      box['text']?.toString() ??
      'preview';
  return BadgeQrOptions(
    value: value,
    radius: _asDouble(qr['radius']) ?? 0,
    blackColor: parseCssColor(qr['blackColor']?.toString() ?? '#000000') ??
        Colors.black,
    whiteColor: parseCssColor(qr['whiteColor']?.toString() ?? 'transparent') ??
        Colors.transparent,
  );
}

/// Absolute position + size of a box on the design canvas (in design px).
Rect badgeBoxRect(Map<String, dynamic> box) {
  final props = _obj(box['properties']);
  final pos = _obj(box['position']);
  final size = _obj(props['size']);
  final top = _asDouble(pos['top']) ?? _asDouble(props['y']) ?? 0;
  final left = _asDouble(pos['left']) ?? _asDouble(props['x']) ?? 0;
  final width = _asDouble(size['width']) ?? 0;
  final height = _asDouble(size['height']) ?? 0;
  return Rect.fromLTWH(left, top, width, height);
}

double badgeBoxRotation(Map<String, dynamic> box) {
  final props = _obj(box['properties']);
  return (_asDouble(props['rotation']) ?? 0) * 3.141592653589793 / 180;
}

Color? badgeBoxFill(Map<String, dynamic> box) {
  final props = _obj(box['properties']);
  if (props['fillTransparency'] == true) return Colors.transparent;
  return parseCssColor(props['fillColor']?.toString() ?? '#ffffff') ??
      Colors.white;
}

Border? badgeBoxBorder(Map<String, dynamic> box) {
  final props = _obj(box['properties']);
  final width = _asDouble(props['strokeWidth']) ?? 0;
  if (width <= 0) return null;
  final color =
      parseCssColor(props['strokeColor']?.toString() ?? '#000000') ??
          Colors.black;
  return Border.all(color: color, width: width);
}

int badgeBoxZIndex(Map<String, dynamic> box) {
  final z = box['zIndex'];
  if (z is int) return z;
  return int.tryParse('$z') ?? 0;
}

class BadgeTextLayout {
  final double fontSize;
  final FontWeight fontWeight;
  final FontStyle fontStyle;
  final TextDecoration decoration;
  final Color color;
  final TextAlign textAlign;
  final Alignment alignment;
  final bool isParagraph;
  final TextOverflow overflow;
  final int? maxLines;

  const BadgeTextLayout({
    required this.fontSize,
    required this.fontWeight,
    required this.fontStyle,
    required this.decoration,
    required this.color,
    required this.textAlign,
    required this.alignment,
    required this.isParagraph,
    this.overflow = TextOverflow.ellipsis,
    this.maxLines,
  });
}

BadgeTextLayout badgeTextLayout(Map<String, dynamic> box) {
  final props = _obj(box['properties']);
  final isParagraph = box['type'] == 'p';
  final boxH = _asDouble(_obj(props['size'])['height']) ?? 0;
  final calculated = (boxH * (isParagraph ? 0.2 : 0.4)).clamp(12.0, 48.0);
  final rawSize = props['fontSize'];
  final fontSize = (rawSize == null || rawSize == 'Auto')
      ? calculated
      : (_asDouble(rawSize) ?? calculated);

  final weightRaw = (props['fontWeight'] ?? 'normal').toString().toLowerCase();
  final fontWeight = switch (weightRaw) {
    'bold' || '700' => FontWeight.w700,
    '600' || 'semibold' => FontWeight.w600,
    '500' || 'medium' => FontWeight.w500,
    '300' || 'light' => FontWeight.w300,
    _ => FontWeight.w400,
  };

  final styleRaw = (props['fontStyle'] ?? 'normal').toString();
  final fontStyle =
      styleRaw == 'italic' ? FontStyle.italic : FontStyle.normal;

  final decoRaw = (props['textDecoration'] ?? 'none').toString();
  final decoration = decoRaw.contains('underline')
      ? TextDecoration.underline
      : TextDecoration.none;

  final color =
      parseCssColor(props['color']?.toString() ?? 'black') ?? Colors.black;

  final hAlign = (props['horizontalAlign'] ?? 'center').toString();
  final vAlign = (props['verticalAlign'] ?? 'middle').toString();

  final textAlign = switch (hAlign) {
    'left' => TextAlign.left,
    'right' => TextAlign.right,
    'justify' => TextAlign.justify,
    _ => TextAlign.center,
  };

  final alignment = Alignment(
    switch (hAlign) {
      'left' => -1.0,
      'right' => 1.0,
      _ => 0.0,
    },
    switch (vAlign) {
      'top' => -1.0,
      'bottom' => 1.0,
      _ => 0.0,
    },
  );

  return BadgeTextLayout(
    fontSize: fontSize,
    fontWeight: fontWeight,
    fontStyle: fontStyle,
    decoration: decoration,
    color: color,
    textAlign: textAlign,
    alignment: alignment,
    isParagraph: isParagraph,
    maxLines: isParagraph ? null : 2,
  );
}

String applyTextTransform(String text, Map<String, dynamic> box) {
  final props = _obj(box['properties']);
  final transform = (props['textTransform'] ?? 'none').toString();
  return switch (transform) {
    'uppercase' => text.toUpperCase(),
    'lowercase' => text.toLowerCase(),
    'capitalize' => text
        .split(' ')
        .map((w) => w.isEmpty ? w : '${w[0].toUpperCase()}${w.substring(1)}')
        .join(' '),
    _ => text,
  };
}

BoxFit badgeImageFit(Map<String, dynamic> box) {
  final props = _obj(box['properties']);
  return switch (props['objectFit']?.toString()) {
    'contain' => BoxFit.contain,
    'fill' => BoxFit.fill,
    'none' => BoxFit.none,
    'scale-down' => BoxFit.scaleDown,
    _ => BoxFit.cover,
  };
}

class BadgeAvatarFrame {
  final BorderRadius? borderRadius;
  final Color backgroundColor;
  final Border? border;
  final List<BoxShadow>? boxShadow;

  const BadgeAvatarFrame({
    this.borderRadius,
    this.backgroundColor = const Color(0xFFF3F4F6),
    this.border,
    this.boxShadow,
  });
}

BadgeAvatarFrame badgeAvatarFrame(Map<String, dynamic> box) {
  final avatar = _obj(_obj(box['properties'])['avatar']);
  final persisted = _obj(avatar['containerStyle']);

  BorderRadius? radius;
  if (persisted.isNotEmpty) {
    radius = _parseBorderRadius(persisted['borderRadius']?.toString());
  } else {
    final shape = (avatar['shape'] ?? 'rounded').toString();
    final r = _asDouble(avatar['radius']) ?? 14;
    radius = switch (shape) {
      'circle' => BorderRadius.circular(9999),
      'rounded' => BorderRadius.circular(r),
      _ => BorderRadius.circular(r),
    };
  }

  Color bg = const Color(0xFFF3F4F6);
  final bgRaw = persisted['backgroundColor']?.toString();
  if (bgRaw != null) {
    bg = parseCssColor(bgRaw) ?? bg;
  }

  Border? border;
  if (avatar['showBorder'] == true) {
    border = Border.all(color: const Color(0xFFD1D5DB));
  }

  List<BoxShadow>? shadow;
  if (avatar['showRing'] == true) {
    shadow = const [
      BoxShadow(color: Colors.white, spreadRadius: 2),
      BoxShadow(color: Color(0xFF9CA3AF), spreadRadius: 4),
    ];
  }

  return BadgeAvatarFrame(
    borderRadius: radius,
    backgroundColor: bg,
    border: border,
    boxShadow: shadow,
  );
}

/// Parses CSS color strings used in badge designs (`#rgb`, `#rrggbb`,
/// `rgb()`, `rgba()`, named colors, `transparent`, and simple
/// `linear-gradient(...)` - gradients return the first stop color as a
/// solid fallback; use [parseCssGradient] for full gradients).
Color? parseCssColor(String? raw) {
  if (raw == null) return null;
  final s = raw.trim().toLowerCase();
  if (s.isEmpty) return null;
  if (s == 'transparent') return Colors.transparent;
  if (s == 'white') return Colors.white;
  if (s == 'black') return Colors.black;

  if (s.startsWith('#')) {
    var hex = s.substring(1);
    if (hex.length == 3) {
      hex = hex.split('').map((c) => '$c$c').join();
    }
    if (hex.length == 6) {
      final value = int.tryParse(hex, radix: 16);
      if (value != null) return Color(0xFF000000 | value);
    }
    if (hex.length == 8) {
      final value = int.tryParse(hex, radix: 16);
      if (value != null) return Color(value);
    }
  }

  final rgb = RegExp(r'rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)(?:\s*,\s*([\d.]+))?\s*\)')
      .firstMatch(s);
  if (rgb != null) {
    final r = double.parse(rgb.group(1)!).round().clamp(0, 255);
    final g = double.parse(rgb.group(2)!).round().clamp(0, 255);
    final b = double.parse(rgb.group(3)!).round().clamp(0, 255);
    final a = rgb.group(4) != null
        ? (double.parse(rgb.group(4)!) * 255).round().clamp(0, 255)
        : 255;
    return Color.fromARGB(a, r, g, b);
  }

  if (s.startsWith('linear-gradient')) {
    final stops = _gradientStops(s);
    if (stops.isNotEmpty) return stops.first;
  }

  return null;
}

BoxDecoration badgeBackgroundDecoration(String css) {
  final trimmed = css.trim();
  if (trimmed.startsWith('linear-gradient')) {
    final stops = _gradientStops(trimmed);
    final angle = _gradientAngle(trimmed);
    if (stops.length >= 2) {
      return BoxDecoration(
        gradient: LinearGradient(
          begin: _alignmentFromAngle(angle),
          end: _alignmentFromAngle(angle + 180),
          colors: stops,
        ),
      );
    }
    if (stops.length == 1) {
      return BoxDecoration(color: stops.first);
    }
  }
  return BoxDecoration(
    color: parseCssColor(trimmed) ?? Colors.white,
  );
}

List<Color> _gradientStops(String css) {
  final inner = css.substring(css.indexOf('(') + 1, css.lastIndexOf(')'));
  final parts = <String>[];
  var depth = 0;
  final buf = StringBuffer();
  for (final ch in inner.split('')) {
    if (ch == '(') depth++;
    if (ch == ')') depth--;
    if (ch == ',' && depth == 0) {
      parts.add(buf.toString().trim());
      buf.clear();
    } else {
      buf.write(ch);
    }
  }
  if (buf.isNotEmpty) parts.add(buf.toString().trim());

  final colors = <Color>[];
  for (final part in parts) {
    // Skip angle tokens like "200deg"
    if (RegExp(r'^\d+(\.\d+)?deg$').hasMatch(part)) continue;
    if (RegExp(r'^(to\s+)').hasMatch(part)) continue;
    // Strip trailing percentage: "#2B6CB0 0%"
    final colorPart = part.replaceAll(RegExp(r'\s+\d+(\.\d+)?%$'), '').trim();
    final c = parseCssColor(colorPart);
    if (c != null) colors.add(c);
  }
  return colors;
}

double _gradientAngle(String css) {
  final m = RegExp(r'(\d+(?:\.\d+)?)deg').firstMatch(css);
  if (m != null) return double.parse(m.group(1)!);
  return 180;
}

/// CSS angles: 0deg = to top, 90deg = to right. Map to Alignment begin.
Alignment _alignmentFromAngle(double deg) {
  final rad = deg * 3.141592653589793 / 180;
  // CSS: 0° = north. Flutter Alignment: (0,-1) = top.
  final x = math.sin(rad);
  final y = -math.cos(rad);
  return Alignment(x.clamp(-1.0, 1.0), y.clamp(-1.0, 1.0));
}

BorderRadius? _parseBorderRadius(String? raw) {
  if (raw == null || raw.isEmpty) return null;
  final m = RegExp(r'([\d.]+)px').firstMatch(raw);
  if (m != null) {
    return BorderRadius.circular(double.parse(m.group(1)!));
  }
  if (raw.contains('%') || raw == '9999px') {
    return BorderRadius.circular(9999);
  }
  return null;
}

double? _asDouble(dynamic v) {
  if (v == null) return null;
  if (v is num) return v.toDouble();
  return double.tryParse('$v');
}
