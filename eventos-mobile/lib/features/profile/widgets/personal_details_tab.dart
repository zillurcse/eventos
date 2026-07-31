import 'package:expouse/widgets/custom_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import '../../../utils/extension/theme_ext.dart';
import '../profile_controller.dart';
import 'edit_photo_modal.dart';

class PersonalDetailsTab extends StatelessWidget {
  const PersonalDetailsTab({super.key});

  void _showEditPhotoModal(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => const EditPhotoModal(),
    );
  }

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<ProfileController>();
    final profile = controller.profileData.value;

    return SingleChildScrollView(
      padding: EdgeInsets.symmetric(horizontal: 20.w, vertical: 24.h),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Profile Photo Area
          Center(
            child: Stack(
              clipBehavior: Clip.none,
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(12.r),
                  child: CustomImage(
                    profile?.profilePhotoUrl ?? "",
                    width: 100.w,
                    height: 100.w,
                    fit: BoxFit.cover,
                    avatar: true,
                  ),
                ),
                Positioned(
                  bottom: -10.h,
                  right: -10.w,
                  child: GestureDetector(
                    onTap: () => _showEditPhotoModal(context),
                    child: Container(
                      padding: EdgeInsets.all(6.sp),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(8.r),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withValues(alpha: 0.1),
                            blurRadius: 4,
                            offset: const Offset(0, 2),
                          ),
                        ],
                      ),
                      child: Icon(
                        Icons.camera_alt_outlined,
                        size: 18.sp,
                        color: context.primaryTheme,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
          SizedBox(height: 32.h),

          // Form Fields
          _buildLabeledField(
            context: context,
            label: "First Name",
            isRequired: true,
            child: _buildTextField(context, controller: controller.firstNameCtrl),
          ),
          _buildLabeledField(
            context: context,
            label: "Last Name",
            isRequired: true,
            child: _buildTextField(context, controller: controller.lastNameCtrl),
          ),
          _buildLabeledField(
            context: context,
            label: "About",
            child: _buildTextField(
              context,
              controller: controller.aboutCtrl,
              hint: "Enter about your self",
              maxLines: 4,
            ),
          ),
          _buildLabeledField(
            context: context,
            label: "Gender",
            child: Obx(() => _buildDropdown(
              context: context,
              hint: "Select Gender",
              value: controller.selectedGender.value,
              items: const [
                "Male",
                "Female",
                "Non-binary",
                "Prefer not to say",
              ],
              onChanged: (val) => controller.selectedGender.value = val,
            )),
          ),
          _buildLabeledField(
            context: context,
            label: "Designation",
            child: _buildTextField(context, controller: controller.designationCtrl, hint: "Enter Designation"),
          ),
          _buildLabeledField(
            context: context,
            label: "Organisation",
            child: _buildTextField(context, controller: controller.companyCtrl, hint: "Enter Organisation"),
          ),
          _buildLabeledField(
            context: context,
            label: "Mobile Number",
            child: _buildTextField(context, controller: controller.mobileNumberCtrl, hint: "Enter Mobile Number"),
          ),
          _buildLabeledField(
            context: context,
            label: "Address",
            child: _buildTextField(context, controller: controller.addressCtrl, hint: "Enter Address"),
          ),
          _buildLabeledField(
            context: context,
            label: "Country",
            child: Obx(() {
              // Depend on geoReady so options appear after JSON load.
              final ready = controller.geoReady.value;
              return _buildDropdown(
                context: context,
                hint: ready ? "Select Country" : "Loading countries…",
                value: controller.selectedCountry.value,
                items: controller.availableCountries,
                onChanged: ready
                    ? (val) => controller.updateCountry(val)
                    : null,
              );
            }),
          ),
          _buildLabeledField(
            context: context,
            label: "State",
            child: Obx(() {
              final _ = controller.geoReady.value;
              final states = controller.availableStates;
              String? currentValue = controller.selectedState.value;
              if (currentValue != null && !states.contains(currentValue)) {
                currentValue = null;
                WidgetsBinding.instance.addPostFrameCallback((_) {
                  controller.updateState(null);
                });
              }
              
              return _buildDropdown(
                context: context,
                hint: "Select State",
                value: currentValue,
                items: states,
                onChanged: (val) => controller.updateState(val),
              );
            }),
          ),
          _buildLabeledField(
            context: context,
            label: "City/Town",
            child: _buildTextField(context, controller: controller.cityTownCtrl, hint: "Enter City/Town"),
          ),
          _buildLabeledField(
            context: context,
            label: "Website",
            child: _buildTextField(context, controller: controller.websiteCtrl, hint: "https://"),
          ),
          _buildLabeledField(
            context: context,
            label: "Purpose of Visit",
            child: _buildTextField(context, controller: controller.purposeOfVisitCtrl, hint: "Enter Purpose of Visit"),
          ),
          _buildLabeledField(
            context: context,
            label: "Purchasing Decision",
            child: _buildTextField(context, controller: controller.purchasingDecisionCtrl, hint: "Enter Purchasing Decision"),
          ),
        ],
      ),
    );
  }

  Widget _buildLabeledField({
    required BuildContext context,
    required String label,
    bool isRequired = false,
    required Widget child,
  }) {
    return Padding(
      padding: EdgeInsets.only(bottom: 16.h),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Text(
                label,
                style: context.bodyRegular?.copyWith(
                  color: context.caption,
                  fontSize: 13.sp,
                ),
              ),
              if (isRequired)
                Text(
                  "*",
                  style: context.bodyRegular?.copyWith(
                    color: Colors.red,
                    fontSize: 13.sp,
                  ),
                ),
            ],
          ),
          SizedBox(height: 6.h),
          child,
        ],
      ),
    );
  }

  Widget _buildTextField(
    BuildContext context, {
    TextEditingController? controller,
    String? hint,
    int maxLines = 1,
  }) {
    return TextFormField(
      controller: controller,
      maxLines: maxLines,
      style: context.bodyRegular?.copyWith(color: context.heading),
      decoration: InputDecoration(
        hintText: hint,
        hintStyle: context.bodyRegular?.copyWith(color: context.ghost),
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
    );
  }

  Widget _buildDropdown({
    required BuildContext context,
    required String hint,
    String? value,
    List<String> items = const [],
    Function(String?)? onChanged,
  }) {
    // Empty / unknown API values must not be passed to DropdownButton —
    // Flutter asserts the value is either null or exactly one of [items].
    final effectiveValue =
        (value != null && value.isNotEmpty && items.contains(value))
            ? value
            : null;

    return DropdownButtonFormField<String>(
      value: effectiveValue,
      dropdownColor: Colors.white,
      decoration: InputDecoration(
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
      icon: Icon(Icons.keyboard_arrow_down, color: context.caption),
      hint: Text(
        hint,
        style: context.bodyRegular?.copyWith(color: context.ghost),
      ),
      items: items
          .map(
            (e) => DropdownMenuItem(
              value: e,
              child: Text(
                e,
                style: context.bodyRegular?.copyWith(color: context.heading),
              ),
            ),
          )
          .toList(),
      onChanged: onChanged,
    );
  }
}
