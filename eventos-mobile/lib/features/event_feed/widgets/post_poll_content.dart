import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../models/event_feed_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../event_feed_controller.dart';
import 'poll_option.dart';

class PostPollContent extends StatefulWidget {
  final FeedPostModel post;
  const PostPollContent({super.key, required this.post});

  @override
  State<PostPollContent> createState() => _PostPollContentState();
}

class _PostPollContentState extends State<PostPollContent> {
  final _showResults = false.obs;

  /// Whether a vote API call is currently in-flight for this post.
  final _isVoting = false.obs;

  @override
  Widget build(BuildContext context) {
    final ctrl = Get.find<EventFeedController>();

    // Drive everything from the reactive posts list so Obx rebuilds
    // whenever votePoll / refreshPollData mutates the post in the controller.
    return Obx(() {
      // Find the up-to-date post from the controller's list.
      final post = ctrl.posts.firstWhereOrNull((p) => p.id == widget.post.id)
          ?? widget.post;

      final total = post.totalVotes ?? 0;
      final voted = post.voteByThisUser ?? false;
      final isLive = post.isLive;
      final isEnded = !isLive;

      final bannerBg =
          isLive ? context.greenPositiveLight : context.primaryFocused;
      final bannerText =
          isLive ? context.greenPositive : context.primaryTheme;
      final bannerLabel = isLive ? 'Poll Live' : 'Poll Ended – $total Votes';

      return Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // ── Status banner ──────────────────────────────────────────────────
          Container(
            width: double.infinity,
            padding: EdgeInsets.symmetric(vertical: 8.h),
            decoration: BoxDecoration(
              color: bannerBg,
              borderRadius: BorderRadius.circular(6.r),
            ),
            child: Text(
              bannerLabel,
              textAlign: TextAlign.center,
              style: context.titleRegular?.copyWith(
                color: bannerText,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
          SizedBox(height: 10.h),

          // ── Options ────────────────────────────────────────────────────────
          ...post.options.map((opt) {
            final pct = total > 0 ? (opt.votes / total) : 0.0;
            return Padding(
              padding: EdgeInsets.only(bottom: 8.h),
              child: PollOption(
                option: opt,
                percentage: pct,
                isMyVote: post.myVote == opt.id,
                isVoted: voted,
                isLive: isLive,
                showResults: _showResults.value,
                isVoting: _isVoting.value,
                onVote: (selectedOption) async {
                  if (_isVoting.value) return;
                  _isVoting.value = true;
                  await ctrl.votePoll(post.id, selectedOption.id);
                  _isVoting.value = false;
                },
              ),
            );
          }),

          // ── Show / hide result button (ended polls only) ───────────────────
          if (isEnded) ...[
            SizedBox(height: 4.h),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton(
                onPressed: () => _showResults.toggle(),
                style: OutlinedButton.styleFrom(
                  foregroundColor: context.primaryTheme,
                  side: BorderSide(color: context.primaryTheme, width: 1.5),
                  shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(8.r)),
                  padding: EdgeInsets.symmetric(vertical: 12.h),
                ),
                child: Obx(() => Text(
                      _showResults.value ? 'Hide Result' : 'Show Result',
                      style: context.titleRegular?.copyWith(
                        color: context.primaryTheme,
                        fontWeight: FontWeight.w700,
                      ),
                    )),
              ),
            ),
          ],
        ],
      );
    });
  }
}
