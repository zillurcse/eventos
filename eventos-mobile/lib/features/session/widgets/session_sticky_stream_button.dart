import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../../../models/session_detail_response_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../session_phase.dart';
import 'session_player.dart';

class SessionStickyStreamButton extends StatelessWidget {
  final SessionDetailModel detail;

  const SessionStickyStreamButton({super.key, required this.detail});

  String? _label(SessionPhase phase) {
    if (phase == SessionPhase.upcoming) {
      return 'Starts soon';
    }
    if (phase == SessionPhase.ended) {
      if (detail.onDemandRecordingLink != null &&
          detail.onDemandRecordingLink!.isNotEmpty) {
        return 'Watch Recording';
      }
      return null;
    }
    if (!detail.isStream) return null;
    final host = detail.whoWillHost ?? '';
    if (host == 'youtube') return 'Watch Live';
    if (host == 'zoom') return 'Join Zoom';
    if (host == 'meet') return 'Join Meet';
    if (host == 'jitsi') return 'Join Jitsi';
    return 'Join Now';
  }

  @override
  Widget build(BuildContext context) {
    final phase = SessionPhaseHelper.resolve(
      status: detail.status,
      startsAt: detail.startsAt,
      endsAt: detail.endsAt,
    );
    final label = _label(phase);
    if (label == null) return const SizedBox.shrink();

    final enabled = phase != SessionPhase.upcoming ||
        (detail.streamLink != null && detail.streamLink!.isNotEmpty);

    return SafeArea(
      top: false,
      child: Container(
        width: double.infinity,
        padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 12.h),
        decoration: BoxDecoration(
          color: Colors.white,
          border: Border(top: BorderSide(color: context.strokeLight, width: 1.h)),
        ),
        child: ElevatedButton(
          onPressed: enabled ? () => openSessionStream(detail) : null,
          style: ElevatedButton.styleFrom(
            backgroundColor: context.primaryTheme,
            disabledBackgroundColor: context.primaryTheme.withValues(alpha: 0.4),
            padding: EdgeInsets.symmetric(vertical: 14.h),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(8.r),
            ),
          ),
          child: Text(
            label,
            style: context.buttonMediumBold?.copyWith(color: Colors.white),
          ),
        ),
      ),
    );
  }
}
