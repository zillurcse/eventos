import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import 'package:file_picker/file_picker.dart';
import '../../../utils/config/app_config.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_image.dart';
import '../profile_controller.dart';

class EditPhotoModal extends StatefulWidget {
  const EditPhotoModal({super.key});

  @override
  State<EditPhotoModal> createState() => _EditPhotoModalState();
}

class _EditPhotoModalState extends State<EditPhotoModal> {
  File? _selectedImage;
  double _zoomValue = 1.0;
  final _controller = Get.find<ProfileController>();

  Future<void> _pickImage() async {
    final result = await FilePicker.pickFiles(type: FileType.image);
    if (result != null && result.files.single.path != null) {
      setState(() {
        _selectedImage = File(result.files.single.path!);
      });
    }
  }

  String get _existingPhotoUrl => AppConfig.resolveMediaUrl(
        _controller.profileData.value?.profilePhotoUrl,
      );

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 24.h),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.only(
          topLeft: Radius.circular(24.r),
          topRight: Radius.circular(24.r),
        ),
      ),
      child: SafeArea(
        top: false,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 40.w,
              height: 4.h,
              decoration: BoxDecoration(
                color: context.strokeLight,
                borderRadius: BorderRadius.circular(2.r),
              ),
            ),
            SizedBox(height: 16.h),
            Text(
              "Edit Photo",
              style: context.titleLarge?.copyWith(
                fontWeight: FontWeight.bold,
                color: context.heading,
              ),
            ),
            SizedBox(height: 24.h),
            ClipRRect(
              borderRadius: BorderRadius.circular(8.r),
              child: Stack(
                alignment: Alignment.center,
                children: [
                  _selectedImage != null
                      ? Image.file(
                          _selectedImage!,
                          width: double.infinity,
                          height: 300.h,
                          fit: BoxFit.cover,
                        )
                      : CustomImage(
                          _existingPhotoUrl,
                          width: double.infinity,
                          height: 300.h,
                          fit: BoxFit.cover,
                          avatar: true,
                        ),
                  Container(
                    width: double.infinity,
                    height: 300.h,
                    color: Colors.white.withValues(alpha: 0.3),
                  ),
                  Container(
                    width: 200.w,
                    height: 200.w,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      border: Border.all(color: Colors.white, width: 2.w),
                    ),
                    clipBehavior: Clip.antiAlias,
                    child: Transform.scale(
                      scale: _zoomValue,
                      child: _selectedImage != null
                          ? Image.file(_selectedImage!, fit: BoxFit.cover)
                          : CustomImage(
                              _existingPhotoUrl,
                              fit: BoxFit.cover,
                              avatar: true,
                            ),
                    ),
                  ),
                  Positioned(
                    bottom: 16.h,
                    child: GestureDetector(
                      onTap: _pickImage,
                      child: Container(
                        padding: EdgeInsets.all(8.sp),
                        decoration: BoxDecoration(
                          color: Colors.black.withValues(alpha: 0.6),
                          shape: BoxShape.circle,
                        ),
                        child: Icon(
                          Icons.camera_alt,
                          color: Colors.white,
                          size: 24.sp,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            SizedBox(height: 24.h),
            Row(
              children: [
                Expanded(
                  child: Slider(
                    value: _zoomValue,
                    min: 1.0,
                    max: 3.0,
                    onChanged: (val) {
                      setState(() {
                        _zoomValue = val;
                      });
                    },
                    activeColor: context.primaryTheme,
                    inactiveColor: context.primaryFocused,
                  ),
                ),
                SizedBox(width: 8.w),
                GestureDetector(
                  onTap: () {
                    setState(() {
                      _zoomValue = 1.0;
                    });
                  },
                  child: Icon(
                    Icons.refresh,
                    color: context.caption,
                    size: 28.sp,
                  ),
                ),
              ],
            ),
            SizedBox(height: 24.h),
            Row(
              children: [
                Expanded(
                  child: ElevatedButton(
                    onPressed: () => Get.back(),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: context.primaryFocused,
                      foregroundColor: context.primaryTheme,
                      elevation: 0,
                      padding: EdgeInsets.symmetric(vertical: 14.h),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(8.r),
                      ),
                    ),
                    child: Text(
                      "Cancel",
                      style: context.titleRegular?.copyWith(
                        fontWeight: FontWeight.bold,
                        color: context.primaryTheme,
                      ),
                    ),
                  ),
                ),
                SizedBox(width: 16.w),
                Expanded(
                  child: ElevatedButton(
                    onPressed: () {
                      if (_selectedImage != null) {
                        _controller.updateProfilePhoto(_selectedImage!.path);
                      }
                      Get.back();
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: context.primaryTheme,
                      elevation: 0,
                      padding: EdgeInsets.symmetric(vertical: 14.h),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(8.r),
                      ),
                    ),
                    child: Text(
                      "Save",
                      style: context.titleRegular?.copyWith(
                        fontWeight: FontWeight.bold,
                        color: Colors.white,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
