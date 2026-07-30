import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import 'package:get_storage/get_storage.dart';

import '../../../models/user.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../utils/helpers/local_key.dart';
import '../../../widgets/cards/leaderboard_card.dart';
import '../../../widgets/custom_button.dart';
import '../../../widgets/custom_image.dart';
import '../home_controller.dart';
import '../../leaderboard/leaderboard_view.dart';

class Leaderboard extends StatelessWidget {
  const Leaderboard({super.key});

  String _rankIcon(int rank) {
    switch (rank) {
      case 1:
        return 'assets/png/first.png';
      case 2:
        return 'assets/png/second.png';
      case 3:
        return 'assets/png/third.png';
      default:
        return 'assets/png/third.png';
    }
  }

  @override
  Widget build(BuildContext context) {
    final ctrl = Get.find<HomeController>();
    final rawUser = GetStorage().read(LocalKeyHelper.userInfo);
    final currentUser = rawUser is Map
        ? User.fromJson(Map<String, dynamic>.from(rawUser))
        : null;

    return Obx(() {
      final entries = ctrl.leaderboard;
      // Need at least 3 entries with ranks 1, 2, 3 present to render the podium.
      if (entries.length < 3) return const SliverToBoxAdapter(child: SizedBox.shrink());
      final hasAllRanks = [1, 2, 3].every((r) => entries.any((e) => e.rank == r));
      if (!hasAllRanks) return const SliverToBoxAdapter(child: SizedBox.shrink());

      // Show top 3 in the podium cards; rank is set during parse
      final top3 = entries.take(3).toList();

      return SliverToBoxAdapter(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: EdgeInsets.symmetric(horizontal: 16.w),
              child: Text("Leaderboard", style: context.h3),
            ),
            SizedBox(height: 12.h),
            Column(
              children: [
                top3.firstWhere((e) => e.rank == 2),
                top3.firstWhere((e) => e.rank == 1),
                top3.firstWhere((e) => e.rank == 3),
              ].map((entry) {
                return LeaderboardCard(
                  name: entry.userName,
                  points: '${entry.points}pt',
                  avatarUrl: entry.userPhotoUrl,
                  rankIcon: _rankIcon(entry.rank),
                  isFirstPlace: entry.rank == 1,
                );
              }).toList(),
            ),
            SizedBox(height: 12.h),
            // Current user's own row
            if (currentUser != null)
              Container(
                margin: EdgeInsets.symmetric(horizontal: 16.sp),
                padding: EdgeInsets.all(12.sp),
                decoration: BoxDecoration(
                  color: context.primaryTheme,
                  borderRadius: BorderRadius.circular(12.r),
                ),
                child: Row(
                  children: [
                    CustomImage(
                      currentUser.profilePhotoUrl,
                      height: 40.sp,
                      width: 40.sp,
                      radius: 8.r,
                    ),
                    SizedBox(width: 12.w),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            "You",
                            style: context.titleLarge
                                ?.copyWith(color: context.tertiaryText),
                          ),
                          Text(
                            currentUser.name,
                            style: context.specialCaption1
                                ?.copyWith(color: context.primaryFocused),
                          ),
                        ],
                      ),
                    ),
                    SizedBox(width: 12.w),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Text(
                          "#${entries.length}+",
                          style: context.buttonSmallBold
                              ?.copyWith(color: context.tertiaryText),
                        ),
                        SizedBox(height: 2.h),
                        Text(
                          "—",
                          style: context.bodyRegular
                              ?.copyWith(color: context.tertiaryText),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            SizedBox(height: 12.h),
            Padding(
              padding: EdgeInsets.symmetric(horizontal: 16.w),
              child: Button.roundedText(
                text: "View entire leaderboard",
                style: context.buttonMediumBold
                    ?.copyWith(color: context.primaryTheme),
                backgroundColor: context.primaryFocused,
                borderColor: context.primaryTheme,
                onTap: () {
                  Get.to(() => const LeaderboardView());
                },
              ),
            ),
            SizedBox(height: 16.h),
          ],
        ),
      );
    });
  }
}