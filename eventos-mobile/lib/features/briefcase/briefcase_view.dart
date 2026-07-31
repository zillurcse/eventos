import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../models/briefcase_item_model.dart';
import '../../utils/enum/enums.dart';
import '../../utils/extension/theme_ext.dart';
import '../../widgets/custom_image.dart';
import 'briefcase_controller.dart';

class BriefcaseView extends StatefulWidget {
  const BriefcaseView({super.key});

  @override
  State<BriefcaseView> createState() => _BriefcaseViewState();
}

class _BriefcaseViewState extends State<BriefcaseView>
    with SingleTickerProviderStateMixin {
  late final TabController _tabController;
  final briefcaseCtrl = Get.put(BriefcaseController());
  final RxString _activeNoteFilter = ''.obs;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      briefcaseCtrl.fetchAllBriefcaseItems();
    });
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _openFile(String fileUrl) async {
    if (fileUrl.isEmpty) return;
    final url = fileUrl.startsWith('http')
        ? fileUrl
        : 'https://admin.expouse.com/storage/$fileUrl';
    final uri = Uri.tryParse(url);
    if (uri != null) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: context.backgroundColor,
      appBar: AppBar(
        titleSpacing: 0,
        backgroundColor: context.primaryTheme,
        elevation: 0,
        iconTheme: const IconThemeData(color: Colors.white),
        title: Text(
          "My Briefcase",
          style: context.titleLarge?.copyWith(
            color: Colors.white,
            fontWeight: FontWeight.bold,
          ),
        ),
        bottom: TabBar(
          controller: _tabController,
          indicatorColor: Colors.white,
          indicatorWeight: 3.h,
          labelColor: Colors.white,
          unselectedLabelColor: Colors.white.withValues(alpha: 0.7),
          labelStyle: context.bodyRegular?.copyWith(
            fontWeight: FontWeight.bold,
          ),
          unselectedLabelStyle: context.bodyRegular,
          tabs: const [
            Tab(text: "Files & Documents"),
            Tab(text: "Notes"),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: [_buildFilesTab(), _buildNotesTab()],
      ),
    );
  }

  // --- Files & Documents Tab ---
  Widget _buildFilesTab() {
    return Obx(() {
      if (briefcaseCtrl.dataStatus.value == ApiState.loading) {
        return _buildSkeletonList();
      }
      
      final files = briefcaseCtrl.files;
      if (files.isEmpty) {
        return _buildEmptyState("No saved files in briefcase yet");
      }

      return ListView.builder(
        padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 12.h),
        itemCount: files.length,
        itemBuilder: (context, index) {
          final file = files[index];
          return _buildFileCard(file);
        },
      );
    });
  }

  Widget _buildFileCard(BriefcaseFileModel file) {
    return Container(
      margin: EdgeInsets.symmetric(vertical: 6.h),
      padding: EdgeInsets.all(12.sp),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12.r),
        border: Border.all(color: context.strokeLight, width: 1.sp),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        children: [
          // PDF Icon (matching chat style)
          Container(
            padding: EdgeInsets.all(4.sp),
            child: CustomImage("assets/svg/icons/pdf.svg", height: 28.sp),
          ),
          SizedBox(width: 12.w),
          // File Name and Type
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  file.name,
                  style: context.bodyRegular?.copyWith(
                    fontWeight: FontWeight.bold,
                    color: context.heading,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                SizedBox(height: 4.h),
                  Text(
                  file.kind.toUpperCase() == 'PDF' ? 'PDF FILE' : '${file.kind.toUpperCase()} FILE',
                  style: context.specialCaption2?.copyWith(
                    color: context.caption,
                    fontSize: 10.sp,
                  ),
                ),
              ],
            ),
          ),
          // Action Buttons: Save/Download, Delete
          GestureDetector(
            onTap: () => _openFile(file.url),
            child: Container(
              padding: EdgeInsets.fromLTRB(8.sp, 8.sp, 8.sp, 4.sp),
              child: Icon(
                Icons.download,
                color: context.caption,
                size: 20.sp,
              ),
            ),
          ),
          GestureDetector(
            onTap: () {
              briefcaseCtrl.removeFile(file.id);
              Get.rawSnackbar(message: "File removed from briefcase");
            },
            child: Container(
              padding: EdgeInsets.all(8.sp),
              child: Icon(
                Icons.delete,
                color: context.caption,
                size: 18.sp,
              ),
            ),
          ),
        ],
      ),
    );
  }

  // --- Notes Tab ---
  Widget _buildNotesTab() {
    return Obx(() {
      if (briefcaseCtrl.dataStatus.value == ApiState.loading) {
        return _buildSkeletonList();
      }

      final keys = briefcaseCtrl.notesMap.keys.toList();
      if (keys.isEmpty) {
        return _buildEmptyState("No notes available");
      }

      if (_activeNoteFilter.value.isEmpty || !keys.contains(_activeNoteFilter.value)) {
        WidgetsBinding.instance.addPostFrameCallback((_) {
          _activeNoteFilter.value = keys.first;
        });
      }

      final activeFilter = _activeNoteFilter.value.isNotEmpty ? _activeNoteFilter.value : keys.first;
      final filteredNotes = briefcaseCtrl.notesMap[activeFilter] ?? [];

      return Column(
        children: [
          // Notes Filter Row
          Padding(
            padding: EdgeInsets.fromLTRB(16.w, 16.h, 16.w, 12.h),
            child: SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: keys.map((key) {
                  final label = key.capitalizeFirst ?? key;
                  return Padding(
                    padding: EdgeInsets.only(right: 12.w),
                    child: _buildFilterPill(label, key),
                  );
                }).toList(),
              ),
            ),
          ),
          // Notes List
          Expanded(
            child: filteredNotes.isEmpty
                ? _buildEmptyState("No notes under $activeFilter yet")
                : ListView.builder(
                    padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 8.h),
                    itemCount: filteredNotes.length,
                    itemBuilder: (context, index) {
                      final note = filteredNotes[index];
                      return _buildNoteCard(note);
                    },
                  ),
          ),
        ],
      );
    });
  }

  Widget _buildFilterPill(String label, String value) {
    return Obx(() {
      final isSelected = _activeNoteFilter.value == value;
      return GestureDetector(
        onTap: () {
          _activeNoteFilter.value = value;
        },
        child: Container(
          padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 8.h),
          decoration: BoxDecoration(
            color: isSelected ? Colors.transparent : Colors.white,
            borderRadius: BorderRadius.circular(8.r),
            border: Border.all(
              color: isSelected ? context.primaryTheme : Colors.transparent,
              width: 1.sp,
            ),
            boxShadow: isSelected
                ? []
                : [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.02),
                      blurRadius: 4,
                      offset: const Offset(0, 1),
                    ),
                  ],
          ),
          child: Text(
            label,
            style: context.bodyRegular?.copyWith(
              color: isSelected ? context.primaryTheme : context.caption,
              fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
            ),
          ),
        ),
      );
    });
  }

  Widget _buildNoteCard(BriefcaseNoteModel note) {
    final hasImage = note.entityImage.isNotEmpty;
    return Container(
      margin: EdgeInsets.symmetric(vertical: 8.h),
      padding: EdgeInsets.all(16.w),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16.r),
        border: Border.all(color: context.strokeLight, width: 1.sp),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.03),
            blurRadius: 8,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Note Header: Avatar, Info, Action icons
          Row(
            children: [
              hasImage
                  ? ClipRRect(
                      borderRadius: BorderRadius.circular(8.r),
                      child: CustomImage(
                        note.entityImage,
                        height: 40.sp,
                        width: 40.sp,
                        radius: 8.r,
                        fit: BoxFit.cover,
                      ),
                    )
                  : Container(
                      height: 40.sp,
                      width: 40.sp,
                      decoration: BoxDecoration(
                        color: context.primaryFocused,
                        borderRadius: BorderRadius.circular(8.r),
                      ),
                      child: Icon(
                        Icons.person_outline,
                        color: context.primaryTheme,
                        size: 20.sp,
                      ),
                    ),
              SizedBox(width: 12.w),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      note.entityName,
                      style: context.titleLarge?.copyWith(
                        color: context.heading,
                        fontWeight: FontWeight.bold,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    SizedBox(height: 2.h),
                    Text(
                      note.entityRole,
                      style: context.bodyRegular?.copyWith(
                        color: context.caption,
                        fontSize: 12.sp,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ),
              ),
              GestureDetector(
                onTap: () {
                  Clipboard.setData(ClipboardData(text: note.noteText));
                  Get.rawSnackbar(message: "Note copied to clipboard");
                },
                child: Container(
                  padding: EdgeInsets.all(6.sp),
                  child: Icon(
                    Icons.copy_rounded,
                    color: context.caption,
                    size: 20.sp,
                  ),
                ),
              ),
              SizedBox(width: 8.w),
              GestureDetector(
                onTap: () {
                  briefcaseCtrl.removeNote(note.id);
                  Get.rawSnackbar(message: "Note deleted successfully");
                },
                child: Container(
                  padding: EdgeInsets.all(6.sp),
                  child: Icon(
                    Icons.delete_outline_outlined,
                    color: context.caption,
                    size: 20.sp,
                  ),
                ),
              ),
            ],
          ),
          SizedBox(height: 12.h),
          // Note Body Text
          Text(
            note.noteText,
            style: context.bodyRegular?.copyWith(
              color: context.body,
              height: 1.4,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildEmptyState(String message) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.work_outline, size: 64.sp, color: context.caption),
          SizedBox(height: 16.h),
          Text(
            message,
            style: context.titleLarge?.copyWith(color: context.caption),
          ),
        ],
      ),
    );
  }

  Widget _buildSkeletonList() {
    return ListView.builder(
      padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 12.h),
      itemCount: 5,
      itemBuilder: (context, index) {
        return Container(
          height: 80.h,
          margin: EdgeInsets.symmetric(vertical: 6.h),
          decoration: BoxDecoration(
            color: Colors.grey.withValues(alpha: 0.1),
            borderRadius: BorderRadius.circular(12.r),
          ),
        );
      },
    );
  }
}
