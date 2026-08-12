import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:video_player/video_player.dart';

import '../../utils/config/app_config.dart';

/// Full-screen player for a regular (non-YouTube) network video.
class NormalVideoCard extends StatefulWidget {
  final String videoUrl;
  const NormalVideoCard({super.key, required this.videoUrl});

  @override
  State<NormalVideoCard> createState() => _NormalVideoCardState();
}

class _NormalVideoCardState extends State<NormalVideoCard> {
  late final VideoPlayerController _ctrl;
  bool _initialized = false;
  bool _failed = false;
  bool _playing = false;
  bool _muted = false;
  bool _showControls = true;
  Timer? _hideTimer;

  String get _resolvedUrl => AppConfig.resolveMediaUrl(widget.videoUrl);

  @override
  void initState() {
    super.initState();
    _ctrl = VideoPlayerController.networkUrl(Uri.parse(_resolvedUrl));
    _ctrl
        .initialize()
        .then((_) {
          if (!mounted) return;
          setState(() => _initialized = true);
          _ctrl.play();
          _scheduleHideControls();
        })
        .catchError((_) {
          if (mounted) setState(() => _failed = true);
        });
    _ctrl.addListener(_onControllerUpdate);
  }

  void _onControllerUpdate() {
    if (!mounted) return;
    final playing = _ctrl.value.isPlaying;
    if (playing != _playing) {
      setState(() => _playing = playing);
      if (playing) {
        _scheduleHideControls();
      } else {
        _hideTimer?.cancel();
        setState(() => _showControls = true);
      }
    } else {
      setState(() {});
    }
  }

  void _scheduleHideControls() {
    _hideTimer?.cancel();
    _hideTimer = Timer(const Duration(seconds: 3), () {
      if (mounted && _playing) setState(() => _showControls = false);
    });
  }

  void _toggleControls() {
    setState(() => _showControls = !_showControls);
    if (_showControls && _playing) _scheduleHideControls();
  }

  void _togglePlay() {
    if (!_initialized) return;
    if (_ctrl.value.isPlaying) {
      _ctrl.pause();
    } else {
      if (_ctrl.value.position >= _ctrl.value.duration) {
        _ctrl.seekTo(Duration.zero);
      }
      _ctrl.play();
    }
    setState(() => _showControls = true);
    _scheduleHideControls();
  }

  void _toggleMute() {
    _muted = !_muted;
    _ctrl.setVolume(_muted ? 0 : 1);
    setState(() {});
    _scheduleHideControls();
  }

  String _fmt(Duration d) {
    final m = d.inMinutes.remainder(60).toString().padLeft(2, '0');
    final s = d.inSeconds.remainder(60).toString().padLeft(2, '0');
    final h = d.inHours;
    return h > 0 ? '$h:$m:$s' : '$m:$s';
  }

  @override
  void dispose() {
    _hideTimer?.cancel();
    _ctrl.removeListener(_onControllerUpdate);
    _ctrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Stack(
      fit: StackFit.expand,
      children: [
        // Close
        Positioned(
          top: 8.h,
          right: 8.w,
          child: Material(
            color: Colors.black45,
            shape: const CircleBorder(),
            child: InkWell(
              customBorder: const CircleBorder(),
              onTap: () => Navigator.pop(context),
              child: Padding(
                padding: EdgeInsets.all(8.sp),
                child: Icon(Icons.close, color: Colors.white, size: 22.sp),
              ),
            ),
          ),
        ),

        if (_failed)
          const Center(
            child: Icon(Icons.error_outline, color: Colors.white54, size: 64),
          )
        else if (!_initialized)
          const Center(child: CircularProgressIndicator(color: Colors.white))
        else
          GestureDetector(
            behavior: HitTestBehavior.opaque,
            onTap: _toggleControls,
            child: Stack(
              alignment: Alignment.center,
              fit: StackFit.expand,
              children: [
                Center(
                  child: AspectRatio(
                    aspectRatio: _ctrl.value.aspectRatio == 0
                        ? 16 / 9
                        : _ctrl.value.aspectRatio,
                    child: VideoPlayer(_ctrl),
                  ),
                ),
                AnimatedOpacity(
                  opacity: _showControls ? 1 : 0,
                  duration: const Duration(milliseconds: 180),
                  child: IgnorePointer(
                    ignoring: !_showControls,
                    child: GestureDetector(
                      onTap: _togglePlay,
                      child: Container(
                        width: 64.sp,
                        height: 64.sp,
                        decoration: const BoxDecoration(
                          color: Colors.black54,
                          shape: BoxShape.circle,
                        ),
                        child: Icon(
                          _playing
                              ? Icons.pause_rounded
                              : Icons.play_arrow_rounded,
                          color: Colors.white,
                          size: 40.sp,
                        ),
                      ),
                    ),
                  ),
                ),
                Positioned(
                  left: 12.w,
                  right: 12.w,
                  bottom: 24.h,
                  child: AnimatedOpacity(
                    opacity: _showControls ? 1 : 0,
                    duration: const Duration(milliseconds: 180),
                    child: IgnorePointer(
                      ignoring: !_showControls,
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          VideoProgressIndicator(
                            _ctrl,
                            allowScrubbing: true,
                            padding: EdgeInsets.symmetric(vertical: 6.h),
                            colors: const VideoProgressColors(
                              playedColor: Colors.white,
                              bufferedColor: Colors.white38,
                              backgroundColor: Colors.white24,
                            ),
                          ),
                          Row(
                            children: [
                              Text(
                                '${_fmt(_ctrl.value.position)} / ${_fmt(_ctrl.value.duration)}',
                                style: TextStyle(
                                  color: Colors.white,
                                  fontSize: 12.sp,
                                  fontWeight: FontWeight.w500,
                                ),
                              ),
                              const Spacer(),
                              GestureDetector(
                                onTap: _toggleMute,
                                child: Icon(
                                  _muted
                                      ? Icons.volume_off
                                      : Icons.volume_up,
                                  color: Colors.white,
                                  size: 22.sp,
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}
