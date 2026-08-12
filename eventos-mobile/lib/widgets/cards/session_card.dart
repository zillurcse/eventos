import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../utils/extension/theme_ext.dart';
import '../custom_button.dart';
import '../custom_image.dart';
import '../image_group.dart';
import '../../models/session_model.dart';
import '../../features/session/session_controller.dart';
import '../../features/session/session_phase.dart';
import '../../features/briefcase/briefcase_controller.dart';
import '../../features/bookmarks/bookmark_controller.dart';
import '../../utils/bottom_sheets/add_note_bottom_sheet.dart';
import '../../utils/helpers/bottom_sheets.dart';
import '../../features/session/pages/session_details.dart';

class SessionCard extends StatelessWidget {
  final SessionModel? session;
  final bool isOnGoing;

  /// When true, card spans the available width (single-session layout).
  final bool fullWidth;
  final String title;
  final String startTime;
  final String endTime;
  final String dayLabel;
  final String logoUrl;
  final List<String> speakerImageUrls;

  const SessionCard({
    super.key,
    this.session,
    this.isOnGoing = false,
    this.fullWidth = false,
    this.title = '',
    this.startTime = '',
    this.endTime = '',
    this.dayLabel = '',
    this.logoUrl = '',
    this.speakerImageUrls = const [],
  });

  @override
  Widget build(BuildContext context) {
    final timeLabel = (startTime.isNotEmpty && endTime.isNotEmpty)
        ? '$startTime – $endTime'
        : '-';
    final displayTitle = title.isNotEmpty ? title : 'Session Title';

    return GestureDetector(
      onTap: () {
        if (session != null) {
          Get.to(() => SessionDetails(scheduleId: session!.id));
        }
      },
      child: Container(
        width: fullWidth ? double.infinity : context.width * .8,
        margin: fullWidth ? EdgeInsets.zero : EdgeInsets.only(right: 16.w),
        decoration: BoxDecoration(
          color: context.tertiaryText,
          borderRadius: BorderRadius.circular(12.r),
        ),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(12.r),
          child: Column(
            children: [
              Padding(
                padding: EdgeInsets.all(16.sp),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.center,
                      children: [
                        Container(
                          padding: EdgeInsets.all(6.sp),
                          decoration: BoxDecoration(
                            color: context.primaryFocused,
                            borderRadius: BorderRadius.circular(4.r),
                          ),
                          child: CustomImage(
                            "assets/svg/icons/schedule.svg",
                            color: context.primaryTheme,
                            height: 18.sp,
                          ),
                        ),
                        SizedBox(width: 8.w),
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              dayLabel,
                              style: context.specialCaption1?.copyWith(
                                color: context.caption,
                              ),
                            ),
                            Text(
                              timeLabel,
                              style: context.specialCaption1?.copyWith(
                                color: context.caption,
                              ),
                            ),
                          ],
                        ),
                        const Spacer(),
                        _SessionBookmarkButton(session: session),
                        SizedBox(width: 12.w),
                        GestureDetector(
                          onTap: () {
                            if (session != null) {
                              final briefcaseCtrl =
                                  Get.find<BriefcaseController>();
                              final match = briefcaseCtrl.notes
                                  .firstWhereOrNull(
                                    (n) =>
                                        n.noteType == 'Session' &&
                                        n.entityId == session!.id,
                                  );
                              final initialNoteText = match?.noteText;

                              addNoteBottomSheet(
                                child: AddNoteBottomSheet(
                                  noteType: 'Session',
                                  entityId: session!.id,
                                  entityName: session!.title,
                                  entityRole: session!.sessionPlace.isNotEmpty
                                      ? session!.sessionPlace
                                      : 'Community Hall',
                                  entityImage: '',
                                  initialNoteText: initialNoteText,
                                ),
                              );
                            }
                          },
                          child: Obx(() {
                            final hasNoteLocally =
                                session != null &&
                                Get.find<BriefcaseController>().notes.any(
                                  (n) =>
                                      n.noteType == 'Session' &&
                                      n.entityId == session!.id,
                                );
                            return CustomImage(
                              "assets/svg/icons/note_take.svg",
                              height: 21.sp,
                              width: 21.sp,
                              color: context.primaryTheme,
                            );
                          }),
                        ),
                      ],
                    ),
                    SizedBox(height: 12.sp),
                    ClipRRect(
                      borderRadius: BorderRadius.circular(8.r),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          CustomImage(
                            logoUrl,
                            width: context.width,
                            height: context.height * (fullWidth ? .22 : .16),
                            fit: BoxFit.cover,
                          ),
                          if (isOnGoing)
                            _SessionLiveProgressBar(
                              startsAt: session?.startsAt,
                              endsAt: session?.endsAt,
                            ),
                        ],
                      ),
                    ),
                    SizedBox(height: 12.sp),
                    Text(
                      displayTitle,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: context.h2?.copyWith(color: context.heading),
                    ),
                    SizedBox(height: 4.sp),
                    if (speakerImageUrls.isNotEmpty) ...[
                      const Divider(),
                      SizedBox(height: 4.sp),
                      Text(
                        "Speakers (${speakerImageUrls.length})",
                        style: context.specialCaption1?.copyWith(
                          color: context.caption,
                        ),
                      ),
                      SizedBox(height: 8.sp),
                      ImageGroup(imageUrls: speakerImageUrls),
                      SizedBox(height: 12.sp),
                    ],
                    if (isOnGoing)
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          const Divider(),
                          SizedBox(height: 12.sp),
                          Button.roundedText(
                            text: "Join Now",
                            width: fullWidth
                                ? double.infinity
                                : context.width * .4,
                            style: context.buttonMediumBold?.copyWith(
                              color: context.tertiaryText,
                            ),
                            onTap: () {},
                          ),
                        ],
                      )
                    else
                      Container(
                        padding: EdgeInsets.fromLTRB(8.sp, 4.sp, 12.sp, 6.sp),
                        decoration: BoxDecoration(
                          color: context.greenPositiveLight,
                          borderRadius: BorderRadius.circular(8.r),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            CustomImage(
                              "assets/svg/icons/timer.svg",
                              height: 14.sp,
                            ),
                            SizedBox(width: 8.w),
                            Text(
                              "Starts soon",
                              style: context.specialCaption1?.copyWith(
                                color: context.greenPositive,
                              ),
                            ),
                          ],
                        ),
                      ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SessionBookmarkButton extends StatelessWidget {
  final SessionModel? session;

  const _SessionBookmarkButton({this.session});

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      final s = session;
      if (s == null) return const SizedBox.shrink();

      final bookmarked = _isBookmarked(s);
      return GestureDetector(
        onTap: () => _toggle(s),
        child: Icon(
          bookmarked ? Icons.bookmark : Icons.bookmark_border,
          color: context.primaryTheme,
          size: 26.sp,
        ),
      );
    });
  }

  bool _isBookmarked(SessionModel s) {
    if (Get.isRegistered<BookmarkController>()) {
      final bm = Get.find<BookmarkController>();
      if (s.uuid.isNotEmpty && bm.isOn('session', s.uuid)) return true;
      if (bm.isOnHashed('session', s.id)) return true;
    }
    if (Get.isRegistered<SessionController>()) {
      final sc = Get.find<SessionController>();
      if (s.uuid.isNotEmpty && sc.isBookmarked(s.uuid)) return true;
      return sc.isBookmarkedById(s.id);
    }
    return s.isFavorite;
  }

  Future<void> _toggle(SessionModel s) async {
    if (Get.isRegistered<BookmarkController>() && s.uuid.isNotEmpty) {
      final bm = Get.find<BookmarkController>();
      final on = !bm.isOn('session', s.uuid);
      final ok = await bm.toggleSessionBookmark(s);
      if (ok && Get.isRegistered<SessionController>()) {
        Get.find<SessionController>().syncBookmarkFromExternal(s.uuid, on);
      }
      return;
    }
    if (Get.isRegistered<SessionController>()) {
      await Get.find<SessionController>().toggleBookmark(
        s.id,
        uuid: s.uuid.isNotEmpty ? s.uuid : null,
      );
    }
  }
}

