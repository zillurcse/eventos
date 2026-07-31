import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

import '../../../models/contest_model.dart';
import '../../../utils/extension/theme_ext.dart';

class ContestCountdownBoxes extends StatefulWidget {
  final String? targetIso;

  const ContestCountdownBoxes({super.key, required this.targetIso});

  @override
  State<ContestCountdownBoxes> createState() => _ContestCountdownBoxesState();
}

class _ContestCountdownBoxesState extends State<ContestCountdownBoxes> {
  Timer? _timer;
  ContestCountdown? _countdown;

  @override
  void initState() {
    super.initState();
    _tick();
    _timer = Timer.periodic(const Duration(seconds: 30), (_) => _tick());
  }

  @override
  void didUpdateWidget(covariant ContestCountdownBoxes oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.targetIso != widget.targetIso) _tick();
  }

  void _tick() {
    final next = ContestCountdown.fromIso(widget.targetIso);
    if (!mounted) return;
    setState(() => _countdown = next);
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final c = _countdown;
    if (c == null) return const SizedBox.shrink();

    return Row(
      children: [
        Expanded(child: _box(context, '${c.days}', 'days')),
        SizedBox(width: 8.w),
        Expanded(child: _box(context, '${c.hours}', 'hours')),
        SizedBox(width: 8.w),
        Expanded(child: _box(context, '${c.mins}', 'mins')),
      ],
    );
  }

  Widget _box(BuildContext context, String value, String label) {
    return Container(
      padding: EdgeInsets.symmetric(vertical: 8.h, horizontal: 6.w),
      decoration: BoxDecoration(
        border: Border.all(color: const Color(0xFFE2E8F0)),
        borderRadius: BorderRadius.circular(8.r),
      ),
      child: Column(
        children: [
          Text(
            value,
            style: context.titleRegular?.copyWith(
              fontWeight: FontWeight.w600,
              color: const Color(0xFF1E293B),
              fontSize: 16.sp,
            ),
          ),
          SizedBox(height: 2.h),
          Text(
            label,
            style: context.bodyRegular?.copyWith(
              color: const Color(0xFF94A3B8),
              fontSize: 12.sp,
            ),
          ),
        ],
      ),
    );
  }
}
