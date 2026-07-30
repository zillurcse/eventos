import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import '../utils/extension/theme_ext.dart';

class CustomCheckbox extends StatelessWidget {
  final List<String> options;
  final Function(List<String>) onSelected;
  final RxList<String> selectedValues = <String>[].obs;

  CustomCheckbox({super.key, required this.options, required this.onSelected});

  @override
  Widget build(BuildContext context) {
    return Column(
      children: options.map((option) {
        return InkWell(
          onTap: () {
            if (selectedValues.contains(option)) {
              selectedValues.remove(option);
            } else {
              selectedValues.add(option);
            }
            onSelected(selectedValues.toList());
          },
          child: Padding(
            padding: EdgeInsets.symmetric(vertical: 8.h),
            child: Obx(() {
              final isSelected = selectedValues.contains(option);

              return Row(
                children: [
                  Container(
                    height: 20.sp,
                    width: 20.sp,
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(4.r),
                      border: Border.all(
                        color: isSelected ? context.primaryTheme : context.ghost,
                        width: 2,
                      ),
                      color: isSelected ? context.primaryTheme : Colors.transparent,
                    ),
                    child: isSelected
                        ? Icon(Icons.check, size: 14.h, color: Colors.white)
                        : null,
                  ),
                  SizedBox(width: 12.w),
                  Text(
                    option,
                    style: context.titleRegular?.copyWith(
                      color: isSelected ? context.primaryTheme : context.titleRegular?.color,
                    ),
                  ),
                ],
              );
            }),
          ),
        );
      }).toList(),
    );
  }
}