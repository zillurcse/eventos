import 'package:flutter/material.dart';

class DashedBorderPainter extends CustomPainter {
  final Color color;
  final double radius;
  final double dashWidth;
  final double dashGap;
  final double strokeWidth;

  const DashedBorderPainter({
    required this.color,
    required this.radius,
    this.dashWidth = 6,
    this.dashGap = 4,
    this.strokeWidth = 1.5,
  });

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..strokeWidth = strokeWidth
      ..style = PaintingStyle.stroke;

    final path = Path()
      ..addRRect(RRect.fromRectAndRadius(
        Rect.fromLTWH(0, 0, size.width, size.height),
        Radius.circular(radius),
      ));

    for (final metric in path.computeMetrics()) {
      double distance = 0;
      bool draw = true;
      while (distance < metric.length) {
        final len = draw ? dashWidth : dashGap;
        if (draw) canvas.drawPath(metric.extractPath(distance, distance + len), paint);
        distance += len;
        draw = !draw;
      }
    }
  }

  @override
  bool shouldRepaint(DashedBorderPainter old) =>
      old.color != color || old.radius != radius ||
      old.dashWidth != dashWidth || old.dashGap != dashGap ||
      old.strokeWidth != strokeWidth;
}
