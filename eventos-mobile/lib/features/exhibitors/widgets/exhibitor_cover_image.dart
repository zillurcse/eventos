import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../../../widgets/custom_image.dart';
import '../../../models/exhibitor_model.dart';

class ExhibitorCoverImage extends StatelessWidget {
  final ExhibitorModel exhibitor;

  const ExhibitorCoverImage({super.key, required this.exhibitor});

  @override
  Widget build(BuildContext context) {
    return CustomImage(
      exhibitor.spotlightBannerUrl.isNotEmpty
          ? exhibitor.spotlightBannerUrl
          : "https://via.placeholder.com/800x400.png?text=Exhibitor+Cover", // Fallback
      height: 150.h,
      width: double.infinity,
      fit: BoxFit.cover,
    );
  }
}
