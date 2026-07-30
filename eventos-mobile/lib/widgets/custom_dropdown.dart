import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import '../models/registration_item.dart';
import '../utils/extension/theme_ext.dart';

class CustomDropdown extends StatelessWidget {
  final RegistrationItem item;
  final Function(String) onSelected;
  const CustomDropdown({
    super.key,
    required this.item,
    required this.onSelected,
  });

  @override
  Widget build(BuildContext context) {
    final RxnString selectedValue = RxnString();

    return LayoutBuilder(
      builder: (context, constraints) {
        return Container(
          height: 48.h,
          padding: EdgeInsets.symmetric(horizontal: 12.w),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: context.ghost),
          ),
          child: PopupMenuButton<String>(
            constraints: BoxConstraints(
              minWidth: constraints.maxWidth,
              maxWidth: constraints.maxWidth,
            ),
            color: context.strokeLight,
            position: PopupMenuPosition.under,
            offset: Offset(-12.w, 4.h),
            onSelected: (value) {
              selectedValue.value = value;
              onSelected(value);
            },
            itemBuilder: (context) {
              return item.options.map((option) {
                return PopupMenuItem<String>(
                  value: option,
                  child: Text(option, style: context.titleRegular),
                );
              }).toList();
            },
            child: Obx(
              () => Row(
                children: [
                  Expanded(
                    child: Text(
                      selectedValue.value ?? "Select ${item.fieldLabel}",
                      style: context.bodyRegular?.copyWith(
                        color: selectedValue.value == null
                            ? context.caption
                            : context.heading,
                      ),
                    ),
                  ),
                  SizedBox(width: 12.w),
                  Icon(Icons.keyboard_arrow_down, color: context.ghost),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}
