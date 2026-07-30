import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import '../../../utils/extension/theme_ext.dart';
import '../profile_controller.dart';

class InterestTab extends StatefulWidget {
  const InterestTab({super.key});

  @override
  State<InterestTab> createState() => _InterestTabState();
}

class _InterestTabState extends State<InterestTab> {
  final List<String> _interests = [
    "Insurance",
    "Banking",
    "Finance",
    "Graphic Arts",
    "Healthcare",
    "Investment",
    "Hospitality",
    "Computer Science",
    "Consulting",
    "Design",
    "Human Resources",
    "Agriculture",
  ];

  final Set<String> _selectedInterests = {};

  @override
  void initState() {
    super.initState();
    final profile = Get.find<ProfileController>().profileData.value;
    if (profile?.interests != null && profile!.interests!.isNotEmpty) {
      _selectedInterests.addAll(
        profile.interests!.split(',').map((e) => e.trim()).where((e) => e.isNotEmpty),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: EdgeInsets.symmetric(horizontal: 20.w, vertical: 24.h),
      child: Column(
        children: [
          // Search Box
          TextField(
            style: context.bodyRegular?.copyWith(color: context.heading),
            decoration: InputDecoration(
              hintText: "Search...",
              hintStyle: context.bodyRegular?.copyWith(color: context.ghost),
              prefixIcon: Icon(
                Icons.search,
                color: context.caption,
                size: 24.sp,
              ),
              contentPadding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 12.h),
              filled: true,
              fillColor: Colors.white,
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(8.r),
                borderSide: BorderSide(color: context.strokeLight, width: 1),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(8.r),
                borderSide: BorderSide(color: context.primaryTheme, width: 1),
              ),
            ),
          ),
          SizedBox(height: 24.h),

          // Grid of Interests
          GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 2,
              crossAxisSpacing: 12.w,
              mainAxisSpacing: 12.h,
              childAspectRatio: 3.5, // Adjust for pill shape
            ),
            itemCount: _interests.length,
            itemBuilder: (context, index) {
              final item = _interests[index];
              final isSelected = _selectedInterests.contains(item);

              return GestureDetector(
                onTap: () {
                  setState(() {
                    if (isSelected) {
                      _selectedInterests.remove(item);
                    } else {
                      _selectedInterests.add(item);
                    }
                    Get.find<ProfileController>().selectedInterests.value = _selectedInterests.toList();
                  });
                },
                child: Container(
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: isSelected ? context.primaryFocused : Colors.white,
                    borderRadius: BorderRadius.circular(8.r),
                    border: Border.all(
                      color: isSelected ? context.primaryTheme : context.strokeLight,
                      width: 1,
                    ),
                  ),
                  child: Text(
                    item,
                    textAlign: TextAlign.center,
                    style: context.bodyRegular?.copyWith(
                      color: isSelected ? context.primaryTheme : context.caption,
                      fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                    ),
                  ),
                ),
              );
            },
          ),
        ],
      ),
    );
  }
}
