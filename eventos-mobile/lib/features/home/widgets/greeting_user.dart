import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import 'package:get_storage/get_storage.dart';

import '../../../models/user.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../utils/helpers/local_key.dart';
import '../../../widgets/custom_image.dart';
import '../home_controller.dart';

class GreetingUser extends StatelessWidget {
  const GreetingUser({super.key});

  String _greeting() {
    final hour = DateTime.now().hour;
    if (hour < 12) return 'Good morning';
    if (hour < 17) return 'Good afternoon';
    return 'Good evening';
  }

  @override
  Widget build(BuildContext context) {
    final rawUser = GetStorage().read(LocalKeyHelper.userInfo);
    final user = rawUser is Map
        ? User.fromJson(Map<String, dynamic>.from(rawUser))
        : null;

    // Also pull the event title from the controller for the welcome sub-line
    final ctrl = Get.find<HomeController>();

    return SliverToBoxAdapter(
      child: Container(
        color: context.primaryFocused,
        padding: EdgeInsets.fromLTRB(16.sp, 16.sp, 16.sp, 20.sp),
        child: Obx(() {
          final eventTitle = ctrl.event.title;
          return Row(
            children: [
              CustomImage(
                user?.profilePhotoUrl ?? '',
                radius: 12.r,
                height: 48.sp,
                width: 48.sp,
              ),
              SizedBox(width: 12.w),
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    user != null
                        ? 'Hello ${user.firstName.isNotEmpty ? user.firstName : user.name} 👋🏻'
                        : 'Hello 👋🏻',
                    style: context.h5?.copyWith(color: context.heading),
                  ),
                  Text(
                    '${_greeting()} &\nWelcome${eventTitle.isNotEmpty ? ' to $eventTitle' : ' to the event'}',
                    style: context.specialCaption1
                        ?.copyWith(color: context.caption),
                  ),
                ],
              ),
            ],
          );
        }),
      ),
    );
  }
}