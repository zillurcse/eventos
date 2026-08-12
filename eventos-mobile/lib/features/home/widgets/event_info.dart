import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_image.dart';
import '../home_controller.dart';
import 'image_slider.dart';

class EventInfo extends StatelessWidget {
  const EventInfo({super.key});

  // Strip HTML tags for plain-text rendering
  String _stripHtml(String html) =>
      html.replaceAll(RegExp(r'<[^>]*>'), '').trim();

  @override
  Widget build(BuildContext context) {
    final ctrl = Get.find<HomeController>();
    final seeDetails = false.obs;

    return Obx(() {
      final event = ctrl.event;
      // Vanish entirely if the API returned no event data yet
      if (event.title.isEmpty) return const SliverToBoxAdapter(child: SizedBox.shrink());

      final rawDesc = _stripHtml(event.description);
      final location = [
        event.address1,
        event.city,
        event.country,
      ].where((s) => s.isNotEmpty).join(', ');
      final dateTime = [
        event.formatedDate,
        event.formatedTime,
      ].where((s) => s.isNotEmpty).join(' | ');
      final banners = ctrl.banners;

      return SliverToBoxAdapter(
        child: Container(
          color: context.tertiaryText,
          child: Column(
            children: [
              Padding(
                padding: EdgeInsets.only(
                  left: 16.sp, right: 16.sp, top: 16.sp, bottom: 4.sp,
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  spacing: 12.h,
                  children: [
                    Text(
                      event.title.isNotEmpty ? event.title : '-',
                      maxLines: 2,
                      style: context.h5?.copyWith(color: context.heading),
                    ),
                    if (dateTime.isNotEmpty)
                      Row(
                        children: [
                          CustomImage(
                            "assets/svg/icons/calender.svg",
                            height: 18.sp,
                            color: context.caption,
                          ),
                          SizedBox(width: 8.w),
                          Expanded(
                            child: Text(
                              dateTime,
                              style: context.specialCaption2?.copyWith(
                                color: context.caption,
                              ),
                            ),
                          ),
                        ],
                      ),
                    if (location.isNotEmpty)
                      Row(
                        children: [
                          CustomImage(
                            "assets/svg/icons/map.svg",
                            height: 18.sp,
                            color: context.caption,
                          ),
                          SizedBox(width: 8.w),
                          Expanded(
                            child: Text(
                              location,
                              style: context.specialCaption2?.copyWith(
                                color: context.caption,
                              ),
                            ),
                          ),
                        ],
                      ),
                    if (rawDesc.isNotEmpty)
                      Obx(
                        () => Text(
                          seeDetails.value
                              ? rawDesc
                              : rawDesc.length > 180
                              ? "${rawDesc.substring(0, 180)} ..."
                              : rawDesc,
                          style: context.bodyRegular,
                        ),
                      ),

                    Obx(() {
                      if(seeDetails.value) {
                        return Row(
                          spacing: 8.w,
                          mainAxisSize: MainAxisSize.min,
                          mainAxisAlignment: MainAxisAlignment.start,
                          children: [
                          event.socialLinks.twitterLink.isNotEmpty ? CustomImage("assets/png/twiter.png", height: 40.sp, fit: BoxFit.fill,) : SizedBox.shrink(),
                          event.socialLinks.instagramLink.isNotEmpty ? CustomImage("assets/png/insta.png", height: 40.sp, fit: BoxFit.fill,) : SizedBox.shrink(),
                          event.socialLinks.linkedinLink.isNotEmpty ? CustomImage("assets/png/in.png", height: 40.sp, fit: BoxFit.fill,) : SizedBox.shrink(),
                          event.socialLinks.facebookLink.isNotEmpty ? CustomImage("assets/png/fb.png", height: 40.sp, fit: BoxFit.fill,) : SizedBox.shrink(),
                          ],
                        );
                      } else {
                        return SizedBox.shrink();
                      }
                    })
                  ],
                ),
              ),
              // Community banners as the image slider
              ImageSlider(
                height: MediaQuery.sizeOf(context).height * .16,
                imageUrls: banners.map((b) => b.imageUrl).toList(),
              ),
              SizedBox(height: 6.h),
              if (rawDesc.length > 180)
                Obx(
                  () => GestureDetector(
                    onTap: seeDetails.toggle,
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.start,
                      children: [
                        SizedBox(width: 16.w),
                        Text(
                          seeDetails.value ? "Less Details" : "More Details",
                          style: context.bodyRegular?.copyWith(
                            color: context.primaryTheme,
                          ),
                        ),
                        SizedBox(width: 4.w),
                        Icon(
                          seeDetails.value
                              ? Icons.keyboard_arrow_up_outlined
                              : Icons.keyboard_arrow_down_outlined,
                          color: context.primaryTheme,
                        ),
                      ],
                    ),
                  ),
                ),
              SizedBox(height: 20.h),
            ],
          ),
        ),
      );
    });
  }
}
