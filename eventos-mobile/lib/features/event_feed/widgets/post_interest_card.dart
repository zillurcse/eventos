import 'package:expouse/utils/extension/size_ext.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get_core/src/get_main.dart';
import 'package:get/get_instance/src/extension_instance.dart';
import 'package:get/get_navigation/src/root/parse_route.dart';
import 'package:get/get_state_manager/src/rx_flutter/rx_obx_widget.dart';

import '../../../models/event_feed_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_button.dart';
import '../../../widgets/custom_image.dart';
import '../event_feed_controller.dart';

class PostInterestCard extends StatelessWidget {
  final FeedPostModel post;
  final bool isLookingFor;
  const PostInterestCard({
    super.key,
    required this.post,
    required this.isLookingFor,
  });

  @override
  Widget build(BuildContext context) {
    final ctrl = Get.find<EventFeedController>();
    final title = isLookingFor
        ? '${post.user.name} is looking for'
        : '${post.user.name} is offering';

    return Obx(() {
      final live =
          ctrl.posts.firstWhereOrNull((p) => p.id == post.id) ?? post;
      final count = live.like;
      final interested = live.isLiked;

      return Container(
        width: double.infinity,
        padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 20.h),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            CustomImage(
              post.user.profilePhotoUrl,
              width: 80.w,
              height: 80.w,
              radius: 12.r,
              fit: BoxFit.cover,
              avatar: true,
            ),
            SizedBox(height: 14.h),
            Text(title, style: context.h2, textAlign: TextAlign.center),
            SizedBox(height: 10.h),
            if (post.body != null && post.body!.isNotEmpty)
              Text(
                post.body!,
                style: context.bodyRegular,
                textAlign: TextAlign.center,
              ),
            SizedBox(height: 14.h),
            Divider(color: context.strokeLight, thickness: 1),
            SizedBox(height: 10.h),
            Button.roundedText(
              text: interested ? "You're interested" : 'Interested',
              width: context.width / 2.6,
              onTap: () {
                HapticFeedback.lightImpact();
                ctrl.toggleLike(post.id);
              },
              backgroundColor: interested
                  ? context.primaryTheme
                  : context.primaryFocused,
              onBackgroundColor:
                  interested ? Colors.white : context.primaryTheme,
              borderColor: context.primaryTheme,
            ),
          ],
        ),
      );
    });
  }
}
