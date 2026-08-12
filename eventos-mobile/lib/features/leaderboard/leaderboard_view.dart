import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import 'package:get_storage/get_storage.dart';

import '../../models/user.dart';
import '../../models/leaderboard_entry_model.dart';
import '../../utils/extension/theme_ext.dart';
import '../../utils/helpers/local_key.dart';
import '../../widgets/custom_image.dart';
import '../../widgets/state_handler/api_state_handler.dart';
import '../../widgets/loading_skeletons/delegate_list_skeleton.dart';
import 'leaderboard_controller.dart';

class LeaderboardView extends StatefulWidget {
  const LeaderboardView({super.key});

  @override
  State<LeaderboardView> createState() => _LeaderboardViewState();
}

class _LeaderboardViewState extends State<LeaderboardView> {
  late final LeaderboardController leaderboardController;

  @override
  void initState() {
    super.initState();
    leaderboardController = Get.find<LeaderboardController>();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      leaderboardController.fetchLeaderboard();
    });
  }

  String _rankIcon(int rank) {
    switch (rank) {
      case 1:
        return 'assets/png/first.png';
      case 2:
        return 'assets/png/second.png';
      case 3:
        return 'assets/png/third.png';
      default:
        return '';
    }
  }

  @override
  Widget build(BuildContext context) {
    final rawUser = GetStorage().read(LocalKeyHelper.userInfo);
    final currentUser = rawUser is Map
        ? User.fromJson(Map<String, dynamic>.from(rawUser))
        : null;

    return Scaffold(
      backgroundColor: context.backgroundColor,
      appBar: AppBar(
        titleSpacing: 0,
        backgroundColor: context.primaryTheme,
        elevation: 0,
        iconTheme: const IconThemeData(color: Colors.white),
        title: Text(
          "Leaderboard",
          style: context.titleLarge?.copyWith(
            color: Colors.white,
            fontWeight: FontWeight.bold,
          ),
        ),
      ),
      body: Obx(() => ApiStateHandler(
        state: leaderboardController.dataStatus.value,
        onRetry: leaderboardController.fetchLeaderboard,
        skeleton: const DelegateListSkeleton(),
        loadedElement: Builder(
          builder: (context) {
            final entries = leaderboardController.leaderboard;

            LeaderboardEntryModel? myEntry;
            for (final entry in entries) {
              if (entry.isMe) {
                myEntry = entry;
                break;
              }
            }
            if (myEntry == null && currentUser != null) {
              for (final entry in entries) {
                if (entry.userName.trim().toLowerCase() ==
                    currentUser.name.trim().toLowerCase()) {
                  myEntry = entry;
                  break;
                }
              }
            }

            final myRank = myEntry?.rank ?? 0;
            final points = myEntry?.points ?? leaderboardController.myPoints.value;
            final myPoints = points > 0 ? "${points}pt" : "0pt";
            final attendeeText = entries.isNotEmpty
                ? "${entries.length} attendees"
                : "0 attendees";

            return RefreshIndicator(
              elevation: 0.5,
              color: Theme.of(context).colorScheme.primary,
              backgroundColor: Theme.of(context).colorScheme.primary.withOpacity(0.1),
              onRefresh: leaderboardController.fetchLeaderboard,
              child: ListView.builder(
                padding: EdgeInsets.symmetric(vertical: 16.h),
                itemCount: entries.length + 1, // +1 for the "You" card after rank 3
                itemBuilder: (context, index) {
                  // Determine item to display
                  if (index < 3) {
                    // Top 3 positions
                    if (index >= entries.length) return const SizedBox.shrink();
                    final entry = entries[index];
                    final isSecond = entry.rank == 2;

                    return _buildRankCard(
                      entry: entry,
                      isSecondPlace: isSecond,
                    );
                  } else if (index == 3) {
                    // "You" Card
                    if (currentUser == null) return const SizedBox.shrink();
                    return Column(
                      children: [
                        _buildCurrentUserCard(
                          currentUser: currentUser,
                          myRank: myRank,
                          myPoints: myPoints,
                          attendeeText: attendeeText,
                        ),
                        Padding(
                          padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 12.h),
                          child: Divider(
                            color: context.strokeLight,
                            thickness: 1.sp,
                          ),
                        ),
                      ],
                    );
                  } else {
                    // Rank 4+ positions
                    final listIndex = index - 1; // subtract 1 for the "You" card
                    if (listIndex >= entries.length) return const SizedBox.shrink();
                    final entry = entries[listIndex];

                    return _buildStandardCard(entry: entry);
                  }
                },
              ),
            );
          },
        ),
      )),
    );
  }

  Widget _buildRankCard({
    required LeaderboardEntryModel entry,
    required bool isSecondPlace,
  }) {
    final rankIconPath = _rankIcon(entry.rank);

    return Container(
      margin: EdgeInsets.symmetric(horizontal: 16.w, vertical: 3.h),
      padding: EdgeInsets.all(12.w),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12.r),
        border: isSecondPlace
            ? null
            : Border.all(color: context.strokeLight, width: 1.sp),
        boxShadow: isSecondPlace
            ? [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.08),
                  blurRadius: 16,
                  spreadRadius: 2,
                  offset: const Offset(0, 4),
                ),
              ]
            : [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.01),
                  blurRadius: 4,
                  offset: const Offset(0, 2),
                ),
              ],
      ),
      child: Row(
        children: [
          CustomImage(
            entry.userPhotoUrl,
            height: 40.sp,
            width: 40.sp,
            radius: 8.r,
            avatar: true,
          ),
          SizedBox(width: 12.w),
          Expanded(
            child: Text(
              entry.userName,
              style: context.titleLarge?.copyWith(
                color: context.heading,
                fontWeight: FontWeight.w600,
              ),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          ),
          SizedBox(width: 12.w),
          SizedBox(
            height: 32.h,
            child: VerticalDivider(
              color: context.strokeLight,
              width: 1.w,
            ),
          ),
          SizedBox(width: 12.w),
          Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              if (rankIconPath.isNotEmpty)
                CustomImage(
                  rankIconPath,
                  height: 22.sp,
                  width: 22.sp,
                ),
              SizedBox(height: 2.h),
              Text(
                "${entry.points}pt",
                style: context.bodyRegular?.copyWith(
                  color: context.caption,
                  fontWeight: FontWeight.w500,
                  fontSize: 12.sp,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildCurrentUserCard({
    required User currentUser,
    required int myRank,
    required String myPoints,
    required String attendeeText,
  }) {
    return Container(
      margin: EdgeInsets.symmetric(horizontal: 16.w, vertical: 6.h),
      padding: EdgeInsets.all(12.w),
      decoration: BoxDecoration(
        color: context.primaryTheme,
        borderRadius: BorderRadius.circular(12.r),
        boxShadow: [
          BoxShadow(
            color: context.primaryTheme.withValues(alpha: 0.2),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Row(
        children: [
          CustomImage(
            currentUser.profilePhotoUrl,
            height: 40.sp,
            width: 40.sp,
            radius: 8.r,
            avatar: true,
          ),
          SizedBox(width: 12.w),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  "You",
                  style: context.titleLarge?.copyWith(
                    color: Colors.white,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                SizedBox(height: 2.h),
                Text(
                  attendeeText,
                  style: context.bodyRegular?.copyWith(
                    color: Colors.white.withValues(alpha: 0.7),
                    fontSize: 12.sp,
                  ),
                ),
              ],
            ),
          ),
          SizedBox(width: 12.w),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                myRank > 0 ? "$myRank" : "-",
                style: context.titleLarge?.copyWith(
                  color: Colors.white,
                  fontWeight: FontWeight.bold,
                ),
              ),
              SizedBox(height: 2.h),
              Text(
                myPoints,
                style: context.bodyRegular?.copyWith(
                  color: Colors.white.withValues(alpha: 0.9),
                  fontSize: 12.sp,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildStandardCard({
    required LeaderboardEntryModel entry,
  }) {
    return Container(
      margin: EdgeInsets.symmetric(horizontal: 16.w, vertical: 3.h),
      padding: EdgeInsets.all(12.w),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12.r),
        border: Border.all(color: context.strokeLight, width: 1.sp),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.01),
            blurRadius: 4,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        children: [
          CustomImage(
            entry.userPhotoUrl,
            height: 40.sp,
            width: 40.sp,
            radius: 8.r,
            avatar: true,
          ),
          SizedBox(width: 12.w),
          Expanded(
            child: Text(
              entry.userName,
              style: context.titleLarge?.copyWith(
                color: context.heading,
                fontWeight: FontWeight.w600,
              ),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          ),
          SizedBox(width: 12.w),
          SizedBox(
            height: 32.h,
            child: VerticalDivider(
              color: context.strokeLight,
              width: 1.w,
            ),
          ),
          SizedBox(width: 12.w),
          Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(
                "${entry.rank}",
                style: context.titleLarge?.copyWith(
                  color: context.caption,
                  fontWeight: FontWeight.w600,
                ),
              ),
              SizedBox(height: 2.h),
              Text(
                "${entry.points}pt",
                style: context.bodyRegular?.copyWith(
                  color: context.caption,
                  fontWeight: FontWeight.w500,
                  fontSize: 12.sp,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
