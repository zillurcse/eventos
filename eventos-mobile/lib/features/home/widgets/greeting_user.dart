import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import 'package:get_storage/get_storage.dart';

import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_image.dart';
import '../../root/root_controller.dart';
import '../home_controller.dart';

class GreetingUser extends StatefulWidget {
  const GreetingUser({super.key});

  @override
  State<GreetingUser> createState() => _GreetingUserState();
}

class _GreetingUserState extends State<GreetingUser> {
  bool _shouldShow = false;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _checkAndShowGreeting();
  }

  void _checkAndShowGreeting() {
    final box = GetStorage();
    final today = DateTime.now().toIso8601String().substring(0, 10);
    final lastShownDate = box.read<String>('lastGreetingDate');

    if (lastShownDate != today) {
      _shouldShow = true;
      box.write('lastGreetingDate', today);
      _timer = Timer(const Duration(seconds: 3), () {
        if (mounted) {
          setState(() {
            _shouldShow = false;
          });
        }
      });
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  String _greeting() {
    final hour = DateTime.now().hour;
    if (hour < 12) return 'Good morning';
    if (hour < 17) return 'Good afternoon';
    if (hour < 20) return 'Good evening';
    return 'Good night';
  }

  @override
  Widget build(BuildContext context) {
    if (!_shouldShow) return const SliverToBoxAdapter(child: SizedBox.shrink());

    final ctrl = Get.find<HomeController>();
    final rootCtrl = Get.find<RootController>();

    return SliverToBoxAdapter(
      child: Container(
        color: context.primaryFocused,
        padding: EdgeInsets.fromLTRB(16.sp, 16.sp, 16.sp, 20.sp),
        child: Obx(() {
          final eventTitle = ctrl.event.title;
          final photo = rootCtrl.profilePhotoUrl.value;
          final displayName = rootCtrl.userName.value;
          final firstName = displayName.split(RegExp(r'\s+')).first;
          return Row(
            children: [
              CustomImage(
                photo,
                radius: 12.r,
                height: 48.sp,
                width: 48.sp,
                avatar: true,
              ),
              SizedBox(width: 12.w),
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    displayName.isNotEmpty
                        ? 'Hello $firstName 👋🏻'
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
