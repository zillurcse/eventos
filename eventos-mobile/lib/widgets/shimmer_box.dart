import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../utils/extension/theme_ext.dart';

class ShimmerBox extends StatefulWidget {
  final double width;
  final double height;
  final double topRadius;

  const ShimmerBox({
    super.key,
    required this.width,
    required this.height,
    this.topRadius = 4,
  });

  @override
  State<ShimmerBox> createState() => _ShimmerBoxState();
}

class _ShimmerBoxState extends State<ShimmerBox>
    with SingleTickerProviderStateMixin {
  late final AnimationController _ctrl;
  late final Animation<double> _anim;

  @override
  void initState() {
    super.initState();
    _ctrl = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1200),
    )..repeat();
    _anim = Tween<double>(begin: -1.5, end: 1.5).animate(
      CurvedAnimation(parent: _ctrl, curve: Curves.easeInOut),
    );
  }

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final base = context.strokeLight;
    return AnimatedBuilder(
      animation: _anim,
      builder: (_, __) => Container(
        width: widget.width,
        height: widget.height,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.only(
            topLeft: Radius.circular(widget.topRadius),
            topRight: Radius.circular(widget.topRadius),
            bottomLeft: Radius.circular(4.r),
            bottomRight: Radius.circular(4.r),
          ),
          gradient: LinearGradient(
            begin: Alignment(_anim.value - 1, 0),
            end: Alignment(_anim.value, 0),
            colors: [
              base,
              base.withValues(alpha: 0.4),
              base,
            ],
          ),
        ),
      ),
    );
  }
}
