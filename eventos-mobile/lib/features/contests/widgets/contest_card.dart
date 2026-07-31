import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

import '../../../models/contest_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_image.dart';
import 'contest_countdown.dart';

class ContestCard extends StatelessWidget {
  final Contest contest;
  final VoidCallback onTap;

  const ContestCard({
    super.key,
    required this.contest,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final hasWinner = contest.winners.isNotEmpty;

    return GestureDetector(
      onTap: onTap,
      child: Container(
        margin: EdgeInsets.only(bottom: 14.h),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12.r),
          border: Border.all(color: const Color(0xFFE8ECF1)),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.04),
              blurRadius: 8,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        clipBehavior: Clip.antiAlias,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AspectRatio(
              aspectRatio: 16 / 9,
              child: (contest.bannerUrl?.isNotEmpty ?? false)
                  ? CustomImage(
                      contest.bannerUrl!,
                      fit: BoxFit.cover,
                      width: double.infinity,
                      height: double.infinity,
                    )
                  : ColoredBox(
                      color: const Color(0xFFEEF0F3),
                      child: Center(
                        child: Icon(
                          Icons.emoji_events_outlined,
                          size: 40.sp,
                          color: context.ghost,
                        ),
                      ),
                    ),
            ),
            Padding(
              padding: EdgeInsets.fromLTRB(16.w, 14.h, 16.w, 16.h),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    contest.title,
                    style: context.h2?.copyWith(
                      fontWeight: FontWeight.w800,
                      color: const Color(0xFF1E293B),
                      fontSize: 17.sp,
                      height: 1.3,
                    ),
                  ),
                  if (contest.canSeeOthersEntries) ...[
                    SizedBox(height: 4.h),
                    Text(
                      '${contest.entryCount} ${contest.entryCount == 1 ? 'Entry' : 'Entries'}',
                      style: context.bodyRegular?.copyWith(
                        color: const Color(0xFF94A3B8),
                        fontSize: 13.sp,
                      ),
                    ),
                  ],
                  SizedBox(height: 14.h),
                  Text(
                    contest.statusLabel,
                    style: context.bodyRegular?.copyWith(
                      color: const Color(0xFF94A3B8),
                      fontSize: 13.sp,
                    ),
                  ),
                  SizedBox(height: 8.h),
                  if (!contest.isEnded)
                    ContestCountdownBoxes(targetIso: contest.countdownTarget)
                  else
                    _endedPill(context, hasWinner),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _endedPill(BuildContext context, bool hasWinner) {
    return Container(
      width: double.infinity,
      padding: EdgeInsets.symmetric(horizontal: 14.w, vertical: 11.h),
      decoration: BoxDecoration(
        color: context.primaryFocused,
        borderRadius: BorderRadius.circular(8.r),
      ),
      child: Row(
        children: [
          Icon(
            Icons.timer_outlined,
            size: 20.sp,
            color: context.primaryTheme,
          ),
          SizedBox(width: 10.w),
          Expanded(
            child: Text(
              hasWinner
                  ? 'Winner has been announced'
                  : 'Winner has to be announced',
              style: context.bodyRegular?.copyWith(
                color: const Color(0xFF334155),
                fontWeight: FontWeight.w600,
                fontSize: 13.sp,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
