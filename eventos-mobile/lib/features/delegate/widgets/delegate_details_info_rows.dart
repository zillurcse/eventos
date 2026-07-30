import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../../../models/delegate_model.dart';
import '../../../utils/extension/theme_ext.dart';

class DelegateDetailsInfoRows extends StatelessWidget {
  final DelegateDetailModel detail;

  const DelegateDetailsInfoRows({super.key, required this.detail});

  @override
  Widget build(BuildContext context) {
    final rows = <({IconData icon, String label})>[
      if (detail.email != null && detail.email!.isNotEmpty)
        (icon: Icons.email_outlined, label: detail.email!),
      if (detail.mobileNumber != null && detail.mobileNumber!.isNotEmpty)
        (icon: Icons.phone_outlined, label: detail.mobileNumber!),
      if (detail.gender != null && detail.gender!.isNotEmpty)
        (icon: Icons.person_outline, label: detail.gender!),
      if (detail.cityTown != null && detail.cityTown!.isNotEmpty)
        (icon: Icons.location_city_outlined, label: detail.cityTown!),
      if (detail.industry != null && detail.industry!.isNotEmpty)
        (icon: Icons.business_outlined, label: detail.industry!),
      if (detail.interests != null && detail.interests!.isNotEmpty)
        (icon: Icons.interests_outlined, label: detail.interests!),
    ];

    if (rows.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        ...rows.map(
          (e) => Padding(
            padding: EdgeInsets.only(bottom: 8.h),
            child: Row(
              children: [
                Icon(e.icon, size: 16.sp, color: context.primaryTheme),
                SizedBox(width: 8.w),
                Expanded(
                  child: Text(
                    e.label,
                    style: context.bodyRegular?.copyWith(color: context.caption),
                  ),
                ),
              ],
            ),
          ),
        ),
        SizedBox(height: 8.h),
      ],
    );
  }
}
