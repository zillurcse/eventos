import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../../../../utils/extension/theme_ext.dart';

class SpeakerInfoRow extends StatelessWidget {
  final IconData icon;
  final String label;
  final BuildContext context;

  const SpeakerInfoRow({
    super.key,
    required this.icon,
    required this.label,
    required this.context,
  });

  @override
  Widget build(BuildContext ctx) {
    return Padding(
      padding: EdgeInsets.only(bottom: 8.h),
      child: Row(
        children: [
          Icon(icon, size: 16.sp, color: context.primaryTheme),
          SizedBox(width: 8.w),
          Expanded(
            child: Text(
              label,
              style: ctx.bodyRegular?.copyWith(color: ctx.caption),
            ),
          ),
        ],
      ),
    );
  }
}
