import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

import '../../../models/contest_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_image.dart';

class ContestWinnersSection extends StatelessWidget {
  final Contest contest;
  final List<ContestEntry> winners;

  const ContestWinnersSection({
    super.key,
    required this.contest,
    required this.winners,
  });

  static const _medals = ['🥇', '🥈', '🥉'];

  @override
  Widget build(BuildContext context) {
    if (winners.isEmpty) return const SizedBox.shrink();

    final chooser = contest.winnerChooser == 'most_likes'
        ? 'Chosen by the most likes'
        : 'Chosen by the organizer';
    final pts = contest.winningPoints > 0
        ? ' · ${contest.winningPoints} pts'
        : '';

    return Container(
      margin: EdgeInsets.only(bottom: 16.h),
      padding: EdgeInsets.all(16.w),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16.r),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 6,
            offset: const Offset(0, 1),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Winners',
            style: context.h2?.copyWith(
              fontWeight: FontWeight.w800,
              fontSize: 16.sp,
              color: const Color(0xFF1E293B),
            ),
          ),
          SizedBox(height: 3.h),
          Text(
            '$chooser$pts',
            style: context.bodyRegular?.copyWith(
              color: const Color(0xFF94A3B8),
              fontSize: 12.sp,
            ),
          ),
          SizedBox(height: 12.h),
          ...List.generate(winners.length, (i) {
            final w = winners[i];
            return Container(
              margin: EdgeInsets.only(bottom: 10.h),
              padding: EdgeInsets.symmetric(horizontal: 12.w, vertical: 10.h),
              decoration: BoxDecoration(
                color: const Color(0xFFF8FAFC),
                borderRadius: BorderRadius.circular(12.r),
              ),
              child: Row(
                children: [
                  SizedBox(
                    width: 26.w,
                    child: Text(
                      i < _medals.length ? _medals[i] : '#${i + 1}',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 16.sp,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  SizedBox(width: 8.w),
                  ClipOval(
                    child: SizedBox(
                      width: 38.sp,
                      height: 38.sp,
                      child: (w.authorAvatar?.isNotEmpty ?? false)
                          ? CustomImage(
                              w.authorAvatar!,
                              fit: BoxFit.cover,
                              width: 38.sp,
                              height: 38.sp,
                            )
                          : ColoredBox(
                              color: const Color(0xFFE2E8F0),
                              child: Icon(Icons.person,
                                  size: 20.sp, color: context.ghost),
                            ),
                    ),
                  ),
                  SizedBox(width: 10.w),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          w.author,
                          style: context.bodyRegular?.copyWith(
                            fontWeight: FontWeight.w700,
                            color: const Color(0xFF1E293B),
                          ),
                          overflow: TextOverflow.ellipsis,
                        ),
                        if (w.body?.isNotEmpty ?? false)
                          Text(
                            w.body!,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: context.bodyRegular?.copyWith(
                              color: const Color(0xFF64748B),
                              fontSize: 12.sp,
                            ),
                          ),
                      ],
                    ),
                  ),
                  Text(
                    '${w.likeCount} ♥',
                    style: TextStyle(
                      color: const Color(0xFFE11D48),
                      fontSize: 12.sp,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  if (w.attachments.isNotEmpty) ...[
                    SizedBox(width: 8.w),
                    ClipRRect(
                      borderRadius: BorderRadius.circular(10.r),
                      child: SizedBox(
                        width: 48.sp,
                        height: 48.sp,
                        child: w.attachments.first.isVideo
                            ? ColoredBox(
                                color: const Color(0xFF0F172A),
                                child: Icon(Icons.play_arrow,
                                    color: Colors.white, size: 22.sp),
                              )
                            : CustomImage(
                                w.attachments.first.url,
                                fit: BoxFit.cover,
                                width: 48.sp,
                                height: 48.sp,
                              ),
                      ),
                    ),
                  ],
                ],
              ),
            );
          }),
        ],
      ),
    );
  }
}
