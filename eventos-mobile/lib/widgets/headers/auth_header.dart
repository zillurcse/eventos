import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

import '../../utils/extension/theme_ext.dart';
import '../../utils/helpers/app_data_provider.dart';
import '../custom_image.dart';

class AuthHeader extends StatelessWidget {
  const AuthHeader({super.key});

  @override
  Widget build(BuildContext context) {
    final iconUrl = AppDataProvider.obj.mobileBranding?.brand.iconUrl;
    final logoSrc = (iconUrl != null && iconUrl.isNotEmpty)
        ? iconUrl
        : 'assets/svg/img/logo.svg';

    return Container(
      width: double.infinity,
      color: context.primaryTheme,
      alignment: Alignment.center,
      padding: EdgeInsets.only(
        top: MediaQuery.of(context).viewPadding.top + 4.h,
        bottom: 16.sp,
      ),
      child: CustomImage(
        logoSrc,
        height: 26.h,
      ),
    );
  }
}
