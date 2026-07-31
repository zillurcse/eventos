import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../../../models/participant_badge_model.dart';
import '../../../utils/enum/enums.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/state_handler/api_state_handler.dart';
import 'badges_controller.dart';
import 'widgets/badge_render.dart';

/// My Badges — the attendee's own pass(es) for this event.
///
/// Matches `eventos-event/app/pages/badges.vue`: one card per participation,
/// design rendered with merged data, Show QR overlay, and front/back flip.
class BadgesView extends StatefulWidget {
  const BadgesView({super.key});

  @override
  State<BadgesView> createState() => _BadgesViewState();
}

class _BadgesViewState extends State<BadgesView> {
  late final BadgesController controller;

  @override
  void initState() {
    super.initState();
    controller = Get.isRegistered<BadgesController>()
        ? Get.find<BadgesController>()
        : Get.put(BadgesController());
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (controller.dataStatus.value == ApiState.initial ||
          controller.badges.isEmpty) {
        controller.fetchBadges();
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF7F7FB),
      body: Stack(
        children: [
          Obx(
            () => ApiStateHandler(
              state: controller.dataStatus.value,
              onRetry: controller.fetchBadges,
              loadedElement: controller.badges.isEmpty
                  ? _EmptyState(
                      message:
                          "The organizers haven't published a badge for this event yet.",
                    )
                  : RefreshIndicator(
                      onRefresh: controller.fetchBadges,
                      child: ListView.separated(
                        padding: EdgeInsets.fromLTRB(16.w, 16.h, 16.w, 24.h),
                        itemCount: controller.badges.length,
                        separatorBuilder: (_, _) => SizedBox(height: 20.h),
                        itemBuilder: (context, index) {
                          final badge = controller.badges[index];
                          return _BadgeCard(
                            badge: badge,
                            flipped: controller.isFlipped(badge.participationId),
                            onFlip: () =>
                                controller.toggleFlip(badge.participationId),
                            onShowQr: () => controller.showQr(badge),
                          );
                        },
                      ),
                    ),
            ),
          ),
          Obx(() {
            final scanning = controller.scanning;
            if (scanning == null) return const SizedBox.shrink();
            return _QrOverlay(
              badge: scanning,
              onClose: controller.closeQr,
            );
          }),
        ],
      ),
    );
  }
}

class _EmptyState extends StatelessWidget {
  final String message;

  const _EmptyState({required this.message});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: EdgeInsets.symmetric(horizontal: 32.w),
        child: Text(
          message,
          textAlign: TextAlign.center,
          style: context.bodyLarge?.copyWith(color: context.caption),
        ),
      ),
    );
  }
}

class _BadgeCard extends StatelessWidget {
  final ParticipantBadge badge;
  final bool flipped;
  final VoidCallback onFlip;
  final VoidCallback onShowQr;

  const _BadgeCard({
    required this.badge,
    required this.flipped,
    required this.onFlip,
    required this.onShowQr,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.all(16.w),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16.r),
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Column(
        children: [
          DecoratedBox(
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(12.r),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.12),
                  blurRadius: 16,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(12.r),
              child: BadgeRender(
                badgeJson: badge.design.badgeJson,
                data: badge.data,
                side: flipped ? 'back' : 'front',
                maxWidth: 300.w,
                maxHeight: 420.h,
              ),
            ),
          ),
          SizedBox(height: 12.h),
          Text(
            badge.roleLabel.toUpperCase(),
            style: TextStyle(
              fontSize: 11.sp,
              fontWeight: FontWeight.w700,
              letterSpacing: 0.6,
              color: context.primaryTheme,
            ),
          ),
          SizedBox(height: 2.h),
          Text(
            badge.fullName,
            style: context.titleRegular?.copyWith(fontWeight: FontWeight.w600),
            textAlign: TextAlign.center,
          ),
          SizedBox(height: 12.h),
          Wrap(
            spacing: 8.w,
            runSpacing: 8.h,
            alignment: WrapAlignment.center,
            children: [
              _ActionButton(
                label: 'Show QR',
                primary: true,
                onTap: onShowQr,
              ),
              if (badge.hasBack)
                _ActionButton(
                  label: flipped ? 'Show front' : 'Show back',
                  onTap: onFlip,
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class _ActionButton extends StatelessWidget {
  final String label;
  final bool primary;
  final VoidCallback onTap;

  const _ActionButton({
    required this.label,
    required this.onTap,
    this.primary = false,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: primary ? context.primaryTheme : Colors.white,
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(999),
        child: Container(
          padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 8.h),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(999),
            border: Border.all(
              color: primary ? context.primaryTheme : const Color(0xFFE5E7EB),
            ),
          ),
          child: Text(
            label,
            style: TextStyle(
              fontSize: 13.sp,
              fontWeight: FontWeight.w600,
              color: primary ? Colors.white : const Color(0xFF1E293B),
            ),
          ),
        ),
      ),
    );
  }
}

/// Full-screen bright QR for gate scanning — opaque white, large code.
class _QrOverlay extends StatefulWidget {
  final ParticipantBadge badge;
  final VoidCallback onClose;

  const _QrOverlay({required this.badge, required this.onClose});

  @override
  State<_QrOverlay> createState() => _QrOverlayState();
}

class _QrOverlayState extends State<_QrOverlay> {
  @override
  void initState() {
    super.initState();
    SystemChrome.setSystemUIOverlayStyle(SystemUiOverlayStyle.dark);
  }

  @override
  Widget build(BuildContext context) {
    final size = (MediaQuery.sizeOf(context).shortestSide * 0.78).clamp(220.0, 380.0);

    return Material(
      color: Colors.white,
      child: SafeArea(
        child: GestureDetector(
          onTap: widget.onClose,
          behavior: HitTestBehavior.opaque,
          child: Center(
            child: GestureDetector(
              onTap: () {}, // swallow so only backdrop closes
              child: Padding(
                padding: EdgeInsets.symmetric(horizontal: 24.w),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    QrImageView(
                      data: widget.badge.qrcode,
                      version: QrVersions.auto,
                      size: size,
                      backgroundColor: Colors.white,
                      padding: EdgeInsets.zero,
                    ),
                    SizedBox(height: 16.h),
                    Text(
                      widget.badge.fullName,
                      style: TextStyle(
                        fontSize: 18.sp,
                        fontWeight: FontWeight.w700,
                        color: const Color(0xFF1E293B),
                      ),
                      textAlign: TextAlign.center,
                    ),
                    SizedBox(height: 4.h),
                    Text(
                      '${widget.badge.roleLabel} · ${widget.badge.eventName}',
                      style: TextStyle(
                        fontSize: 13.sp,
                        color: const Color(0xFF6B7280),
                      ),
                      textAlign: TextAlign.center,
                    ),
                    SizedBox(height: 16.h),
                    _ActionButton(label: 'Close', onTap: widget.onClose),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
