import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import 'package:get_storage/get_storage.dart';

import '../../../models/leaderboard_entry_model.dart';
import '../../../models/user.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../utils/helpers/local_key.dart';
import '../../../widgets/cards/leaderboard_card.dart';
import '../../../widgets/custom_button.dart';
import '../../../widgets/custom_image.dart';
import '../home_controller.dart';
import '../../leaderboard/leaderboard_view.dart';
import '../../root/root_controller.dart';

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

  /// Podium order: 2nd, 1st (highlighted), 3rd - only slots that exist.
  List<LeaderboardEntryModel> _podium(List<LeaderboardEntryModel> entries) {
    final byRank = <int, LeaderboardEntryModel>{};
    for (final e in entries) {
      byRank.putIfAbsent(e.rank, () => e);
    }
    final ordered = [2, 1, 3]
        .where(byRank.containsKey)
        .map((r) => byRank[r]!)
        .toList();
    if (ordered.isNotEmpty) return ordered;
    return entries.take(3).toList();
  }

  @override
  Widget build(BuildContext context) {
    final ctrl = Get.find<HomeController>();
    final rootCtrl = Get.find<RootController>();
    final rawUser = GetStorage().read(LocalKeyHelper.userInfo);
    final currentUser = rawUser is Map
        ? User.fromJson(Map<String, dynamic>.from(rawUser))
        : null;

    return Obx(() {
      // Respect Navigation › Modules › Leaderboard when the organizer turned it off.
      final moduleOn = rootCtrl.themeModules.isEmpty ||
          rootCtrl.themeModules.contains('leaderboard');
      if (!moduleOn) {
        return const SliverToBoxAdapter(child: SizedBox.shrink());
      }

      final entries = ctrl.leaderboard;
      // Need at least one ranked participant; also respect gamification toggle.
      if (!ctrl.leaderboardEnabled.value || entries.isEmpty) {
        return const SliverToBoxAdapter(child: SizedBox.shrink());
      }

      final podium = _podium(entries);

      LeaderboardEntryModel? myEntry;
      for (final entry in entries) {
        if (entry.isMe) {
          myEntry = entry;
          break;
        }
      }

      final myRank = myEntry?.rank ?? 0;
      final myPoints = myEntry?.points ?? ctrl.myPoints.value;

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
              children: podium.map((entry) {
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
                          myRank > 0 ? "#$myRank" : "-",
                          style: context.buttonSmallBold
                              ?.copyWith(color: context.tertiaryText),
                        ),
                        SizedBox(height: 2.h),
                        Text(
                          myPoints > 0 ? "${myPoints}pt" : "0pt",
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
