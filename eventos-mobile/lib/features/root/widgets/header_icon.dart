import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../../../widgets/custom_image.dart';

/// Stateless icon button used in the header action row.
class HeaderIcon extends StatelessWidget {
  final String asset;
  final double height;

  const HeaderIcon(this.asset, {super.key, required this.height});

  @override
  Widget build(BuildContext context) {
    return Container(
      color: Colors.transparent,
      padding: EdgeInsets.only(left: 16.w, top: 20.sp),
      child: CustomImage(asset, height: height),
    );
  }
}
