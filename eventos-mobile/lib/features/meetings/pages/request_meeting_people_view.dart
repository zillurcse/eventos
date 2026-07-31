import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../models/meeting_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../utils/theme/app_colors.dart';
import '../../../widgets/custom_image.dart';
import '../meetings_controller.dart';
import 'request_meeting_form_view.dart';

class RequestMeetingPeopleView extends StatelessWidget {
  const RequestMeetingPeopleView({super.key});

  static const _roleLabels = {
    'attendee': 'Attendees',
    'speaker': 'Speakers',
    'exhibitor': 'Exhibitors',
    'sponsor': 'Sponsors',
  };

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<MeetingsController>();

    return Scaffold(
      backgroundColor: const Color(0xFFF7F7FB),
      appBar: AppBar(
        backgroundColor: primaryTheme,
        foregroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new, size: 18),
          onPressed: () => Get.back(),
        ),
        title: Text(
          'Request a Meeting',
          style: TextStyle(
            color: Colors.white,
            fontSize: 18.sp,
            fontWeight: FontWeight.w700,
          ),
        ),
        centerTitle: false,
      ),
      body: Column(
        children: [
          Padding(
            padding: EdgeInsets.fromLTRB(16.w, 16.h, 16.w, 8.h),
            child: Container(
              height: 44.h,
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(22.r),
                border: Border.all(color: context.stroke),
              ),
              child: Row(
                children: [
                  SizedBox(width: 14.w),
                  CustomImage(
                    'assets/svg/icons/search.svg',
                    height: 18.sp,
                    color: context.ghost,
                  ),
                  SizedBox(width: 10.w),
                  Expanded(
                    child: TextField(
                      controller: controller.partnerSearchController,
                      onChanged: controller.setPartnerSearch,
                      decoration: InputDecoration(
                        border: InputBorder.none,
                        isDense: true,
                        hintText: 'Search people',
                        hintStyle: context.bodyRegular?.copyWith(
                          color: context.ghost,
                        ),
                      ),
                    ),
                  ),
                  Obx(
                    () => controller.partnerSearch.value.isNotEmpty
                        ? GestureDetector(
                            onTap: controller.clearPartnerSearch,
                            child: Padding(
                              padding: EdgeInsets.symmetric(horizontal: 10.w),
                              child: Icon(
                                Icons.close,
                                size: 18.sp,
                                color: context.ghost,
                              ),
                            ),
                          )
                        : const SizedBox.shrink(),
                  ),
                ],
              ),
            ),
          ),
          Obx(() {
            final roles = controller.allowedRoles;
            if (roles.length <= 1) return const SizedBox.shrink();
            return SizedBox(
              height: 40.h,
              child: ListView(
                scrollDirection: Axis.horizontal,
                padding: EdgeInsets.symmetric(horizontal: 16.w),
                children: [
                  _RoleChip(
                    label: 'All',
                    selected: controller.roleFilter.value.isEmpty,
                    onTap: () {
                      if (controller.roleFilter.value.isNotEmpty) {
                        controller.roleFilter.value = '';
                        controller.fetchPartners();
                      }
                    },
                  ),
                  ...roles.map(
                    (r) => _RoleChip(
                      label: _roleLabels[r] ?? r,
                      selected: controller.roleFilter.value == r,
                      onTap: () => controller.setRoleFilter(r),
                    ),
                  ),
                ],
              ),
            );
          }),
          Expanded(
            child: Obx(() {
              if (controller.partnersLoading.value &&
                  controller.partners.isEmpty) {
                return const Center(child: CircularProgressIndicator());
              }
              if (!controller.canRequest) {
                return Center(
                  child: Padding(
                    padding: EdgeInsets.all(24.w),
                    child: Text(
                      'You have reached your meeting request limit.',
                      textAlign: TextAlign.center,
                      style: context.bodyRegular?.copyWith(
                        color: context.caption,
                      ),
                    ),
                  ),
                );
              }
              if (controller.partners.isEmpty) {
                return Center(
                  child: Text(
                    'No one matches your search.',
                    style: context.bodyRegular?.copyWith(
                      color: context.caption,
                    ),
                  ),
                );
              }

              return ListView.separated(
                padding: EdgeInsets.fromLTRB(16.w, 8.h, 16.w, 24.h),
                itemCount: controller.partners.length,
                separatorBuilder: (_, __) => SizedBox(height: 10.h),
                itemBuilder: (_, index) {
                  final partner = controller.partners[index];
                  return _PartnerTile(
                    partner: partner,
                    onTap: () async {
                      await controller.choosePartner(partner);
                      Get.to(() => const RequestMeetingFormView());
                    },
                  );
                },
              );
            }),
          ),
        ],
      ),
    );
  }
}

class _RoleChip extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;

  const _RoleChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(right: 8.w),
      child: GestureDetector(
        onTap: onTap,
        child: Container(
          alignment: Alignment.center,
          padding: EdgeInsets.symmetric(horizontal: 14.w),
          decoration: BoxDecoration(
            color: selected ? context.primaryTheme : Colors.white,
            borderRadius: BorderRadius.circular(20.r),
            border: Border.all(
              color: selected ? context.primaryTheme : context.stroke,
            ),
          ),
          child: Text(
            label,
            style: context.bodyRegular?.copyWith(
              color: selected ? Colors.white : context.body,
              fontWeight: FontWeight.w600,
              fontSize: 12.sp,
            ),
          ),
        ),
      ),
    );
  }
}

class _PartnerTile extends StatelessWidget {
  final MeetingPartner partner;
  final VoidCallback onTap;

  const _PartnerTile({required this.partner, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final subtitle = partner.subtitle;
    final initial =
        partner.name.isNotEmpty ? partner.name[0].toUpperCase() : '?';

    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(12.r),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12.r),
        child: Padding(
          padding: EdgeInsets.symmetric(horizontal: 12.w, vertical: 12.h),
          child: Row(
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(8.r),
                child: SizedBox(
                  width: 44.sp,
                  height: 44.sp,
                  child: partner.avatarUrl != null &&
                          partner.avatarUrl!.isNotEmpty
                      ? CustomImage(
                          partner.avatarUrl!,
                          fit: BoxFit.cover,
                          width: 44.sp,
                          height: 44.sp,
                        )
                      : ColoredBox(
                          color: context.primaryFocused,
                          child: Center(
                            child: Text(
                              initial,
                              style: TextStyle(
                                color: context.primaryTheme,
                                fontWeight: FontWeight.w700,
                                fontSize: 16.sp,
                              ),
                            ),
                          ),
                        ),
                ),
              ),
              SizedBox(width: 12.w),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      partner.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: context.bodyRegular?.copyWith(
                        fontWeight: FontWeight.w700,
                        color: context.heading,
                      ),
                    ),
                    if (subtitle.isNotEmpty) ...[
                      SizedBox(height: 2.h),
                      Text(
                        subtitle,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: context.specialCaption2?.copyWith(
                          color: context.caption,
                          fontSize: 12.sp,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              Icon(
                Icons.chevron_right,
                color: context.ghost,
                size: 22.sp,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
