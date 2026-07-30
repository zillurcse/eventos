import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

import '../../../utils/theme/app_colors.dart';

/// Animated shimmer skeleton shown under a nav icon while the tab is loading.
class NavSkeletonDot extends StatefulWidget {
  const NavSkeletonDot({super.key});

  @override
  State<NavSkeletonDot> createState() => _NavSkeletonDotState();
}

class _NavSkeletonDotState extends State<NavSkeletonDot>
    with SingleTickerProviderStateMixin {
  late final AnimationController _anim;
  late final Animation<double> _opacity;

  @override
  void initState() {
    super.initState();
    _anim = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 700),
    )..repeat(reverse: true);
    _opacity = Tween<double>(begin: 0.3, end: 1.0).animate(
      CurvedAnimation(parent: _anim, curve: Curves.easeInOut),
    );
  }

  @override
  void dispose() {
    _anim.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return FadeTransition(
      opacity: _opacity,
      child: Container(
        height: 6.h,
        width: 28.w,
        decoration: BoxDecoration(
          color: ghost.withValues(alpha: .4),
          borderRadius: BorderRadius.circular(3.r),
        ),
      ),
    );
  }
}
