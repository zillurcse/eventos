import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../models/contest_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_image.dart';
import '../../../widgets/state_handler/api_state_handler.dart';
import '../contest_details_controller.dart';
import '../widgets/contest_countdown.dart';
import '../widgets/entry_card.dart';
import '../widgets/entry_composer.dart';
import '../widgets/winners_section.dart';

class ContestDetailsView extends StatelessWidget {
  final String contestId;

  const ContestDetailsView({super.key, required this.contestId});

  @override
  Widget build(BuildContext context) {
    final ctrl = Get.find<ContestDetailsController>();

    return Scaffold(
      backgroundColor: const Color(0xFFF7F7FB),
      appBar: AppBar(
        backgroundColor: context.primaryTheme,
        foregroundColor: Colors.white,
        elevation: 0,
        title: Text(
          'Contest',
          style: context.h2?.copyWith(
            color: Colors.white,
            fontWeight: FontWeight.w700,
          ),
        ),
      ),
      body: Obx(
        () => ApiStateHandler(
          state: ctrl.dataStatus.value,
          onRetry: ctrl.fetchAll,
          loadedElement: ctrl.contest.value == null
              ? Center(
                  child: Text(
                    'This contest is no longer available.',
                    style: context.bodyRegular?.copyWith(color: context.caption),
                  ),
                )
              : _ContestDetailsBody(contest: ctrl.contest.value!),
        ),
      ),
    );
  }
}

class _ContestDetailsBody extends StatelessWidget {
  final Contest contest;

  const _ContestDetailsBody({required this.contest});

  String? _windowLabel() {
    final start = contest.startsAt != null
        ? DateTime.tryParse(contest.startsAt!)
        : null;
    final end =
        contest.endsAt != null ? DateTime.tryParse(contest.endsAt!) : null;
    if (start == null && end == null) return null;
    final fmt = DateFormat('dd MMM yyyy, h:mm a');
    if (start != null && end != null) {
      return '${fmt.format(start.toLocal())} – ${fmt.format(end.toLocal())}';
    }
    if (start != null) return 'Starts ${fmt.format(start.toLocal())}';
    return 'Ends ${fmt.format(end!.toLocal())}';
  }

  @override
  Widget build(BuildContext context) {
    final ctrl = Get.find<ContestDetailsController>();
    final window = _windowLabel();

    return RefreshIndicator(
      onRefresh: ctrl.fetchAll,
      child: ListView(
        padding: EdgeInsets.fromLTRB(16.w, 12.h, 16.w, 32.h),
        children: [
          if (contest.bannerUrl?.isNotEmpty ?? false)
            ClipRRect(
              borderRadius: BorderRadius.circular(12.r),
              child: CustomImage(
                contest.bannerUrl!,
                fit: BoxFit.cover,
                width: double.infinity,
                height: 180.h,
              ),
            ),
          SizedBox(height: 14.h),
          Text(
            contest.title,
            style: context.h2?.copyWith(
              fontWeight: FontWeight.w800,
              color: const Color(0xFF1E293B),
              fontSize: 20.sp,
            ),
          ),
          SizedBox(height: 8.h),
          Wrap(
            spacing: 8.w,
            runSpacing: 6.h,
            children: [
              _pill(
                context,
                contest.isEntryType ? 'Entry contest' : 'Response contest',
              ),
              if (contest.points > 0)
                _pill(
                  context,
                  '+${contest.points} pts per ${contest.isEntryType ? 'entry' : 'response'}',
                  highlight: true,
                ),
            ],
          ),
          if (window != null) ...[
            SizedBox(height: 10.h),
            Text(
              window,
              style: context.bodyRegular?.copyWith(
                color: const Color(0xFF64748B),
                fontSize: 13.sp,
              ),
            ),
          ],
          if (contest.caption?.isNotEmpty ?? false) ...[
            SizedBox(height: 10.h),
            Text(
              contest.caption!,
              style: context.bodyRegular?.copyWith(
                color: const Color(0xFF334155),
                height: 1.4,
              ),
            ),
          ],
          if (contest.description?.isNotEmpty ?? false) ...[
            SizedBox(height: 10.h),
            Text(
              contest.description!,
              style: context.bodyRegular?.copyWith(
                color: const Color(0xFF475569),
                height: 1.45,
              ),
            ),
          ],
          if (contest.descriptionFileUrl?.isNotEmpty ?? false) ...[
            SizedBox(height: 10.h),
            TextButton.icon(
              onPressed: () async {
                final uri = Uri.tryParse(contest.descriptionFileUrl!);
                if (uri != null) {
                  await launchUrl(uri, mode: LaunchMode.externalApplication);
                }
              },
              icon: Icon(Icons.attach_file, size: 18.sp),
              label: Text(
                contest.descriptionFileName ?? 'Contest details',
              ),
            ),
          ],
          SizedBox(height: 12.h),
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
            Text(
              contest.winnerChooser == 'most_likes'
                  ? 'The ${contest.winnerNumber} most-liked ${contest.isEntryType ? 'entries' : 'responses'} win.'
                  : 'Winners are chosen by the organizer.',
              style: context.bodyRegular?.copyWith(
                color: const Color(0xFF64748B),
                fontSize: 13.sp,
              ),
            ),
          SizedBox(height: 18.h),
          if (contest.isEnded && contest.winners.isNotEmpty)
            ContestWinnersSection(
              contest: contest,
              winners: contest.winners,
            ),
          if (contest.canEnter)
            ContestEntryComposer(contest: contest)
          else if (!contest.isEnded && contest.myEntryCount > 0)
            Padding(
              padding: EdgeInsets.only(bottom: 14.h),
              child: Text(
                'You’ve already entered this contest. Good luck!',
                style: context.bodyRegular?.copyWith(
                  color: context.primaryTheme,
                  fontWeight: FontWeight.w600,
                ),
              ),
            )
          else if (contest.isUpcoming)
            Padding(
              padding: EdgeInsets.only(bottom: 14.h),
              child: Text(
                'This contest opens soon.',
                style: context.bodyRegular?.copyWith(color: context.caption),
              ),
            ),
          _EntriesHeader(contest: contest),
          Obx(() {
            if (ctrl.entriesLoading.value) {
              return Padding(
                padding: EdgeInsets.symmetric(vertical: 24.h),
                child: const Center(child: CircularProgressIndicator()),
              );
            }
            if (ctrl.entries.isEmpty) {
              return Padding(
                padding: EdgeInsets.symmetric(vertical: 20.h),
                child: Text(
                  _emptyLabel(ctrl),
                  style: context.bodyRegular?.copyWith(color: context.caption),
                  textAlign: TextAlign.center,
                ),
              );
            }
            return Column(
              children: ctrl.entries
                  .map((e) => ContestEntryCard(entry: e, contest: contest))
                  .toList(),
            );
          }),
        ],
      ),
    );
  }

