import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import 'package:get_storage/get_storage.dart';

import '../../../models/user.dart';
import '../../../utils/enum/enums.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../utils/helpers/local_key.dart';
import '../../../widgets/custom_image.dart';
import '../pages/create_post_view.dart';
import 'type_chip.dart';

class CreatePostWidget extends StatelessWidget {
  const CreatePostWidget({super.key});

  // Chip config - index maps to the PostType it opens
  static const _chips = [
    {'icon': 'assets/svg/icons/img.svg',      'type': PostTypes.image},
    {'icon': 'assets/svg/icons/video.svg',     'type': PostTypes.video},
    {'icon': 'assets/svg/icons/add_note.svg',  'type': PostTypes.pdf},
    {'icon': 'assets/svg/icons/poll.svg',      'type': PostTypes.poll},
    {'icon': 'assets/svg/icons/look_for.svg',  'type': PostTypes.lookingFor},
    {'icon': 'assets/svg/icons/book.svg',      'type': PostTypes.offering},
  ];

  User? _currentUser() {
    final raw = GetStorage().read(LocalKeyHelper.userInfo);
    if (raw is Map) return User.fromJson(Map<String, dynamic>.from(raw));
    return null;
  }

  void _navigate(PostTypes type) {
    Get.to(() => CreatePostView(initialType: type));
  }

  @override
  Widget build(BuildContext context) {
    final user = _currentUser();

    return SliverToBoxAdapter(
      child: Container(
        margin: EdgeInsets.fromLTRB(16.w, 0, 16.w, 12.h),
        padding: EdgeInsets.fromLTRB(16.w, 16.h, 16.w, 16.h),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(8.r),
          color: context.tertiaryText,
          border: Border.all(color: context.strokeLight),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // ── Avatar + tap-to-create ────────────────────────────────────
            GestureDetector(
              behavior: HitTestBehavior.opaque,
              onTap: () => _navigate(PostTypes.post),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  CustomImage(
                    user?.profilePhotoUrl ?? '',
                    fit: BoxFit.cover,
                    height: 38.sp,
                    width: 38.sp,
                    radius: 8.r,
                    avatar: true,
                  ),
                  SizedBox(width: 8.w),
                  Expanded(
                    child: Text(
                      "Got a spark of an idea? Let the community feel your energy!",
                      style: context.titleRegular?.copyWith(
                        color: context.ghost,
                      ),
                      maxLines: 2,
                    ),
                  ),
                ],
              ),
            ),

            Divider(height: 30.h),

            // ── Post-type chips ────────────────────────────────────────────
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: _chips.map((chip) {
                final type = chip['type'] as PostTypes;
                final icon = chip['icon'] as String;
                return TypeChip(
                  iconUrl: icon,
                  onTap: () => _navigate(type),
                );
              }).toList(),
            ),
          ],
        ),
      ),
    );
  }
}

