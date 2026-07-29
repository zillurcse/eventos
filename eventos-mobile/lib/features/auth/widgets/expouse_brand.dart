import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../core/theme/app_theme.dart';

/// Stylized Expouse mark (vertical bars + dots).
class ExpouseLogoMark extends StatelessWidget {
  const ExpouseLogoMark({
    super.key,
    this.size = 28,
    this.color = Colors.white,
  });

  final double size;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: size,
      height: size,
      child: CustomPaint(painter: _ExpouseLogoPainter(color)),
    );
  }
}

class _ExpouseLogoPainter extends CustomPainter {
  _ExpouseLogoPainter(this.color);

  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..style = PaintingStyle.fill;

    final w = size.width;
    final h = size.height;
    final colW = w / 7;
    final r = colW * 0.28;

    void bar(double cx, double top, double bottom) {
      canvas.drawRRect(
        RRect.fromLTRBR(cx - r, top, cx + r, bottom, Radius.circular(r)),
        paint,
      );
    }

    void dot(double cx, double cy) {
      canvas.drawCircle(Offset(cx, cy), r * 0.85, paint);
    }

    final c1 = colW * 1.0;
    final c2 = colW * 2.1;
    final c3 = colW * 3.2;
    final c4 = colW * 4.3;
    final c5 = colW * 5.4;
    final c6 = colW * 6.3;

    bar(c1, h * 0.28, h * 0.82);
    bar(c2, h * 0.08, h * 0.62);
    dot(c2, h * 0.78);
    dot(c3, h * 0.12);
    bar(c3, h * 0.28, h * 0.72);
    dot(c3, h * 0.88);
    bar(c4, h * 0.08, h * 0.92);
    dot(c5, h * 0.22);
    dot(c5, h * 0.38);
    bar(c5, h * 0.52, h * 0.88);
    bar(c6, h * 0.32, h * 0.78);
  }

  @override
  bool shouldRepaint(covariant _ExpouseLogoPainter oldDelegate) =>
      oldDelegate.color != color;
}

class ExpouseWordmark extends StatelessWidget {
  const ExpouseWordmark({
    super.key,
    this.color = Colors.white,
    this.markSize = 26,
    this.fontSize = 18,
  });

  final Color color;
  final double markSize;
  final double fontSize;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        ExpouseLogoMark(size: markSize, color: color),
        const SizedBox(width: 8),
        Text(
          'EXPOUSE',
          style: TextStyle(
            color: color,
            fontSize: fontSize,
            fontWeight: FontWeight.w700,
            letterSpacing: 1.4,
          ),
        ),
      ],
    );
  }
}

class AuthHeaderBar extends StatelessWidget implements PreferredSizeWidget {
  const AuthHeaderBar({super.key, this.showBack = false});

  final bool showBack;

  @override
  Size get preferredSize => const Size.fromHeight(56);

  @override
  Widget build(BuildContext context) {
    return AppBar(
      backgroundColor: AppColors.brandPurple,
      foregroundColor: Colors.white,
      elevation: 0,
      scrolledUnderElevation: 0,
      centerTitle: true,
      automaticallyImplyLeading: showBack,
      iconTheme: const IconThemeData(color: Colors.white),
      title: const ExpouseWordmark(),
      systemOverlayStyle: SystemUiOverlayStyle.light.copyWith(
        statusBarColor: AppColors.brandPurple,
      ),
    );
  }
}

class AuthFooterBrand extends StatelessWidget {
  const AuthFooterBrand({super.key});

  @override
  Widget build(BuildContext context) {
    return const Padding(
      padding: EdgeInsets.only(bottom: 16, top: 8),
      child: Center(
        child: ExpouseWordmark(
          color: AppColors.brandPurple,
          markSize: 22,
          fontSize: 15,
        ),
      ),
    );
  }
}
