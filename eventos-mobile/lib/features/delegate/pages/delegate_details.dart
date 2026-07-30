import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../models/delegate_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/loading_skeletons/delegate_details_skeleton.dart';
import '../../../widgets/state_handler/api_state_handler.dart';
import '../../../widgets/shared_social_links_section.dart';
import '../delegate_controller.dart';
import '../widgets/delegate_card_actions.dart';
import '../widgets/delegate_header_details.dart';
import '../widgets/delegate_about_section.dart';
import '../widgets/delegate_details_info_rows.dart';

class DelegateDetails extends StatefulWidget {
  final DelegateItemModel delegate;

  const DelegateDetails({super.key, required this.delegate});

  @override
  State<DelegateDetails> createState() => _DelegateDetailsState();
}

class _DelegateDetailsState extends State<DelegateDetails> {
  late final DelegateController ctrl;

  @override
  void initState() {
    super.initState();
    ctrl = Get.find<DelegateController>();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ctrl.fetchDelegateDetail(widget.delegate.id);
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
          'Delegate Details',
          style: context.titleLarge?.copyWith(color: context.tertiaryText),
        ),
      ),
      body: Obx(() => ApiStateHandler(
            state: ctrl.detailStatus.value,
            onRetry: () => ctrl.fetchDelegateDetail(widget.delegate.id),
            skeleton: const DelegateDetailsSkeleton(),
            loadedElement: _buildDetail(context),
          )),
    );
  }

  Widget _buildDetail(BuildContext context) {
    final detail = ctrl.delegateDetail.value;
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
                DelegateHeaderDetails(detail: detail),
                SizedBox(height: 12.h),
                DelegateCardActions(delegate: widget.delegate),
                SizedBox(height: 16.h),
                const Divider(),
                SizedBox(height: 12.h),
                DelegateAboutSection(detail: detail),
                SizedBox(height: 16.h),
                DelegateDetailsInfoRows(detail: detail),
                SharedSocialLinksSection(
                  website: detail.website,
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}
