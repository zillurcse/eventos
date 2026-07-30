import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import 'package:get_storage/get_storage.dart';

import '../../../models/user.dart';
import '../../../utils/enum/enums.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../utils/helpers/local_key.dart';
import '../../../widgets/custom_button.dart';
import '../../../widgets/custom_image.dart';
import '../create_post_controller.dart';
import '../widgets/post_avatar.dart';
import '../widgets/post_body_text_field.dart';
import '../widgets/post_date_time_field.dart';
import '../widgets/post_poll_option_field.dart';
import 'widgets/post_type.dart';

class CreatePostView extends StatefulWidget {
  /// The post type that should be pre-selected when the view opens.
  final PostTypes initialType;

  const CreatePostView({super.key, this.initialType = PostTypes.post});

  @override
  State<CreatePostView> createState() => _CreatePostViewState();
}

class _CreatePostViewState extends State<CreatePostView> {
  final ctrl = Get.find<CreatePostController>();

  // ── View-local state only — nothing shared with controller ────────────────
  late final Rx<PostTypes> _selectedType;
  late final RxInt _selectedChipIndex;

  final _bodyCtrl = TextEditingController();

  // Poll
  final _optionCtrls = <TextEditingController>[
    TextEditingController(),
    TextEditingController(),
  ].obs;
  final _pollEndDate = Rx<DateTime?>(null);
  final _pollEndTime = Rx<TimeOfDay?>(null);

  // ── Chip config ───────────────────────────────────────────────────────────
  // Chip order: image(0), video(1), pdf(2), poll(3), lookingFor(4), offering(5)
  static const _chips = [
    {'name': 'Add Image',   'url': 'assets/svg/icons/img.svg',      'type': PostTypes.image},
    {'name': 'Add Video',   'url': 'assets/svg/icons/video.svg',     'type': PostTypes.video},
    {'name': 'Add Pdf',     'url': 'assets/svg/icons/add_note.svg',  'type': PostTypes.pdf},
    {'name': 'Add Poll',    'url': 'assets/svg/icons/poll.svg',      'type': PostTypes.poll},
    {'name': 'Looking For', 'url': 'assets/svg/icons/look_for.svg',  'type': PostTypes.lookingFor},
    {'name': 'Offering',    'url': 'assets/svg/icons/book.svg',      'type': PostTypes.offering},
  ];

  @override
  void initState() {
    super.initState();
    final initial = widget.initialType;
    _selectedType = initial.obs;
    // Find the chip index that matches the initial type (-1 if none / not
    // in the chip list, e.g. when opened by tapping the text area directly)
    _selectedChipIndex =
        _chips.indexWhere((c) => c['type'] == initial).obs;
  }

  @override
  void dispose() {
    _bodyCtrl.dispose();
    for (final c in _optionCtrls) {
      c.dispose();
    }
    super.dispose();
  }

  // ── Helpers ───────────────────────────────────────────────────────────────
  User? get _currentUser {
    final raw = GetStorage().read(LocalKeyHelper.userInfo);
    if (raw is Map) return User.fromJson(Map<String, dynamic>.from(raw));
    return null;
  }

  void _onChipTap(int index) {
    final type = _chips[index]['type'] as PostTypes;

    // If the chip is already selected, ignore the tap to avoid deselecting it.
    if (_selectedChipIndex.value != index) {
      _selectedChipIndex.value = index;
    }

    if (type == PostTypes.image) {
      // image attachment → post_type stays 'post'
      _selectedType.value = PostTypes.post;
      ctrl.clearAttach();
      ctrl.pickImageFile();
    } else if (type == PostTypes.video) {
      // video attachment → post_type = 'video'
      _selectedType.value = PostTypes.video;
      ctrl.clearAttach();
      ctrl.pickVideoFile();
    } else if (type == PostTypes.pdf) {
      // pdf attachment → post_type = 'pdf'
      _selectedType.value = PostTypes.pdf;
      ctrl.clearAttach();
      ctrl.pickPdfFile();
    } else {
      // Poll / offering / looking-for — standard create-post UI.
      ctrl.clearAttach();
      _selectedType.value = type;
    }
  }

