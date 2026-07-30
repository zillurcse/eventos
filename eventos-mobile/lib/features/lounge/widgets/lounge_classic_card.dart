import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';

import '../../../models/lounge_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../utils/theme/app_colors.dart';
import '../../../widgets/custom_image.dart';
import '../lounge_controller.dart';
import 'lounge_seat_helper.dart';

/// Classic Lounge card — center image with dashed seat circles around it.
class LoungeClassicCard extends StatelessWidget {
  final LoungeTable table;

  const LoungeClassicCard({super.key, required this.table});

  static const _maxSeats = 8;

  Color get _accent {
    final hex = table.accent?.replaceAll('#', '');
    if (hex != null && (hex.length == 6 || hex.length == 8)) {
      return Color(int.parse('0xff$hex'));
    }
    return primaryTheme;
  }

  @override
  Widget build(BuildContext context) {
    final ctrl = Get.find<LoungeController>();
    final shown = table.capacity.clamp(1, _maxSeats);
    final seats = LoungeSeatHelper.occupantBySeat(table, maxSeats: _maxSeats);
    final positions = LoungeSeatHelper.classicPositions(shown);

    return Obx(() {
      final joining = ctrl.joiningId.value == table.id;

      return Container(
        margin: EdgeInsets.only(bottom: 14.h),
        padding: EdgeInsets.fromLTRB(16.w, 16.h, 16.w, 14.h),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16.r),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.05),
              blurRadius: 12,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              table.name,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: context.h2?.copyWith(fontWeight: FontWeight.w800),
            ),
            SizedBox(height: 4.h),
            Text(
              LoungeSeatHelper.kindLabel(table.kind),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: context.bodyRegular?.copyWith(color: context.caption),
            ),
            SizedBox(height: 14.h),
            AspectRatio(
              aspectRatio: 1.15,
              child: LayoutBuilder(
                builder: (context, constraints) {
                  final seatSize = 46.sp;
                  return Stack(
                    alignment: Alignment.center,
                    children: [
                      Container(
                        width: constraints.maxWidth * 0.46,
                        height: constraints.maxHeight * 0.46,
                        decoration: BoxDecoration(
                          color: _accent.withValues(alpha: 0.06),
                          borderRadius: BorderRadius.circular(12.r),
                          border: Border.all(
                            color: _accent.withValues(alpha: 0.18),
                          ),
                        ),
                        clipBehavior: Clip.antiAlias,
                        child: (table.imageUrl?.isNotEmpty ?? false)
                            ? CustomImage(
                                table.imageUrl!,
                                fit: BoxFit.cover,
                                width: constraints.maxWidth * 0.46,
                                height: constraints.maxHeight * 0.46,
                              )
                            : Center(
                                child: Text(
                                  table.name.isNotEmpty
                                      ? table.name[0].toUpperCase()
                                      : 'T',
                                  style: context.h1?.copyWith(color: _accent),
                                ),
                              ),
                      ),
                      for (var i = 0; i < shown; i++)
                        Positioned(
                          left: constraints.maxWidth * positions[i].x / 100 -
                              seatSize / 2,
                          top: constraints.maxHeight * positions[i].y / 100 -
                              seatSize / 2,
                          child: _seatBubble(
                            context,
                            size: seatSize,
                            occupant: seats[i],
                            isMe: seats[i]?.identity == ctrl.meId,
                            joining: joining,
                            onTap: () {
                              if (seats[i] != null) return;
                              if (!table.full) {
                                ctrl.joinTable(table, seat: i);
                              }
                            },
                          ),
                        ),
                    ],
                  );
                },
              ),
            ),
            SizedBox(height: 10.h),
            Row(
              children: [
                Text(
                  '${table.occupied} / ${table.capacity} seated',
                  style: context.bodyRegular?.copyWith(color: context.caption),
                ),
                const Spacer(),
                if (joining)
                  SizedBox(
                    width: 16.sp,
                    height: 16.sp,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: context.primaryTheme,
                    ),
                  )
                else
                  Text(
                    table.full ? 'Table full' : 'Tap a seat to join',
                    style: context.bodyRegular?.copyWith(
                      color: table.full ? context.ghost : context.body,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
              ],
            ),
          ],
        ),
      );
    });
  }

  Widget _seatBubble(
    BuildContext context, {
    required double size,
    required LoungeOccupant? occupant,
    required bool isMe,
    required bool joining,
    required VoidCallback onTap,
  }) {
    final taken = occupant != null;

    return GestureDetector(
      onTap: joining ? null : onTap,
      child: SizedBox(
        width: size,
        height: size,
        child: CustomPaint(
          painter: taken
              ? null
              : _DashedCirclePainter(color: const Color(0xFFCDD5E0)),
          child: Container(
            margin: EdgeInsets.all(taken ? 0 : 1.5),
            decoration: BoxDecoration(
              color: Colors.white,
              shape: BoxShape.circle,
              border: taken
                  ? Border.all(
                      color: isMe ? greenPositive : primaryTheme,
                      width: 2,
                    )
                  : null,
              boxShadow: taken
                  ? [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.12),
                        blurRadius: 6,
                        offset: const Offset(0, 2),
                      ),
                    ]
                  : null,
            ),
            alignment: Alignment.center,
            child: taken
                ? ClipOval(
                    child: (occupant.avatarUrl?.isNotEmpty ?? false)
                        ? CustomImage(
                            occupant.avatarUrl!,
                            height: size,
                            width: size,
                            isCircle: true,
                            avatar: true,
                          )
                        : ColoredBox(
                            color: isMe ? greenPositive : primaryTheme,
                            child: Center(
                              child: Text(
                                occupant.name.isNotEmpty
                                    ? occupant.name[0].toUpperCase()
                                    : '?',
                                style: TextStyle(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w700,
                                  fontSize: 14.sp,
                                ),
                              ),
                            ),
                          ),
                  )
                : Icon(Icons.add, size: 18.sp, color: const Color(0xFF9AA3B2)),
          ),
        ),
      ),
    );
  }
}

class _DashedCirclePainter extends CustomPainter {
  final Color color;

  _DashedCirclePainter({required this.color});

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2;
    final radius = size.width / 2 - 1;
    const dashCount = 18;
    const dashSweep = 2 * math.pi / dashCount;
    const gapRatio = 0.45;
    final rect = Rect.fromCircle(
      center: Offset(size.width / 2, size.height / 2),
      radius: radius,
    );
    for (var i = 0; i < dashCount; i++) {
      canvas.drawArc(
        rect,
        i * dashSweep,
        dashSweep * (1 - gapRatio),
        false,
        paint,
      );
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
