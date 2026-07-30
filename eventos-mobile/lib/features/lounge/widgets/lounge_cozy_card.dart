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
import 'lounge_seat_unit.dart';

/// Cozy Lounge card — chair seats around a centerpiece + Select a seat CTA.
class LoungeCozyCard extends StatelessWidget {
  final LoungeTable table;

  const LoungeCozyCard({super.key, required this.table});

  bool get _isPair => table.capacity == 2;
  bool get _isDiamond => table.capacity >= 3 && table.capacity <= 4;

  @override
  Widget build(BuildContext context) {
    final ctrl = Get.find<LoungeController>();
    final seats = LoungeSeatHelper.occupantBySeat(table);
    final available = math.max(0, table.capacity - table.occupied);

    return Obx(() {
      final joining = ctrl.joiningId.value == table.id;
      final seatedHere = ctrl.activeTableId.value == table.id;

      return Container(
        margin: EdgeInsets.only(bottom: 14.h),
        padding: EdgeInsets.fromLTRB(16.w, 18.h, 16.w, 16.h),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16.r),
          border: seatedHere
              ? Border.all(color: greenPositive, width: 2)
              : null,
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.05),
              blurRadius: 12,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Column(
          children: [
            Text(
              table.name,
              textAlign: TextAlign.center,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: context.h2?.copyWith(fontWeight: FontWeight.w800),
            ),
            SizedBox(height: 6.h),
            Text(
              LoungeSeatHelper.kindLabel(table.kind),
              textAlign: TextAlign.center,
              style: context.bodyRegular?.copyWith(color: context.caption),
            ),
            SizedBox(height: 12.h),
            Container(
              padding: EdgeInsets.symmetric(horizontal: 12.w, vertical: 5.h),
              decoration: BoxDecoration(
                color: table.full
                    ? redErrorLight
                    : const Color(0xFFFFF4D6),
                borderRadius: BorderRadius.circular(20.r),
              ),
              child: Text(
                table.full
                    ? 'Table full'
                    : '$available/${table.capacity} seat available',
                style: context.specialCaption2?.copyWith(
                  color: table.full ? redError : const Color(0xFFB8860B),
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
            SizedBox(height: 16.h),
            _seatingArea(context, ctrl, seats),
            SizedBox(height: 16.h),
            SizedBox(
              width: double.infinity,
              height: 44.h,
              child: ElevatedButton(
                onPressed: table.full || joining
                    ? null
                    : () {
                        if (seatedHere) return;
                        final empty = seats.indexWhere((o) => o == null);
                        ctrl.joinTable(
                          table,
                          seat: empty >= 0 ? empty : null,
                        );
                      },
                style: ElevatedButton.styleFrom(
                  backgroundColor: primaryTheme,
                  foregroundColor: Colors.white,
                  disabledBackgroundColor: context.stroke,
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10.r),
                  ),
                ),
                child: joining
                    ? SizedBox(
                        width: 18.sp,
                        height: 18.sp,
                        child: const CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : Text(
                        seatedHere
                            ? 'Seated here'
                            : (table.full ? 'Full' : 'Select a seat'),
                        style: context.buttonLabelLarge?.copyWith(
                          color: Colors.white,
                        ),
                      ),
              ),
            ),
          ],
        ),
      );
    });
  }

  Widget _seatingArea(
    BuildContext context,
    LoungeController ctrl,
    List<LoungeOccupant?> seats,
  ) {
    void onSeat(int i) {
      final o = seats[i];
      if (o != null) return;
      if (!table.full) ctrl.joinTable(table, seat: i);
    }

    LoungeSeatUnit unit(int i, {double rotate = 0}) {
      final o = i < seats.length ? seats[i] : null;
      return LoungeSeatUnit(
        occupant: o,
        isMe: o?.identity == ctrl.meId,
        full: table.full,
        rotateDeg: rotate,
        onTap: () => onSeat(i),
      );
    }

    Widget plant() => Image.asset(
          'assets/png/lounge/seat-plant.png',
          width: 36.w,
          height: 36.w,
          fit: BoxFit.contain,
          filterQuality: FilterQuality.high,
        );

    Widget centerpiece() {
      final url = table.imageUrl ?? '';
      return Container(
        width: 72.sp,
        height: 72.sp,
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14.r),
          border: Border.all(color: context.strokeLight),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.06),
              blurRadius: 8,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        clipBehavior: Clip.antiAlias,
        child: url.isNotEmpty
            ? CustomImage(url, fit: BoxFit.cover, width: 72.sp, height: 72.sp)
            : Center(
                child: CustomImage(
                  'assets/svg/icons/logo-primary.png',
                  width: 40.sp,
                  height: 40.sp,
                  fit: BoxFit.contain,
                ),
              ),
      );
    }

    if (_isPair) {
      return SizedBox(
        height: 220.h,
        child: Stack(
          alignment: Alignment.center,
          children: [
            Positioned(top: 0, left: 8.w, child: plant()),
            Positioned(bottom: 0, right: 8.w, child: plant()),
            Column(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                unit(0),
                centerpiece(),
                unit(1, rotate: 180),
              ],
            ),
          ],
        ),
      );
    }

    if (_isDiamond) {
      return SizedBox(
        height: 240.h,
        child: Stack(
          alignment: Alignment.center,
          children: [
            Positioned(top: 0, left: 4.w, child: plant()),
            Positioned(bottom: 0, right: 4.w, child: plant()),
            Column(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                if (table.capacity >= 1) unit(0),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                  children: [
                    if (table.capacity >= 2) unit(1, rotate: -90),
                    centerpiece(),
                    if (table.capacity >= 3) unit(2, rotate: 90),
                  ],
                ),
                if (table.capacity >= 4) unit(3, rotate: 180),
              ],
            ),
          ],
        ),
      );
    }

    // Larger tables — two rows
    final topN = (table.capacity / 2).ceil();
    return Column(
      children: [
        Wrap(
          alignment: WrapAlignment.center,
          spacing: 4.w,
          children: [
            for (var i = 0; i < topN; i++) unit(i),
          ],
        ),
        SizedBox(height: 10.h),
        centerpiece(),
        SizedBox(height: 10.h),
        Wrap(
          alignment: WrapAlignment.center,
          spacing: 4.w,
          children: [
            for (var i = topN; i < table.capacity; i++)
              unit(i, rotate: 180),
          ],
        ),
      ],
    );
  }
}
