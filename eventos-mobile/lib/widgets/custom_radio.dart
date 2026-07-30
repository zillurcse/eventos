import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import '../utils/extension/theme_ext.dart';

class CustomRadio extends StatelessWidget {
  final List<String> options;
  final Function(String) onSelected;
  final RxnString selectedValue = RxnString();

  CustomRadio({super.key, required this.options, required this.onSelected});

  @override
  Widget build(BuildContext context) {
    return Column(
      children: options.map((option) {
        return InkWell(
          onTap: () {
            selectedValue.value = option;
            onSelected(option);
          },
          child: Obx(() {
            final isSelected = selectedValue.value == option;

            return Container(
              margin: EdgeInsets.symmetric(vertical: 4.h),
              padding: EdgeInsets.symmetric(vertical: 12.h, horizontal: 12.w),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(8.r),
                border: Border.all(
                  color: isSelected ? context.primaryTheme : context.ghost,
                  width: 1.5.sp,
                ),
              ),
              child: Row(
                children: [
                  Container(
                    height: 20.sp,
                    width: 20.sp,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      border: Border.all(
                        color: isSelected
                            ? context.primaryTheme
                            : context.ghost,
                        width: 2.sp,
                      ),
                    ),
                    child: Center(
                      child: Container(
                        height: 10.sp,
                        width: 10.sp,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: isSelected
                              ? context.primaryTheme
                              : Colors.transparent,
                        ),
                      ),
                    ),
                  ),
                  SizedBox(width: 12.w),
                  Expanded(
                    child: Text(
                      option,
                      style: context.bodyRegular?.copyWith(
                        color: isSelected ? context.primaryTheme : null,
                      ),
                    ),
                  ),
                ],
              ),
            );
          }),
        );
      }).toList(),
    );
  }
}
