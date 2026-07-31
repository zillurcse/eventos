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
import '../widgets/session_player.dart';
import '../widgets/session_sticky_stream_button.dart';
import '../widgets/engagement/session_engagement_panel.dart';

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
            if (detail == null || !detail.isAllowedToRate) {
              return const SizedBox.shrink();
            }
            return Padding(
              padding: EdgeInsets.only(right: 16.w),
              child: RatingStars(
                rating: ctrl.sessionRating.value,
                allowedToRate: detail.isAllowedToRate,
                onRate: ctrl.submitRating,
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
                SessionPlayer(detail: detail),
                Container(
                  color: context.tertiaryText,
                  padding:
                      EdgeInsets.symmetric(horizontal: 16.w, vertical: 16.h),
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
                SessionEngagementPanel(detail: detail),
              ],
            ),
          ),
        ),
        SessionStickyStreamButton(detail: detail),
      ],
    );
  }
}

class RatingStars extends StatelessWidget {
  final int rating;
  final bool allowedToRate;
  final ValueChanged<int>? onRate;

  const RatingStars({
    super.key,
    this.rating = 0,
    this.allowedToRate = true,
    this.onRate,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: List.generate(5, (index) {
        final star = index + 1;
        return GestureDetector(
          onTap: allowedToRate && onRate != null ? () => onRate!(star) : null,
          child: Icon(
            index < rating ? Icons.star : Icons.star_border,
            color: index < rating ? Colors.amber : Colors.grey,
            size: 20.sp,
          ),
        );
      }),
    );
  }
}
