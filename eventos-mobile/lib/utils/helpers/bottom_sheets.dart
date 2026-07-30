import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get_core/src/get_main.dart';
import 'package:get/get_navigation/get_navigation.dart';

import '../../widgets/bottom_sheet_frame.dart';

BuildContext? context = Get.context;

void addNoteBottomSheet({required Widget child}) {
  if (context == null) return;
  showModalBottomSheet(
    context: context!,
    isScrollControlled: true,
    backgroundColor: Colors.transparent,
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(20.r)),
    ),
    builder: (ctx) {
      return BottomSheetFrame(child: child);
    },
  );
}

void showMoreBottomSheet({required Widget child}) {
  if (context == null) return;
  showModalBottomSheet(
    context: context!,
    isScrollControlled: true,
    backgroundColor: Colors.transparent,
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(20.r)),
    ),
    builder: (ctx) {
      return BottomSheetFrame(child: child);
    },
  );
}
