import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../models/session_detail_response_model.dart';
import '../../../widgets/state_handler/api_state_handler.dart';
import '../../../utils/extension/theme_ext.dart';
import '../session_controller.dart';
import '../../../widgets/loading_skeletons/session_details_skeleton.dart';
import '../widgets/session_cover_image.dart';
import '../widgets/session_header_details.dart';
import '../widgets/session_action_buttons.dart';
import '../widgets/session_about_section.dart';
import '../widgets/session_speakers_section.dart';
import '../widgets/session_sponsors_section.dart';
import '../widgets/session_files_section.dart';
import '../widgets/session_sticky_stream_button.dart';

class SessionDetails extends StatefulWidget {
  final int scheduleId;
  const SessionDetails({super.key, required this.scheduleId});

  @override
  State<SessionDetails> createState() => _SessionDetailsState();
}

class _SessionDetailsState extends State<SessionDetails> {
  late final SessionController ctrl;

  @override
  void initState() {
    super.initState();
    ctrl = Get.find<SessionController>();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ctrl.fetchSessionDetails(widget.scheduleId);
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        titleSpacing: 0,
        backgroundColor: context.primaryTheme,
        title: Text(
          'Session Details',
          style: context.titleLarge?.copyWith(color: context.tertiaryText),
        ),
        actions: [
          Obx(() {
            final detail = ctrl.sessionDetail.value;
            if (detail == null) return const SizedBox.shrink();
            return Padding(
              padding: EdgeInsets.only(right: 16.w),
              child: RatingStars(
                initialRating: 3,
                allowedToRate: detail.isAllowedToRate,
              ),
            );
          }),
        ],
      ),
      body: Obx(() => ApiStateHandler(
            state: ctrl.detailStatus.value,
            onRetry: () => ctrl.fetchSessionDetails(widget.scheduleId),
            skeleton: const SessionDetailsSkeleton(),
            loadedElement: _buildContent(context),
          )),
    );
  }

  Widget _buildContent(BuildContext context) {
    final SessionDetailModel? detail = ctrl.sessionDetail.value;
    if (detail == null) return const SizedBox.shrink();

    return Column(
      children: [
        Expanded(
          child: SingleChildScrollView(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                SessionCoverImage(detail: detail),
                Container(
                  color: context.tertiaryText,
                  padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 16.h),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      SessionHeaderDetails(detail: detail),
                      SizedBox(height: 20.h),
                      SessionActionButtons(detail: detail),
                      SizedBox(height: 24.h),
                      SessionAboutSection(detail: detail),
                    ],
                  ),
                ),
                SessionSpeakersSection(detail: detail),
                SessionSponsorsSection(detail: detail),
                SessionFilesSection(detail: detail),
              ],
            ),
          ),
        ),
        SessionStickyStreamButton(detail: detail),
      ],
    );
  }
}

// ── Rating Stars Component ──
class RatingStars extends StatefulWidget {
  final int initialRating;
  final bool allowedToRate;
  const RatingStars({super.key, this.initialRating = 3, this.allowedToRate = true});

  @override
  State<RatingStars> createState() => _RatingStarsState();
}

class _RatingStarsState extends State<RatingStars> {
  late int _rating;

  @override
  void initState() {
    super.initState();
    _rating = widget.initialRating;
  }

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: List.generate(5, (index) {
        return GestureDetector(
          onTap: widget.allowedToRate
              ? () {
                  setState(() {
                    _rating = index + 1;
                  });
                }
              : null,
          child: Icon(
            index < _rating ? Icons.star : Icons.star_border,
            color: index < _rating ? Colors.amber : Colors.grey,
            size: 20.sp,
          ),
        );
      }),
    );
  }
}