/// Live session progress under the cover image, driven by start/end times.
class _SessionLiveProgressBar extends StatefulWidget {
  final String? startsAt;
  final String? endsAt;

  const _SessionLiveProgressBar({required this.startsAt, required this.endsAt});

  @override
  State<_SessionLiveProgressBar> createState() =>
      _SessionLiveProgressBarState();
}

class _SessionLiveProgressBarState extends State<_SessionLiveProgressBar> {
  Timer? _timer;
  double _value = 0;

  @override
  void initState() {
    super.initState();
    _tick();
    _timer = Timer.periodic(const Duration(seconds: 15), (_) => _tick());
  }

  @override
  void didUpdateWidget(covariant _SessionLiveProgressBar oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.startsAt != widget.startsAt ||
        oldWidget.endsAt != widget.endsAt) {
      _tick();
    }
  }

  void _tick() {
    final next = SessionPhaseHelper.progress(
      startsAt: widget.startsAt,
      endsAt: widget.endsAt,
    );
    if (!mounted) return;
    setState(() => _value = next);
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final value = _value.isFinite ? _value.clamp(0.0, 1.0) : 0.0;
    return LinearProgressIndicator(
      value: value,
      backgroundColor: context.primaryFocused,
      color: context.primaryTheme,
      borderRadius: BorderRadius.only(
        topRight: Radius.circular(100.r),
        bottomRight: Radius.circular(100.r),
      ),
      minHeight: 6.h,
    );
  }
}
