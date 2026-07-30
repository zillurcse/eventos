import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

import '../../../models/lounge_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../utils/theme/app_colors.dart';
import '../../../widgets/custom_image.dart';

/// Cozy lounge chair graphic (PNG parts + optional avatar).
/// Seat SVGs from Figma embed PNGs that flutter_svg can't paint, so we use
/// the extracted PNGs under assets/png/lounge/.
class LoungeSeatUnit extends StatelessWidget {
  final LoungeOccupant? occupant;
  final bool isMe;
  final bool full;
  final double rotateDeg;
  final VoidCallback? onTap;

  const LoungeSeatUnit({
    super.key,
    required this.occupant,
    this.isMe = false,
    this.full = false,
    this.rotateDeg = 0,
    this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final taken = occupant != null;
    final empty = !taken && !full;

    return GestureDetector(
      onTap: (empty || isMe) ? onTap : null,
      behavior: HitTestBehavior.opaque,
      child: SizedBox(
        width: 78.w,
        height: 68.h,
        child: Stack(
          clipBehavior: Clip.none,
          alignment: Alignment.center,
          children: [
            Transform.rotate(
              angle: rotateDeg * 3.141592653589793 / 180,
              child: SizedBox(
                width: 78.w,
                height: 68.h,
                child: Stack(
                  clipBehavior: Clip.none,
                  children: [
                    Positioned(
                      top: 14.h,
                      left: 0,
                      right: 0,
                      child: Center(
                        child: Image.asset(
                          'assets/png/lounge/seat-middle.png',
                          width: 55.w,
                          height: 50.h,
                          fit: BoxFit.contain,
                          filterQuality: FilterQuality.high,
                        ),
                      ),
                    ),
                    Positioned(
                      top: 0,
                      left: 0,
                      right: 0,
                      child: Center(
                        child: Image.asset(
                          'assets/png/lounge/seat-top.png',
                          width: 56.w,
                          height: 22.h,
                          fit: BoxFit.contain,
                          filterQuality: FilterQuality.high,
                        ),
                      ),
                    ),
                    Positioned(
                      top: 6.h,
                      left: 2.w,
                      child: Image.asset(
                        'assets/png/lounge/seat-left.png',
                        width: 12.w,
                        height: 54.h,
                        fit: BoxFit.contain,
                        filterQuality: FilterQuality.high,
                      ),
                    ),
                    Positioned(
                      top: 6.h,
                      right: 2.w,
                      child: Image.asset(
                        'assets/png/lounge/seat-right.png',
                        width: 12.w,
                        height: 54.h,
                        fit: BoxFit.contain,
                        filterQuality: FilterQuality.high,
                      ),
                    ),
                  ],
                ),
              ),
            ),
            if (taken)
              Positioned(
                top: rotateDeg.abs() == 180 ? 8.h : 24.h,
                child: Container(
                  width: 34.sp,
                  height: 34.sp,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    border: Border.all(
                      color: isMe ? greenPositive : Colors.white,
                      width: 2,
                    ),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.18),
                        blurRadius: 6,
                        offset: const Offset(0, 2),
                      ),
                    ],
                  ),
                  clipBehavior: Clip.antiAlias,
                  child: (occupant!.avatarUrl?.isNotEmpty ?? false)
                      ? CustomImage(
                          occupant!.avatarUrl!,
                          height: 34.sp,
                          width: 34.sp,
                          isCircle: true,
                          avatar: true,
                        )
                      : ColoredBox(
                          color: isMe ? greenPositive : primaryTheme,
                          child: Center(
                            child: Text(
                              occupant!.name.isNotEmpty
                                  ? occupant!.name[0].toUpperCase()
                                  : '?',
                              style: context.specialCaption2?.copyWith(
                                color: Colors.white,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                        ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
