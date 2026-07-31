import 'package:expouse/utils/extension/size_ext.dart';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

import '../../../models/event_feed_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_button.dart';
import '../../../widgets/custom_image.dart';

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
    final title = isLookingFor
        ? '${post.user.name} is looking for'
        : '${post.user.name} is offering';

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
          Text(
            '${post.like} people interested',
            style: context.specialCaption1?.copyWith(color: context.caption),
          ),
          SizedBox(height: 12.h),
          Button.roundedText(
            text: "Interested",
            width: context.width/3,
            onTap: () {},
            backgroundColor: context.primaryFocused,
            onBackgroundColor: context.primaryTheme,
            borderColor: context.primaryTheme,
          ),
        ],
      ),
    );
  }
}
