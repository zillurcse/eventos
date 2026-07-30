import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../../../models/delegate_model.dart';
import '../../../utils/extension/theme_ext.dart';

class DelegateAboutSection extends StatelessWidget {
  final DelegateDetailModel detail;

  const DelegateAboutSection({super.key, required this.detail});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('About', style: context.h3),
        SizedBox(height: 8.h),
        Text(
          (detail.about != null &&
                  detail.about!.isNotEmpty &&
                  detail.about != 'NA')
              ? detail.about!
              : 'No additional information available.',
          style: context.bodyRegular?.copyWith(color: context.caption),
        ),
      ],
    );
  }
}
