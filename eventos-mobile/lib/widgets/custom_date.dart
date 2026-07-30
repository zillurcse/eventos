import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import '../utils/extension/theme_ext.dart';

class CustomDate extends StatelessWidget {
  final String label;
  final Function(DateTime) onSelected;
  const CustomDate({super.key, required this.label, required this.onSelected});

  @override
  Widget build(BuildContext context) {
    final Rxn<DateTime> selectedDate = Rxn<DateTime>();

    return InkWell(
      onTap: () async {
        final date = await showDatePicker(
          context: context,
          initialDate: DateTime.now(),
          firstDate: DateTime(1900),
          lastDate: DateTime(2100),
          builder: (context, child) {
            return Theme(
              data: Theme.of(context).copyWith(
                colorScheme: ColorScheme.light(
                  primary: context.primaryTheme, // header background color
                  onPrimary: Colors.white, // header text color
                  onSurface: context.heading, // body text color
                  surface: context.strokeLight, // background color
                ),
                textButtonTheme: TextButtonThemeData(
                  style: TextButton.styleFrom(
                    foregroundColor: context.primaryTheme, // button text color
                  ),
                ),
              ),
              child: child!,
            );
          },
        );
        if (date != null) {
          selectedDate.value = date;
          onSelected(date);
        }
      },
      child: Container(
        height: 48.h,
        padding: EdgeInsets.symmetric(horizontal: 12.w),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: context.ghost),
        ),
        child: Obx(
          () => Row(
            children: [
              Expanded(
                child: Text(
                  selectedDate.value == null
                      ? "Select $label"
                      : "${selectedDate.value!.day}/${selectedDate.value!.month}/${selectedDate.value!.year}",
                  style: context.bodyRegular?.copyWith(
                    color: selectedDate.value == null
                        ? context.caption
                        : context.heading,
                  ),
                ),
              ),
              Icon(
                Icons.calendar_month_outlined,
                color: context.ghost,
                size: 20.sp,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
