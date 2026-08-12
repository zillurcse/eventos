import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:expouse/utils/config/app_config.dart';
import '../../../widgets/custom_image.dart';
import '../../../models/session_detail_response_model.dart';

class SessionCoverImage extends StatelessWidget {
  final SessionDetailModel detail;

  const SessionCoverImage({super.key, required this.detail});

  @override
  Widget build(BuildContext context) {
    return CustomImage(
      detail.logo.isNotEmpty
          ? AppConfig.resolveAssetUrl(detail.logo)
          : "https://via.placeholder.com/800x400.png?text=Session+Cover",
      height: 180.h,
      width: double.infinity,
      fit: BoxFit.cover,
    );
  }
}
