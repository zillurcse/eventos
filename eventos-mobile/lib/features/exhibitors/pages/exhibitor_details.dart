import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../models/exhibitor_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../utils/service/engagement_service.dart';
import '../../../widgets/loading_skeletons/exhibitor_details_skeleton.dart';
import '../../../widgets/shared_social_links_section.dart';
import '../../../widgets/state_handler/api_state_handler.dart';
import '../exhibitor_controller.dart';
import '../widgets/exhibitor_cover_image.dart';
import '../widgets/exhibitor_header_details.dart';
import '../widgets/exhibitor_action_buttons.dart';
import '../widgets/exhibitor_rating_section.dart';
import '../widgets/exhibitor_about_section.dart';
import '../widgets/exhibitor_get_in_touch.dart';
import '../widgets/exhibitor_videos_section.dart';
import '../widgets/exhibitor_projects_section.dart';
import '../widgets/exhibitor_products_section.dart';
import '../widgets/exhibitor_members_section.dart';
import '../widgets/exhibitor_map_section.dart';
import '../widgets/exhibitor_brochures_section.dart';

class ExhibitorDetails extends StatefulWidget {
  final ExhibitorModel exhibitor;

  const ExhibitorDetails({super.key, required this.exhibitor});

  @override
  State<ExhibitorDetails> createState() => _ExhibitorDetailsState();
}

class _ExhibitorDetailsState extends State<ExhibitorDetails> {
  late final ExhibitorController ctrl;
  DateTime? _enteredAt;

  @override
  void initState() {
    super.initState();
    ctrl = Get.find<ExhibitorController>();
    _enteredAt = DateTime.now();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ctrl.fetchExhibitorDetail(widget.exhibitor.slug);
    });
  }

  @override
  void dispose() {
    final entered = _enteredAt;
    if (entered != null) {
      final detail = ctrl.exhibitorDetail.value ?? widget.exhibitor;
      final isSponsor =
          detail.exhibitorType.toLowerCase().contains('sponsor');
      EngagementService.instance.track(
        actionType: 'booth.left',
        objectType: isSponsor ? 'sponsor' : 'exhibitor',
        objectUuid: detail.slug.isNotEmpty ? detail.slug : widget.exhibitor.slug,
        durationMs: DateTime.now().difference(entered).inMilliseconds,
        metadata: {'name': detail.name},
      );
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        titleSpacing: 0,
        backgroundColor: context.primaryTheme,
        title: Text(
          'Exhibitors Details',
          style: context.titleLarge?.copyWith(color: context.tertiaryText),
        ),
        actions: [
          IconButton(
            onPressed: () {},
            icon: Icon(
              Icons.share_outlined,
              size: 20.sp,
              color: context.tertiaryText,
            ),
          ),
          SizedBox(width: 8.w),
        ],
      ),
      body: Obx(() => ApiStateHandler(
            state: ctrl.detailStatus.value,
            onRetry: () => ctrl.fetchExhibitorDetail(widget.exhibitor.slug),
            skeleton: const ExhibitorDetailsSkeleton(),
            loadedElement: _buildDetail(context),
          )),
      bottomNavigationBar: Obx(() {
        final exhibitor = ctrl.exhibitorDetail.value;
        if (exhibitor == null) return const SizedBox.shrink();
        return Container(
          color: Colors.white,
          padding: EdgeInsets.fromLTRB(16.w, 10.h, 16.w, 16.h),
          child: ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: context.primaryTheme,
              foregroundColor: Colors.white,
              minimumSize: Size(double.infinity, 48.h),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12.r),
              ),
              elevation: 0,
            ),
            onPressed: () {
              Get.rawSnackbar(message: "Details shared with ${exhibitor.name}");
            },
            child: Text(
              "Share your details",
              style: context.buttonMediumBold?.copyWith(
                color: Colors.white,
                fontSize: 16.sp,
              ),
            ),
          ),
        );
      }),
    );
  }

  Widget _buildDetail(BuildContext context) {
    final exhibitor = ctrl.exhibitorDetail.value;
    if (exhibitor == null) return const SizedBox.shrink();

    return SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          ExhibitorCoverImage(exhibitor: exhibitor),
          Container(
            color: context.tertiaryText,
            padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 16.h),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                ExhibitorHeaderDetails(exhibitor: exhibitor),
                SizedBox(height: 24.h),
                ExhibitorActionButtons(exhibitorId: exhibitor.id),
                SizedBox(height: 24.h),
                Divider(color: context.strokeLight),
                SizedBox(height: 20.h),
                ExhibitorRatingSection(exhibitor: exhibitor),
                SizedBox(height: 20.h),
                Divider(color: context.strokeLight),
                SizedBox(height: 20.h),
                ExhibitorAboutSection(exhibitor: exhibitor),
                SizedBox(height: 32.h),
                ExhibitorGetInTouch(exhibitor: exhibitor),
                SharedSocialLinksSection(
                  facebook: exhibitor.facebook,
                  instagram: exhibitor.instagram,
                  linkedin: exhibitor.linkedin,
                  twitter: exhibitor.twitter,
                  whatsapp: exhibitor.whatsapp,
                  website: exhibitor.website,
                ),
                ExhibitorVideosSection(exhibitor: exhibitor),
                SizedBox(height: 24.h),
                ExhibitorProjectsSection(exhibitor: exhibitor),
                SizedBox(height: 24.h),
                ExhibitorProductsSection(exhibitor: exhibitor),
                SizedBox(height: 24.h),
                ExhibitorMembersSection(exhibitor: exhibitor),
                SizedBox(height: 24.h),
                ExhibitorMapSection(exhibitor: exhibitor),
                SizedBox(height: 24.h),
                ExhibitorBrochuresSection(exhibitor: exhibitor),
                SizedBox(height: 12.h),
              ],
            ),
          )
        ],
      ),
    );
  }
}