  // ── Submit ────────────────────────────────────────────────────────────────
  Future<void> _onPost() async {
    final type = _selectedType.value;

    // ── Media / PDF post ────────────────────────────────────────────────────
    // If the controller has a pending attachment (from pickAttachFile()),
    // route straight to submitPostWithAttach — regardless of selected UI type.
    if (ctrl.attachFile.value != null) {
      final ok = await ctrl.submitPostWithAttach(body: _bodyCtrl.text);
      if (ok && mounted) Get.back();
      return;
    }

    // ── Poll ─────────────────────────────────────────────────────────────────
    if (type == PostTypes.poll) {
      final endDt = _buildEndDateTime();
      final diff =
          endDt != null ? endDt.difference(DateTime.now()) : Duration.zero;
      final ok = await ctrl.submitPoll(
        question: _bodyCtrl.text,
        options: _optionCtrls.map((c) => c.text).toList(),
        lengthDays: diff.inDays.clamp(0, 99),
        lengthHours: (diff.inHours % 24).clamp(0, 23),
        lengthMinutes: (diff.inMinutes % 60).clamp(0, 59),
      );
      if (ok && mounted) Get.back();
      return;
    }

    // ── Regular text post (offering / looking-for / plain post) ──────────────
    final apiType = switch (type) {
      PostTypes.offering   => 'offering',
      PostTypes.lookingFor => 'looking-for',
      _                    => 'post',
    };
    final ok = await ctrl.submitPost(body: _bodyCtrl.text, type: apiType);
    if (ok && mounted) Get.back();
  }

  DateTime? _buildEndDateTime() {
    final date = _pollEndDate.value;
    final time = _pollEndTime.value;
    if (date == null) return null;
    return DateTime(
      date.year, date.month, date.day,
      time?.hour ?? 23, time?.minute ?? 59,
    );
  }

  // ── Poll option helpers ───────────────────────────────────────────────────
  void _addOption() {
    if (_optionCtrls.length >= 8) return;
    _optionCtrls.add(TextEditingController());
  }

