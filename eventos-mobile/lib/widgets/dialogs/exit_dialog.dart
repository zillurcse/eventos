import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:expouse/utils/extension/theme_ext.dart';

class ExitDialog extends StatelessWidget {
  const ExitDialog({super.key});

  @override
  Widget build(BuildContext context) {
    return Dialog(
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16.r),
      ),
      elevation: 0,
      backgroundColor: Colors.transparent,
      child: Container(
        padding: EdgeInsets.all(20.w),
        decoration: BoxDecoration(
          color: context.tertiaryText,
          shape: BoxShape.rectangle,
          borderRadius: BorderRadius.circular(24.r),
          boxShadow: [
            BoxShadow(
              color: context.caption.withValues(alpha: 0.2),
              blurRadius: 20.r,
              offset: const Offset(0, 10),
            ),
          ],
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              'Warning',
              style: context.h2?.copyWith(
                color: context.heading,
                fontWeight: FontWeight.bold,
              ),
            ),
            SizedBox(height: 12.h),
            Container(
              padding: EdgeInsets.fromLTRB(12.sp, 8.sp, 12.sp, 12.sp),
              decoration: BoxDecoration(
                color: context.redErrorLight,
                shape: BoxShape.circle,
              ),
              child: Icon(
                Icons.warning_amber_rounded,
                color: context.redError,
                size: 36.sp,
              ),
            ),
            SizedBox(height: 8.h),
            Text(
              'Do you want to exit from the app?',
              textAlign: TextAlign.center,
              style: context.bodyRegular?.copyWith(
                color: context.caption,
              ),
            ),
            SizedBox(height: 16.h),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: () => Navigator.of(context).pop(false),
                    style: OutlinedButton.styleFrom(
                      padding: EdgeInsets.symmetric(vertical: 12.h),
                      side: BorderSide(color: context.strokeLight),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(8.r),
                      ),
                    ),
                    child: Text(
                      'Cancel',
                      style: context.buttonMediumBold?.copyWith(
                        color: context.body,
                      ),
                    ),
                  ),
                ),
                SizedBox(width: 12.w),
                Expanded(
                  child: ElevatedButton(
                    onPressed: () => Navigator.of(context).pop(true),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: context.redError,
                      foregroundColor: context.tertiaryText,
                      elevation: 0,
                      padding: EdgeInsets.symmetric(vertical: 12.h),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(8.r),
                      ),
                    ),
                    child: Text(
                      'Exit',
                      style: context.buttonMediumBold?.copyWith(
                        color: context.tertiaryText,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

Future<bool?> showExitDialog(BuildContext context) {
  return showGeneralDialog<bool>(
    context: context,
    barrierDismissible: true,
    barrierLabel: '',
    barrierColor: Colors.black.withValues(alpha: 0.4),
    transitionDuration: const Duration(milliseconds: 200),
    pageBuilder: (context, anim1, anim2) => const ExitDialog(),
    transitionBuilder: (context, anim1, anim2, child) {
      return Transform.scale(
        scale: anim1.value,
        child: FadeTransition(
          opacity: anim1,
          child: child,
        ),
      );
    },
  );
}
