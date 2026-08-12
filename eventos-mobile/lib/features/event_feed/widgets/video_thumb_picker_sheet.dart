import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:video_player/video_player.dart';

import '../../../utils/extension/theme_ext.dart';
import '../../../utils/helpers/toast_msg.dart';
import '../../../utils/helpers/video_thumb_helper.dart';

/// Full-screen sheet: scrub a video frame or upload a custom cover image.
/// Returns a compressed JPEG [File], or null if skipped/cancelled.
Future<File?> showVideoThumbPicker({
  required BuildContext context,
  required String videoPath,
}) {
  return showModalBottomSheet<File?>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.transparent,
    builder: (_) => VideoThumbPickerSheet(videoPath: videoPath),
  );
}

class VideoThumbPickerSheet extends StatefulWidget {
  final String videoPath;

  const VideoThumbPickerSheet({super.key, required this.videoPath});

  @override
  State<VideoThumbPickerSheet> createState() => _VideoThumbPickerSheetState();
}

class _VideoThumbPickerSheetState extends State<VideoThumbPickerSheet> {
  late final VideoPlayerController _player;
  bool _ready = false;
  bool _saving = false;
  int _tab = 0; // 0 = frame, 1 = upload
  double _positionMs = 0;
  double _durationMs = 0;
  double _aspect = VideoThumbHelper.fallbackAspect;

  File? _customFile;
  String? _error;

  @override
  void initState() {
    super.initState();
    _player = VideoPlayerController.file(File(widget.videoPath))
      ..initialize().then((_) {
        if (!mounted) return;
        final v = _player.value;
        final start = v.duration.inMilliseconds > 200 ? 100.0 : 0.0;
        setState(() {
          _ready = v.isInitialized;
          _durationMs = v.duration.inMilliseconds.toDouble().clamp(0, double.infinity);
          _aspect = v.aspectRatio > 0 ? v.aspectRatio : VideoThumbHelper.fallbackAspect;
          _positionMs = start.clamp(0, _durationMs);
        });
        if (_positionMs > 0) {
          _player.seekTo(Duration(milliseconds: _positionMs.round()));
        }
      }).catchError((_) {
        if (mounted) setState(() => _error = 'Couldn’t load this video.');
      });
  }

  @override
  void dispose() {
    _player.dispose();
    super.dispose();
  }

  Future<void> _pickCustom() async {
    try {
      final result = await FilePicker.pickFiles(
        type: FileType.custom,
        allowedExtensions: ['jpg', 'jpeg', 'png', 'webp'],
      );
      if (result == null || result.files.single.path == null) return;
      final picked = result.files.single;
      if (picked.size > VideoThumbHelper.maxInputBytes) {
        setState(() => _error = 'Image must be under 5 MB.');
        return;
      }
      setState(() {
        _customFile = File(picked.path!);
        _error = null;
        _tab = 1;
      });
    } catch (_) {
      setState(() => _error = 'Couldn’t pick that image.');
    }
  }

