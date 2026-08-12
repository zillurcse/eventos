import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';
import 'package:expouse/utils/extension/theme_ext.dart';
import '../../features/root/root_controller.dart';

class MoreBottomSheet extends StatelessWidget {
  const MoreBottomSheet({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<RootController>();
    
    return Obx(() {
      final tabs = controller.activeTabs;
      if (tabs.length <= 4) {
        return const SizedBox.shrink();
      }

      final moreTabs = tabs.sublist(4);

      return Column(
        spacing: 12.h,
        children: moreTabs.asMap().entries.map((entry) {
          final tabIndex = entry.key + 4; // Absolute index in activeTabs
          final tab = entry.value;

          return GestureDetector(
            onTap: () {
              Get.back();
              controller.changeIndex(tabIndex);
            },
            child: Container(
              color: Colors.transparent,
              child: Row(
                children: [
                  Builder(
                    builder: (context) {
                      double iconSize = 20.sp;
                      if (tab.icon == 'delegates.svg') {
                        iconSize = 15.sp;
                      } else if (tab.icon == 'exhibitors.svg' || tab.icon == 'badges.svg') {
                        iconSize = 17.sp;
                      }
                      
                      return SizedBox(
                        width: 32.sp,
                        height: 32.sp,
                        child: Center(
                          child: SvgPicture.asset(
                            "assets/svg/icons/${tab.icon}", 
                            height: iconSize, 
                            width: iconSize,
                          ),
                        ),
                      );
                    },
                  ),
                  SizedBox(width: 12.w),
                  Expanded(
                    child: Text(
                      tab.customName, 
                      style: context.titleRegular,
                    ),
                  ),
                ],
              ),
            ),
          );
        }).toList(),
      );
    });
  }
}