  void _removeOption(int i) {
    if (_optionCtrls.length <= 2) return;
    _optionCtrls[i].dispose();
    _optionCtrls.removeAt(i);
  }

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: DateTime.now().add(const Duration(days: 1)),
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 365)),
    );
    if (picked != null) _pollEndDate.value = picked;
  }

  Future<void> _pickTime() async {
    final picked = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.now(),
    );
    if (picked != null) _pollEndTime.value = picked;
  }

  // ── Build ─────────────────────────────────────────────────────────────────
  @override
  Widget build(BuildContext context) {
    final user = _currentUser;

    return Scaffold(
      resizeToAvoidBottomInset: true,
      backgroundColor: context.backgroundColor,
      appBar: AppBar(
        titleSpacing: 0,
        backgroundColor: context.primaryTheme,
        leading: IconButton(
          icon: Icon(Icons.arrow_back, color: context.tertiaryText),
          onPressed: () => Get.back(),
        ),
        title: Text(
          'Create Post',
          style: context.titleLarge?.copyWith(color: context.tertiaryText),
        ),
        actions: [
          Padding(
            padding: EdgeInsets.symmetric(horizontal: 16.w, vertical: 8.h),
            child: Obx(
              () => SizedBox(
                height: 32.h,
                width: 70.w,
                child: ctrl.isSubmitting.value
                    ? Center(
                        child: SizedBox(
                          height: 20.sp,
                          width: 20.sp,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: context.tertiaryText,
                          ),
                        ),
                      )
                    : Button.roundedText(
                        text: 'Post',
                        onTap: _onPost,
                        borderColor: context.tertiaryText,
                        backgroundColor: Colors.transparent,
                        onBackgroundColor: context.tertiaryText,
                      ),
              ),
            ),
          ),
        ],
      ),
      body: Column(
        children: [
          // ── Content area ────────────────────────────────────────────────
          Expanded(
            child: SingleChildScrollView(
              padding: EdgeInsets.fromLTRB(16.w, 16.h, 16.w, 16.h),
              child: Obx(
                () => _buildBody(context, user, _selectedType.value),
              ),
            ),
          ),

          // ── Bottom chip bar ─────────────────────────────────────────────
          _buildBottomBar(context),
        ],
      ),
    );
  }

  // ── Body switcher ─────────────────────────────────────────────────────────
  Widget _buildBody(BuildContext context, User? user, PostTypes type) {
    return switch (type) {
      PostTypes.poll       => _buildPollBody(context, user),
      PostTypes.offering   => _buildInterestBody(context, user, isOffering: true),
      PostTypes.lookingFor => _buildInterestBody(context, user, isOffering: false),
      _                    => _buildTextBody(context, user),
    };
  }

  // ── Plain post ────────────────────────────────────────────────────────────
  Widget _buildTextBody(BuildContext context, User? user) {
    return Container(
      padding: EdgeInsets.all(16.w),
      decoration: BoxDecoration(
        color: context.tertiaryText,
        borderRadius: BorderRadius.circular(12.r),
        border: Border.all(color: context.strokeLight),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          PostAvatar(user: user),
          SizedBox(width: 12.w),
          Expanded(child: PostBodyTextField(controller: _bodyCtrl)),
        ],
      ),
    );
  }

  // ── Offering / Looking-for ────────────────────────────────────────────────
  Widget _buildInterestBody(
    BuildContext context,
    User? user, {
    required bool isOffering,
  }) {
    final label = isOffering
        ? '${user?.name ?? 'You'} is Offering'
        : '${user?.name ?? 'You'} is Looking For';

    return Container(
      padding: EdgeInsets.all(16.w),
      decoration: BoxDecoration(
        color: context.tertiaryText,
        borderRadius: BorderRadius.circular(12.r),
        border: Border.all(color: context.strokeLight),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Center(
            child: ClipRRect(
              borderRadius: BorderRadius.circular(12.r),
              child: CustomImage(
                user?.profilePhotoUrl ?? '',
                height: 80.sp,
                width: 80.sp,
                fit: BoxFit.cover,
                radius: 12.r,
                avatar: true,
              ),
            ),
          ),
          SizedBox(height: 10.h),
          Center(
            child: Text(
              label,
              style: context.h2?.copyWith(
                color: context.heading,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          SizedBox(height: 16.h),
          PostBodyTextField(
            controller: _bodyCtrl,
            hint: 'Anything on your mind? Please share it with the community.',
          ),
        ],
      ),
    );
  }

  // ── Poll ──────────────────────────────────────────────────────────────────
  Widget _buildPollBody(BuildContext context, User? user) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Question
        Container(
          padding: EdgeInsets.all(16.w),
          decoration: BoxDecoration(
            color: context.tertiaryText,
            borderRadius: BorderRadius.circular(12.r),
            border: Border.all(color: context.strokeLight),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              PostAvatar(user: user, size: 34.sp),
              SizedBox(width: 12.w),
              Expanded(
                child: PostBodyTextField(
                  controller: _bodyCtrl,
                  hint: 'Anything on your mind? Please share it with the community.',
                  maxLines: 3,
                ),
              ),
            ],
          ),
        ),
        SizedBox(height: 16.h),

        // Options
        Obx(
          () => Column(
            children: List.generate(_optionCtrls.length, (i) {
              return Padding(
                padding: EdgeInsets.only(bottom: 10.h),
                child: PostPollOptionField(
                  controller: _optionCtrls[i],
                  index: i,
                  canRemove: _optionCtrls.length > 2,
                  autoFocus: i == _optionCtrls.length - 1 && i > 1,
                  onRemove: () => _removeOption(i),
                ),
              );
            }),
          ),
        ),

        // Add option button
        Obx(
          () => _optionCtrls.length < 8
              ? Padding(
                  padding: EdgeInsets.only(top: 2.h, bottom: 4.h),
                  child: GestureDetector(
                    onTap: _addOption,
                    child: Row(
                      children: [
                        Icon(Icons.add_circle_outline,
                            size: 18.sp, color: context.primaryTheme),
                        SizedBox(width: 6.w),
                        Text(
                          'Add Option',
                          style: context.specialCaption1?.copyWith(
                            color: context.primaryTheme,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ),
                )
              : const SizedBox.shrink(),
        ),

        SizedBox(height: 20.h),

        // Schedule end
        Text(
          'Schedule Poll End',
          style: context.bodyRegular?.copyWith(
            color: context.heading,
            fontWeight: FontWeight.w600,
          ),
        ),
        SizedBox(height: 10.h),
        Row(
          children: [
            Expanded(
              child: Obx(
                () => PostDateTimeField(
                  hint: 'DD/MM/YYYY',
                  value: _pollEndDate.value != null
                      ? '${_pollEndDate.value!.day.toString().padLeft(2, '0')}/'
                          '${_pollEndDate.value!.month.toString().padLeft(2, '0')}/'
                          '${_pollEndDate.value!.year}'
                      : null,
                  onTap: _pickDate,
                ),
              ),
            ),
            SizedBox(width: 12.w),
            Expanded(
              child: Obx(
                () => PostDateTimeField(
                  hint: 'End Time',
                  value: _pollEndTime.value?.format(context),
                  onTap: _pickTime,
                ),
              ),
            ),
          ],
        ),
        SizedBox(height: 24.h),
      ],
    );
  }

  // ── Bottom chip bar ───────────────────────────────────────────────────────
  Widget _buildBottomBar(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: EdgeInsets.fromLTRB(
        16.w, 12.h, 16.w,
        12.h + MediaQuery.of(context).viewPadding.bottom,
      ),
      decoration: BoxDecoration(
        color: context.tertiaryText,
        boxShadow: [
          BoxShadow(
            color: context.ghost.withValues(alpha: .15),
            offset: const Offset(0, -1),
            blurRadius: 6,
          ),
        ],
        borderRadius: BorderRadius.vertical(top: Radius.circular(20.r)),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // Drag handle
          Center(
            child: Container(
              height: 3.h,
              width: 80.w,
              decoration: BoxDecoration(
                color: context.ghost,
                borderRadius: BorderRadius.circular(50.r),
              ),
            ),
          ),
          SizedBox(height: 12.h),

          // ── Attachment preview strip (shown above chips when a file is picked)
          Obx(() {
            final filePath = ctrl.attachFilePath.value;
            final type    = ctrl.attachType.value;
            if (filePath == null || type == null) return const SizedBox.shrink();

            return Padding(
              padding: EdgeInsets.only(bottom: 10.h),
              child: Container(
                padding: EdgeInsets.symmetric(horizontal: 12.w, vertical: 10.h),
                decoration: BoxDecoration(
                  color: context.primaryFocused,
                  borderRadius: BorderRadius.circular(10.r),
                  border: Border.all(
                    color: context.primaryTheme.withValues(alpha: .3),
                  ),
                ),
                child: Row(
                  children: [
                    // Thumbnail / icon
                    Container(
                      height: 40.sp,
                      width: 40.sp,
                      decoration: BoxDecoration(
                        color: context.tertiaryText,
                        borderRadius: BorderRadius.circular(8.r),
                      ),
                      clipBehavior: Clip.antiAlias,
                      child: type == 'image'
                          ? Image.file(
                              File(filePath),
                              fit: BoxFit.cover,
                              errorBuilder: (c, e, s) => Icon(
                                Icons.image_outlined,
                                color: context.primaryTheme,
                                size: 22.sp,
                              ),
                            )
                          : Icon(
                              type == 'pdf'
                                  ? Icons.picture_as_pdf_outlined
                                  : Icons.videocam_outlined,
                              color: context.primaryTheme,
                              size: 22.sp,
                            ),
                    ),
                    SizedBox(width: 10.w),
                    // File name + type label
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(
                            filePath.split('/').last,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: context.specialCaption1?.copyWith(
                              color: context.heading,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                          Text(
                            type.toUpperCase(),
                            style: context.specialCaption2?.copyWith(
                              color: context.primaryTheme,
                            ),
                          ),
                        ],
                      ),
                    ),
                    // Clear button
                    GestureDetector(
                      onTap: () {
                        ctrl.clearAttach();
                        _selectedChipIndex.value = -1;
                      },
                      child: Padding(
                        padding: EdgeInsets.symmetric(horizontal: 4.w),
                        child: Icon(
                          Icons.close,
                          size: 18.sp,
                          color: context.caption,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            );
          }),

          // ── Chip row
          SizedBox(
            height: 46.sp,
            child: ListView.builder(
              itemCount: _chips.length,
              shrinkWrap: true,
              scrollDirection: Axis.horizontal,
              itemBuilder: (context, index) {
                return Padding(
                  padding: EdgeInsets.only(right: 12.w),
                  child: Obx(
                    () => PostType(
                      isSelected: _selectedChipIndex.value == index,
                      postItem: _chips[index] as Map<String, dynamic>,
                      onTap: () => _onChipTap(index),
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

