import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:video_player/video_player.dart';

import '../../utils/extension/theme_ext.dart';

/// Full-screen modal player for a regular (non-YouTube) network video.
class NormalVideoCard extends StatefulWidget {
  final String videoUrl;
  const NormalVideoCard({super.key, required this.videoUrl});

  @override
  State<NormalVideoCard> createState() => _NormalVideoCardState();
}

class _NormalVideoCardState extends State<NormalVideoCard> {
  late final VideoPlayerController _ctrl;
  bool _initialized = false;
  bool _playing = false;

  @override
  void initState() {
    super.initState();
    _ctrl = VideoPlayerController.networkUrl(Uri.parse(widget.videoUrl));
    _ctrl
        .initialize()
        .then((_) {
          if (mounted) {
            setState(() => _initialized = true);
            _ctrl.play();
          }
        })
        .catchError((_) {
          if (mounted) setState(() => _initialized = false);
        });
    _ctrl.addListener(_onControllerUpdate);
  }

  void _onControllerUpdate() {
    if (mounted) setState(() => _playing = _ctrl.value.isPlaying);
  }

  @override
  void dispose() {
    _ctrl.removeListener(_onControllerUpdate);
    _ctrl.dispose();
    super.dispose();
  }

  void _togglePlay() {
    if (_ctrl.value.isPlaying) {
      _ctrl.pause();
    } else {
      if (_ctrl.value.position >= _ctrl.value.duration) {
        _ctrl.seekTo(Duration.zero);
      }
      _ctrl.play();
    }
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // Close button
          Align(
            alignment: Alignment.topRight,
            child: GestureDetector(
              onTap: () => Navigator.pop(context),
              child: Padding(
                padding: EdgeInsets.only(right: 16.w),
                child: Icon(Icons.close, color: context.tertiaryText),
              ),
            ),
          ),
          // Drag handle
          GestureDetector(
            onTap: () => Navigator.pop(context),
            child: Container(
              margin: EdgeInsets.symmetric(vertical: 12.h),
              height: 4.h,
              width: 40.w,
              decoration: BoxDecoration(
                color: context.ghost,
                borderRadius: BorderRadius.circular(2.r),
              ),
            ),
          ),
          // Player
          Padding(
            padding: EdgeInsets.symmetric(horizontal: 16.w),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(10.r),
              child: _initialized
                  ? Stack(
                      alignment: Alignment.center,
                      children: [
                        AspectRatio(
                          aspectRatio: _ctrl.value.aspectRatio,
                          child: VideoPlayer(_ctrl),
                        ),
                        // Play/pause overlay
                        GestureDetector(
                          onTap: _togglePlay,
                          child: AnimatedOpacity(
                            opacity: _playing ? 0.0 : 1.0,
                            duration: const Duration(milliseconds: 200),
                            child: Container(
                              width: 56.sp,
                              height: 56.sp,
                              decoration: const BoxDecoration(
                                color: Colors.black54,
                                shape: BoxShape.circle,
                              ),
                              child: Icon(
                                Icons.play_arrow_rounded,
                                color: Colors.white,
                                size: 32.sp,
                              ),
                            ),
                          ),
                        ),
                        if (_playing)
                          GestureDetector(
                            behavior: HitTestBehavior.opaque,
                            onTap: _togglePlay,
                            child: const SizedBox.expand(),
                          ),
                        // Progress bar
                        Positioned(
                          bottom: 0,
                          left: 0,
                          right: 0,
                          child: VideoProgressIndicator(
                            _ctrl,
                            allowScrubbing: true,
                            colors: VideoProgressColors(
                              playedColor: context.primaryTheme,
                              bufferedColor:
                                  context.primaryTheme.withValues(alpha: 0.4),
                              backgroundColor: context.ghost,
                            ),
                          ),
                        ),
                      ],
                    )
                  : AspectRatio(
                      aspectRatio: 16 / 9,
                      child: Container(
                        color: Colors.black87,
                        child: const Center(
                          child: CircularProgressIndicator(color: Colors.white),
                        ),
                      ),
                    ),
            ),
          ),
          SizedBox(height: 24.h),
        ],
      ),
    );
  }
}
