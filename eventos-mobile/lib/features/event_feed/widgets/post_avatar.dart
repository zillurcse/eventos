import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../../../../models/user.dart';
import '../../../../widgets/custom_image.dart';

class PostAvatar extends StatelessWidget {
  final User? user;
  final double? size;
  const PostAvatar({super.key, this.user, this.size});

  @override
  Widget build(BuildContext context) {
    return CustomImage(
      user?.profilePhotoUrl ?? '',
      fit: BoxFit.cover,
      height: size ?? 40.sp,
      width: size ?? 40.sp,
      radius: 8.r,
      avatar: true,
    );
  }
}
