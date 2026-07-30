import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

import '../../../models/event_feed_model.dart';
import '../../../utils/extension/theme_ext.dart';

class PollOption extends StatelessWidget {
  final FeedPollOptionModel option;
  final double percentage;
  final bool isMyVote;
  final bool isVoted;
  final bool isLive;
  final bool showResults;

  /// True while the parent is waiting for a vote API response.
  /// Disables taps on all options while in-flight.
  final bool isVoting;

  final void Function(FeedPollOptionModel option)? onVote;

  const PollOption({
    super.key,
    required this.option,
    required this.percentage,
    required this.isMyVote,
    required this.isVoted,
    required this.isLive,
    required this.showResults,
    this.isVoting = false,
    this.onVote,
  });

  @override
  Widget build(BuildContext context) {
    final isEnded = !isLive;

    // Show result bar when: live+voted OR ended+showResults
    final revealResults = (isLive && isVoted) || (isEnded && showResults);
    final pctLabel = '${(percentage * 100).toStringAsFixed(0)}%';

    // ── Color scheme ─────────────────────────────────────────────────────────
    final Color borderColor;
    final double borderWidth;
    final Color bgColor;
    final Color textColor;
    final Color radioColor;

    if (isMyVote && isLive) {
      borderColor = context.primaryTheme;
      borderWidth = 1.5;
      bgColor = context.primaryFocused;
      textColor = context.primaryTheme;
      radioColor = context.primaryTheme;
    } else if (isEnded) {
      borderColor = context.strokeLight;
      borderWidth = 1.0;
      bgColor = context.backgroundColor;
      textColor = context.ghost;
      radioColor = context.ghost;
    } else {
      borderColor = context.stroke;
      borderWidth = 1.0;
      bgColor = context.backgroundColor;
      textColor = context.heading;
      radioColor = context.stroke;
    }

    // Tap is only active when the poll is live AND the user hasn't voted yet
    // AND no vote request is in-flight.
    final bool canTap = isLive && !isVoted && !isVoting;

    return GestureDetector(
      onTap: canTap
          ? () {
              HapticFeedback.lightImpact();
              onVote?.call(option);
            }
          : null,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 250),
        height: 44.h,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(8.r),
          border: Border.all(color: borderColor, width: borderWidth),
          color: bgColor,
        ),
        child: Stack(
          alignment: AlignmentDirectional.centerStart,
          children: [
            // ── Result fill bar ──────────────────────────────────────────────
            if (revealResults && percentage > 0)
              AnimatedFractionallySizedBox(
                duration: const Duration(milliseconds: 400),
                curve: Curves.easeOut,
                widthFactor: percentage,
                child: Container(
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(7.r),
                    color: isMyVote
                        ? context.primaryTheme.withValues(alpha: 0.15)
                        : context.strokeLight.withValues(alpha: 0.7),
                  ),
                ),
              ),

            // ── Option content ───────────────────────────────────────────────
            Padding(
              padding: EdgeInsets.symmetric(horizontal: 12.w),
              child: Row(
                children: [
                  AnimatedSwitcher(
                    duration: const Duration(milliseconds: 200),
                    child: Icon(
                      isMyVote
                          ? Icons.check_circle_rounded
                          : Icons.radio_button_unchecked_rounded,
                      key: ValueKey(isMyVote),
                      size: 18.sp,
                      color: radioColor,
                    ),
                  ),
                  SizedBox(width: 10.w),
                  Expanded(
                    child: Text(
                      option.option,
                      style: context.titleRegular?.copyWith(
                        color: textColor,
                        fontWeight:
                            isMyVote ? FontWeight.w600 : FontWeight.normal,
                      ),
                    ),
                  ),
                  if (revealResults)
                    AnimatedSwitcher(
                      duration: const Duration(milliseconds: 300),
                      child: Text(
                        '$pctLabel (${option.votes})',
                        key: ValueKey(option.votes),
                        style: context.specialCaption1?.copyWith(
                          color: isMyVote
                              ? context.primaryTheme
                              : context.caption,
                        ),
                      ),
                    ),
                  // Loading spinner on the voted option while request is in-flight
                  if (isVoting && canTap == false && !isVoted)
                    SizedBox(
                      height: 14.sp,
                      width: 14.sp,
                      child: CircularProgressIndicator(
                        strokeWidth: 1.5,
                        color: context.primaryTheme,
                      ),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
