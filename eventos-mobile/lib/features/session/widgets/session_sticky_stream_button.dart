import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../../models/session_detail_response_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../session_controller.dart';

class SessionStickyStreamButton extends StatelessWidget {
  final SessionDetailModel detail;

  const SessionStickyStreamButton({super.key, required this.detail});

  Future<void> _openStream(BuildContext context, String? streamUrl) async {
    final ctrl = Get.find<SessionController>();
    if (streamUrl == null || streamUrl.isEmpty) {
      Get.snackbar(
        'Stream Info',
        'Stream link is not available yet.',
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: ctrl.days.isEmpty ? Colors.black : context.primaryTheme.withValues(alpha: 0.8),
        colorText: Colors.white,
      );
      return;
    }
    final uri = Uri.tryParse(streamUrl);
    if (uri != null) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (!detail.isStream) return const SizedBox.shrink();

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
          onPressed: () => _openStream(context, detail.streamLink),
          style: ElevatedButton.styleFrom(
            backgroundColor: context.primaryTheme,
            padding: EdgeInsets.symmetric(vertical: 14.h),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(8.r),
            ),
          ),
          child: Text(
            'Join Now',
            style: context.buttonMediumBold?.copyWith(color: Colors.white),
          ),
        ),
      ),
    );
  }
}
