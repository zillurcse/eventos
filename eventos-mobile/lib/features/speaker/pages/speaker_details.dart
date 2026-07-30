import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../models/speaker_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/loading_skeletons/speaker_details_skeleton.dart';
import '../../../widgets/shared_social_links_section.dart';
import '../../../widgets/state_handler/api_state_handler.dart';
import '../speaker_controller.dart';
import '../widgets/speaker_card_actions.dart';
import '../widgets/speaker_header_details.dart';
import '../widgets/speaker_about_section.dart';
import '../widgets/speaker_details_info_rows.dart';
import '../widgets/speaker_details_tags.dart';
import '../widgets/speaker_session_card.dart';

class SpeakerDetails extends StatefulWidget {
  final SpeakerItemModel speaker;

  const SpeakerDetails({super.key, required this.speaker});

  @override
  State<SpeakerDetails> createState() => _SpeakerDetailsState();
}

class _SpeakerDetailsState extends State<SpeakerDetails> {
  late final SpeakerController ctrl;

  @override
  void initState() {
    super.initState();
    ctrl = Get.find<SpeakerController>();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ctrl.fetchSpeakerDetail(widget.speaker.id);
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      resizeToAvoidBottomInset: true,
      appBar: AppBar(
        titleSpacing: 0,
        backgroundColor: context.primaryTheme,
        title: Text(
          'Speaker Details',
          style: context.titleLarge?.copyWith(color: context.tertiaryText),
        ),
      ),
      body: Obx(() => ApiStateHandler(
            state: ctrl.detailStatus.value,
            onRetry: () => ctrl.fetchSpeakerDetail(widget.speaker.id),
            skeleton: const SpeakerDetailsSkeleton(),
            loadedElement: _buildDetail(context),
          )),
    );
  }

  Widget _buildDetail(BuildContext context) {
    final detail = ctrl.speakerDetail.value;
    if (detail == null) return const SizedBox.shrink();

    return CustomScrollView(
      slivers: [
        SliverToBoxAdapter(
          child: Container(
            color: context.tertiaryText,
            padding: EdgeInsets.fromLTRB(16.w, 16.h, 16.w, 16.h),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                SpeakerHeaderDetails(detail: detail),
                SizedBox(height: 12.h),
                SpeakerCardActions(speakerDetail: detail),
                SizedBox(height: 16.h),
                const Divider(),
                SizedBox(height: 12.h),
                SpeakerAboutSection(detail: detail),
                SizedBox(height: 16.h),
                SpeakerDetailsInfoRows(detail: detail),
                SpeakerDetailsTags(detail: detail),
                SharedSocialLinksSection(
                  facebook: detail.facebook,
                  instagram: detail.instagram,
                  linkedin: detail.linkedin,
                  twitter: detail.twitter,
                  whatsapp: detail.whatsapp,
                ),
              ],
            ),
          ),
        ),
        SliverToBoxAdapter(
          child: Obx(() {
            final sessions = ctrl.speakerSessions;
            if (sessions.isEmpty) return const SizedBox.shrink();

            return Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Padding(
                  padding: EdgeInsets.fromLTRB(16.w, 16.h, 16.w, 8.h),
                  child: Text(
                    'Sessions',
                    style: context.h2?.copyWith(
                      fontWeight: FontWeight.bold,
                      color: context.heading,
                    ),
                  ),
                ),
                ListView.builder(
                  padding: EdgeInsets.zero,
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  itemCount: sessions.length,
                  itemBuilder: (context, index) {
                    final session = sessions[index];
                    return SpeakerSessionCard(
                      session: session,
                      hasBorder: index == 1,
                    );
                  },
                ),
              ],
            );
          }),
        ),
      ],
    );
  }
}