  Future<void> _save() async {
    if (_saving) return;
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final File out;
      if (_tab == 1) {
        final file = _customFile;
        if (file == null) {
          setState(() {
            _error = 'Choose an image first.';
            _saving = false;
          });
          return;
        }
        out = await VideoThumbHelper.compressFile(file, aspect: _aspect);
      } else {
        if (!_ready) {
          setState(() {
            _error = 'Video isn’t ready yet.';
            _saving = false;
          });
          return;
        }
        out = await VideoThumbHelper.frameFromVideo(
          videoPath: widget.videoPath,
          timeMs: _positionMs.round(),
          aspect: _aspect,
        );
      }
      if (!mounted) return;
      Navigator.of(context).pop(out);
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _saving = false;
        _error = e.toString().replaceFirst('Exception: ', '');
      });
      ToastMsg.showErrorMessage(_error ?? 'Couldn’t save thumbnail.');
    }
  }

  String _fmt(double ms) {
    final total = (ms / 1000).floor().clamp(0, 99999);
    final m = total ~/ 60;
    final s = total % 60;
    return '$m:${s.toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    final bottom = MediaQuery.of(context).viewPadding.bottom;
    return Container(
      constraints: BoxConstraints(
        maxHeight: MediaQuery.of(context).size.height * 0.92,
      ),
      decoration: BoxDecoration(
        color: context.tertiaryText,
        borderRadius: BorderRadius.vertical(top: Radius.circular(20.r)),
      ),
      child: SafeArea(
        top: false,
        child: Padding(
          padding: EdgeInsets.fromLTRB(16.w, 12.h, 16.w, 12.h + bottom),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                height: 3.h,
                width: 80.w,
                decoration: BoxDecoration(
                  color: context.ghost,
                  borderRadius: BorderRadius.circular(50.r),
                ),
              ),
              SizedBox(height: 14.h),
              Row(
                children: [
                  Expanded(
                    child: Text(
                      'Choose thumbnail',
                      style: context.h2?.copyWith(
                        color: context.heading,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  IconButton(
                    onPressed: () => Navigator.of(context).pop(),
                    icon: Icon(Icons.close, color: context.caption),
                  ),
                ],
              ),
              SizedBox(height: 8.h),
              _tabs(context),
              SizedBox(height: 14.h),
              Flexible(
                child: SingleChildScrollView(
                  child: _tab == 0 ? _framePane(context) : _uploadPane(context),
                ),
              ),
              if (_error != null) ...[
                SizedBox(height: 8.h),
                Text(
                  _error!,
                  style: context.specialCaption1?.copyWith(color: Colors.red),
                ),
              ],
              SizedBox(height: 14.h),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: _saving ? null : () => Navigator.of(context).pop(),
                      child: const Text('Skip'),
                    ),
                  ),
                  SizedBox(width: 12.w),
                  Expanded(
                    child: FilledButton(
                      onPressed: _saving ? null : _save,
                      style: FilledButton.styleFrom(
                        backgroundColor: context.primaryTheme,
                      ),
                      child: _saving
                          ? SizedBox(
                              height: 18.sp,
                              width: 18.sp,
                              child: const CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : Text(_tab == 0 ? 'Use this frame' : 'Use this image'),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _tabs(BuildContext context) {
    return Container(
      padding: EdgeInsets.all(4.w),
      decoration: BoxDecoration(
        color: context.primaryFocused,
        borderRadius: BorderRadius.circular(10.r),
      ),
      child: Row(
        children: [
          Expanded(child: _tabBtn(context, 0, 'From video')),
          Expanded(child: _tabBtn(context, 1, 'Upload image')),
        ],
      ),
    );
  }

  Widget _tabBtn(BuildContext context, int index, String label) {
    final on = _tab == index;
    return GestureDetector(
      onTap: () => setState(() {
        _tab = index;
        _error = null;
      }),
      child: Container(
        padding: EdgeInsets.symmetric(vertical: 10.h),
        decoration: BoxDecoration(
          color: on ? context.tertiaryText : Colors.transparent,
          borderRadius: BorderRadius.circular(8.r),
          boxShadow: on
              ? [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.06),
                    blurRadius: 4,
                  ),
                ]
              : null,
        ),
        alignment: Alignment.center,
        child: Text(
          label,
          style: context.specialCaption1?.copyWith(
            color: on ? context.heading : context.caption,
            fontWeight: FontWeight.w700,
          ),
        ),
      ),
    );
  }

  Widget _framePane(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Scrub to the frame you want as the cover image.',
          style: context.specialCaption1?.copyWith(color: context.caption),
        ),
        SizedBox(height: 12.h),
        AspectRatio(
          aspectRatio: _aspect,
          child: ClipRRect(
            borderRadius: BorderRadius.circular(12.r),
            child: ColoredBox(
              color: Colors.black,
              child: _ready
                  ? VideoPlayer(_player)
                  : const Center(
                      child: CircularProgressIndicator(color: Colors.white),
                    ),
            ),
          ),
        ),
        SizedBox(height: 10.h),
        Slider(
          value: _positionMs.clamp(0, _durationMs > 0 ? _durationMs : 1),
          max: _durationMs > 0 ? _durationMs : 1,
          onChanged: !_ready
              ? null
              : (v) {
                  setState(() => _positionMs = v);
                  _player.seekTo(Duration(milliseconds: v.round()));
                },
          activeColor: context.primaryTheme,
        ),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(_fmt(_positionMs), style: context.specialCaption2?.copyWith(color: context.caption)),
            Text(_fmt(_durationMs), style: context.specialCaption2?.copyWith(color: context.caption)),
          ],
        ),
      ],
    );
  }

  Widget _uploadPane(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Upload a cover image (max 5 MB). Cropped to your video’s ratio and compressed automatically.',
          style: context.specialCaption1?.copyWith(color: context.caption),
        ),
        SizedBox(height: 12.h),
        AspectRatio(
          aspectRatio: _aspect,
          child: GestureDetector(
            onTap: _pickCustom,
            child: ClipRRect(
              borderRadius: BorderRadius.circular(12.r),
              child: _customFile != null
                  ? Stack(
                      fit: StackFit.expand,
                      children: [
                        Image.file(_customFile!, fit: BoxFit.cover),
                        Positioned(
                          right: 10.w,
                          bottom: 10.h,
                          child: Container(
                            padding: EdgeInsets.symmetric(horizontal: 10.w, vertical: 6.h),
                            decoration: BoxDecoration(
                              color: Colors.black.withValues(alpha: 0.75),
                              borderRadius: BorderRadius.circular(8.r),
                            ),
                            child: Text(
                              'Change image',
                              style: context.specialCaption2?.copyWith(
                                color: Colors.white,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                        ),
                      ],
                    )
                  : Container(
                      decoration: BoxDecoration(
                        color: context.primaryFocused,
                        border: Border.all(color: context.strokeLight, width: 1.5),
                        borderRadius: BorderRadius.circular(12.r),
                      ),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(Icons.upload_rounded, size: 28.sp, color: context.primaryTheme),
                          SizedBox(height: 8.h),
                          Text(
                            'Choose image',
                            style: context.specialCaption1?.copyWith(
                              color: context.primaryTheme,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          Text(
                            'PNG, JPEG or WebP · up to 5 MB',
                            style: context.specialCaption2?.copyWith(color: context.caption),
                          ),
                        ],
                      ),
                    ),
            ),
          ),
        ),
      ],
    );
  }
}
