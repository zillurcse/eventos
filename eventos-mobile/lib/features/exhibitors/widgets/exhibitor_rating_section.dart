import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../../../models/exhibitor_model.dart';
import '../../../utils/extension/theme_ext.dart';

class ExhibitorRatingSection extends StatelessWidget {
  final ExhibitorModel exhibitor;

  const ExhibitorRatingSection({super.key, required this.exhibitor});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        children: [
          Text("Rate Us",
              style: context.titleLarge?.copyWith(
                  color: context.heading,
                  fontWeight: FontWeight.bold)),
          SizedBox(height: 12.h),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: List.generate(5, (index) {
              final rating = exhibitor.review?.rating ?? 0;
              return Icon(
                index < rating ? Icons.star : Icons.star_border,
                color: context.primaryTheme,
                size: 28.sp,
              );
            }),
          )
        ],
      ),
    );
  }
}