  String _emptyLabel(ContestDetailsController ctrl) {
    if (ctrl.mineOnly.value || !contest.canSeeOthersEntries) {
      return 'You haven’t entered yet.';
    }
    if (contest.isUpcoming) {
      return 'Entries open when the contest starts.';
    }
    return 'No entries yet - be the first.';
  }

  Widget _pill(BuildContext context, String text, {bool highlight = false}) {
    return Container(
      padding: EdgeInsets.symmetric(horizontal: 10.w, vertical: 5.h),
      decoration: BoxDecoration(
        color: highlight
            ? context.primaryFocused
            : const Color(0xFFF1F5F9),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        text,
        style: TextStyle(
          fontSize: 12.sp,
          fontWeight: FontWeight.w600,
          color: highlight ? context.primaryTheme : const Color(0xFF475569),
        ),
      ),
    );
  }
}

class _EntriesHeader extends StatelessWidget {
  final Contest contest;

  const _EntriesHeader({required this.contest});

  @override
  Widget build(BuildContext context) {
    final ctrl = Get.find<ContestDetailsController>();

    return Padding(
      padding: EdgeInsets.only(bottom: 12.h, top: 4.h),
      child: Obx(() {
        final title = ctrl.mineOnly.value
            ? 'My entries'
            : (contest.isEntryType ? 'Entries' : 'Responses');
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    title,
                    style: context.h2?.copyWith(
                      fontWeight: FontWeight.w800,
                      fontSize: 16.sp,
                      color: const Color(0xFF1E293B),
                    ),
                  ),
                ),
                if (contest.canSeeOthersEntries)
                  GestureDetector(
                    onTap: () => ctrl.setMineOnly(!ctrl.mineOnly.value),
                    child: Container(
                      padding: EdgeInsets.symmetric(
                        horizontal: 10.w,
                        vertical: 6.h,
                      ),
                      decoration: BoxDecoration(
                        color: ctrl.mineOnly.value
                            ? context.primaryTheme
                            : Colors.white,
                        borderRadius: BorderRadius.circular(999),
                        border: Border.all(
                          color: ctrl.mineOnly.value
                              ? context.primaryTheme
                              : const Color(0xFFE2E8F0),
                        ),
                      ),
                      child: Text(
                        'Mine',
                        style: TextStyle(
                          fontSize: 12.sp,
                          fontWeight: FontWeight.w600,
                          color: ctrl.mineOnly.value
                              ? Colors.white
                              : const Color(0xFF64748B),
                        ),
                      ),
                    ),
                  ),
              ],
            ),
            SizedBox(height: 8.h),
            Row(
              children: [
                _sortChip(context, ctrl, 'recent', 'Recent'),
                SizedBox(width: 8.w),
                _sortChip(context, ctrl, 'top', 'Top'),
              ],
            ),
          ],
        );
      }),
    );
  }

  Widget _sortChip(
    BuildContext context,
    ContestDetailsController ctrl,
    String key,
    String label,
  ) {
    final on = ctrl.sort.value == key;
    return GestureDetector(
      onTap: () => ctrl.setSort(key),
      child: Container(
        padding: EdgeInsets.symmetric(horizontal: 12.w, vertical: 6.h),
        decoration: BoxDecoration(
          color: on ? context.primaryTheme : Colors.white,
          borderRadius: BorderRadius.circular(999),
          border: Border.all(
            color: on ? context.primaryTheme : const Color(0xFFE2E8F0),
          ),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 12.sp,
            fontWeight: FontWeight.w600,
            color: on ? Colors.white : const Color(0xFF64748B),
          ),
        ),
      ),
    );
  }
}
