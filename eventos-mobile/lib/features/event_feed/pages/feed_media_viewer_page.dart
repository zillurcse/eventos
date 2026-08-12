import 'dart:async';

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';
import 'package:video_player/video_player.dart';
import 'package:youtube_player_flutter/youtube_player_flutter.dart';

import '../../../models/event_feed_model.dart';
import '../../../utils/config/app_config.dart';
import '../../../widgets/cards/video_card.dart';
import '../../../widgets/custom_image.dart';
import '../event_feed_controller.dart';

/// Opens a Facebook-style full-screen media viewer for a feed post.
void openFeedMediaViewer(
  BuildContext context,
  FeedPostModel post, {
  required bool isVideo,
}) {
  final url = post.attachUrl;
  if (url == null || url.isEmpty) return;

  Navigator.of(context).push(
    PageRouteBuilder(
      opaque: true,
      barrierColor: Colors.black,
      transitionDuration: const Duration(milliseconds: 220),
      reverseTransitionDuration: const Duration(milliseconds: 180),
      pageBuilder: (_, __, ___) => FeedMediaViewerPage(
        post: post,
        isVideo: _resolveIsVideo(post, isVideo),
      ),
      transitionsBuilder: (_, animation, __, child) {
        return FadeTransition(opacity: animation, child: child);
      },
    ),
  );
}

bool _resolveIsVideo(FeedPostModel post, bool hinted) {
  final kind = post.attachType?.toLowerCase();
  if (kind == 'video') return true;
  if (kind == 'image') return false;

  final url = AppConfig.resolveMediaUrl(post.attachUrl ?? '').toLowerCase();
  final looksLikeVideo = RegExp(
    r'\.(mp4|mov|m4v|webm|mkv|avi|3gp)(\?|$|#)',
  ).hasMatch(url);
  if (looksLikeVideo) return true;

  return hinted;
}

/// Facebook-style media viewer: large media, author overlay, caption, actions.
/// Images support pinch-zoom; videos auto-play with play/pause, scrub, mute.
class FeedMediaViewerPage extends StatefulWidget {
  final FeedPostModel post;
  final bool isVideo;

  const FeedMediaViewerPage({
    super.key,
    required this.post,
    required this.isVideo,
  });

  @override
  State<FeedMediaViewerPage> createState() => _FeedMediaViewerPageState();
}

class _FeedMediaViewerPageState extends State<FeedMediaViewerPage> {
  bool _captionExpanded = false;

  String get _mediaUrl =>
      AppConfig.resolveMediaUrl(widget.post.attachUrl ?? '');

  String get _relativeTime {
    final diff = DateTime.now().difference(widget.post.createdAtTime);
    if (diff.inMinutes < 1) return 'JUST NOW';
    if (diff.inMinutes < 60) return '${diff.inMinutes} MIN AGO';
    if (diff.inHours < 24) return '${diff.inHours} HOURS AGO';
    if (diff.inDays < 7) return '${diff.inDays} DAYS AGO';
    return DateFormat('dd MMM yyyy').format(widget.post.createdAtTime).toUpperCase();
  }

  @override
  void initState() {
    super.initState();
    SystemChrome.setSystemUIOverlayStyle(SystemUiOverlayStyle.light);
  }

  @override
  Widget build(BuildContext context) {
    final body = widget.post.body?.trim() ?? '';
    final showSeeMore = body.length > 120 && !_captionExpanded;
    final caption = showSeeMore ? '${body.substring(0, 120).trimRight()}…' : body;

    return Scaffold(
      backgroundColor: const Color(0xFF18191A),
      body: SafeArea(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // ── Media + overlays ──────────────────────────────────────────
            Expanded(
              flex: 6,
              child: Stack(
                fit: StackFit.expand,
                children: [
                  ColoredBox(
                    color: Colors.black,
                    child: widget.isVideo
                        ? _VideoPane(
                            url: _mediaUrl,
                            posterUrl: widget.post.attachPoster,
                          )
                        : _ImagePane(url: _mediaUrl),
                  ),

                  // Close
                  Positioned(
                    top: 8.h,
                    left: 8.w,
                    child: _CircleIconButton(
                      icon: Icons.close,
                      onTap: () => Navigator.pop(context),
                    ),
                  ),

                  // Author overlay (Facebook-style bottom-left on media)
                  Positioned(
                    left: 12.w,
                    right: 12.w,
                    bottom: 12.h,
                    child: Row(
                      children: [
                        CustomImage(
                          widget.post.user.profilePhotoUrl,
                          width: 36.sp,
                          height: 36.sp,
                          radius: 18.r,
                          avatar: true,
                          fit: BoxFit.cover,
                        ),
                        SizedBox(width: 10.w),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(
                                widget.post.user.name,
                                style: TextStyle(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w700,
                                  fontSize: 14.sp,
                                  shadows: const [
                                    Shadow(
                                      color: Colors.black54,
                                      blurRadius: 6,
                                    ),
                                  ],
                                ),
                              ),
                              SizedBox(height: 2.h),
                              Row(
                                children: [
                                  Text(
                                    _relativeTime,
                                    style: TextStyle(
                                      color: Colors.white70,
                                      fontSize: 11.sp,
                                      fontWeight: FontWeight.w600,
                                      letterSpacing: 0.3,
                                      shadows: const [
                                        Shadow(
                                          color: Colors.black54,
                                          blurRadius: 4,
                                        ),
                                      ],
                                    ),
                                  ),
                                  SizedBox(width: 4.w),
                                  Icon(
                                    Icons.public,
                                    size: 12.sp,
                                    color: Colors.white70,
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),

            // ── Caption ───────────────────────────────────────────────────
            if (body.isNotEmpty)
              Padding(
                padding: EdgeInsets.fromLTRB(16.w, 14.h, 16.w, 0),
                child: GestureDetector(
                  onTap: () {
                    if (body.length > 120) {
                      setState(() => _captionExpanded = !_captionExpanded);
                    }
                  },
                  child: Text.rich(
                    TextSpan(
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 14.sp,
                        height: 1.35,
                      ),
                      children: [
                        TextSpan(text: caption),
                        if (showSeeMore)
                          TextSpan(
                            text: ' See more',
                            style: TextStyle(
                              color: Colors.white60,
                              fontWeight: FontWeight.w600,
                              fontSize: 14.sp,
                            ),
                          ),
                      ],
                    ),
                  ),
                ),
              ),

            // ── Stats + actions ───────────────────────────────────────────
            _EngagementBar(post: widget.post),
          ],
        ),
      ),
    );
  }
}

// ── Image pane ──────────────────────────────────────────────────────────────

class _ImagePane extends StatefulWidget {
  final String url;
  const _ImagePane({required this.url});

  @override
  State<_ImagePane> createState() => _ImagePaneState();
}

class _ImagePaneState extends State<_ImagePane> {
  static const _loadTimeout = Duration(seconds: 30);
  bool _timedOut = false;
  Timer? _timer;
  int _retryKey = 0;

  @override
  void initState() {
    super.initState();
    _startTimeout();
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  void _startTimeout() {
    _timer?.cancel();
    if (widget.url.isEmpty) return;
    _timer = Timer(_loadTimeout, () {
      if (mounted) setState(() => _timedOut = true);
    });
  }

  void _retry() {
    setState(() {
      _timedOut = false;
      _retryKey++;
    });
    _startTimeout();
  }

  @override
  Widget build(BuildContext context) {
    if (widget.url.isEmpty) {
      return const Center(
        child: Icon(Icons.broken_image, color: Colors.white54, size: 64),
      );
    }

    if (_timedOut) {
      return _MediaError(
        message: 'Image is taking too long to load',
        onRetry: _retry,
      );
    }

    return InteractiveViewer(
      minScale: 0.8,
      maxScale: 4,
      child: Center(
        child: CachedNetworkImage(
          key: ValueKey('$_retryKey-${widget.url}'),
          imageUrl: widget.url,
          fit: BoxFit.contain,
          width: double.infinity,
          height: double.infinity,
          fadeInDuration: const Duration(milliseconds: 200),
          progressIndicatorBuilder: (_, __, progress) => Center(
            child: CircularProgressIndicator(
              color: Colors.white,
              value: progress.progress,
            ),
          ),
          errorWidget: (_, __, ___) => _MediaError(
            message: 'Could not load image',
            onRetry: _retry,
          ),
          imageBuilder: (context, imageProvider) {
            _timer?.cancel();
            return Image(image: imageProvider, fit: BoxFit.contain);
          },
        ),
      ),
    );
  }
}

class _MediaError extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;

  const _MediaError({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.broken_image, color: Colors.white54, size: 48),
          SizedBox(height: 12.h),
          Text(
            message,
            style: TextStyle(color: Colors.white70, fontSize: 14.sp),
            textAlign: TextAlign.center,
          ),
          SizedBox(height: 16.h),
          TextButton.icon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh, color: Colors.white),
            label: const Text('Retry', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
  }
}

// ── Video pane ──────────────────────────────────────────────────────────────

class _VideoPane extends StatefulWidget {
  final String url;
  final String? posterUrl;
  const _VideoPane({required this.url, this.posterUrl});

  @override
  State<_VideoPane> createState() => _VideoPaneState();
}

class _VideoPaneState extends State<_VideoPane> {
  @override
  Widget build(BuildContext context) {
    if (widget.url.isEmpty) {
      return const Center(
        child: Icon(Icons.videocam_off, color: Colors.white54, size: 64),
      );
    }

    if (isYoutubeUrl(widget.url)) {
      final id = YoutubePlayer.convertUrlToId(widget.url);
      if (id == null) {
        return _MediaError(
          message: 'Could not load video',
          onRetry: () => setState(() {}),
        );
      }
      return _YoutubePlayerPane(videoId: id);
    }

    return _NetworkVideoPane(
      url: widget.url,
      posterUrl: widget.posterUrl,
    );
  }
}

class _NetworkVideoPane extends StatefulWidget {
  final String url;
  final String? posterUrl;

  const _NetworkVideoPane({required this.url, this.posterUrl});

  @override
  State<_NetworkVideoPane> createState() => _NetworkVideoPaneState();
}

class _NetworkVideoPaneState extends State<_NetworkVideoPane> {
  static const _initTimeout = Duration(seconds: 45);

  VideoPlayerController? _ctrl;
  bool _initialized = false;
  bool _failed = false;
  String? _errorMessage;
  bool _playing = false;
  bool _muted = false;
  bool _showControls = true;
  Timer? _hideTimer;

  String? get _poster =>
      (widget.posterUrl != null && widget.posterUrl!.trim().isNotEmpty)
          ? AppConfig.resolveMediaUrl(widget.posterUrl)
          : null;

  @override
  void initState() {
    super.initState();
    _initPlayer();
  }

  Future<void> _initPlayer() async {
    _hideTimer?.cancel();
    final previous = _ctrl;
    _ctrl = null;
    previous?.removeListener(_onUpdate);
    // Dispose after swap so a failed/slow init can't touch the active controller.
    unawaited(previous?.dispose() ?? Future.value());

    final uri = Uri.tryParse(widget.url);
    if (uri == null || !uri.hasScheme) {
      if (!mounted) return;
      setState(() {
        _failed = true;
        _initialized = false;
        _errorMessage = 'Invalid video URL';
      });
      return;
    }

    final ctrl = VideoPlayerController.networkUrl(
      uri,
      videoPlayerOptions: VideoPlayerOptions(mixWithOthers: true),
      httpHeaders: const {
        'Accept': '*/*',
        'Connection': 'keep-alive',
      },
    );
    _ctrl = ctrl;
    ctrl.addListener(_onUpdate);

    try {
      await ctrl.initialize().timeout(_initTimeout);
      if (!mounted || _ctrl != ctrl) return;
      if (ctrl.value.hasError) {
        throw StateError(ctrl.value.errorDescription ?? 'Video failed to load');
      }
      await ctrl.setVolume(1);
      await ctrl.seekTo(Duration.zero);
      setState(() {
        _initialized = true;
        _failed = false;
        _errorMessage = null;
        _showControls = true;
      });
      await ctrl.play();
      if (!mounted || _ctrl != ctrl) return;
      // Some devices report not-playing until buffered - nudge once more.
      if (!ctrl.value.isPlaying) {
        await ctrl.play();
      }
      _scheduleHideControls();
    } catch (e) {
      if (!mounted) return;
      if (_ctrl != ctrl) return;
      setState(() {
        _initialized = false;
        _failed = true;
        _errorMessage = e is TimeoutException
            ? 'Video is taking too long to load'
            : 'Could not play this video';
      });
    }
  }

  void _retry() {
    setState(() {
      _failed = false;
      _initialized = false;
      _errorMessage = null;
      _playing = false;
    });
    _initPlayer();
  }

  void _onUpdate() {
    if (!mounted || _ctrl == null) return;
    final value = _ctrl!.value;
    if (value.hasError && !_failed) {
      setState(() {
        _failed = true;
        _initialized = false;
        _errorMessage = value.errorDescription ?? 'Could not play this video';
      });
      return;
    }
    final playing = value.isPlaying;
    if (playing != _playing) {
      setState(() => _playing = playing);
      if (playing) {
        _scheduleHideControls();
      } else {
        _hideTimer?.cancel();
        setState(() => _showControls = true);
      }
    } else if (_initialized) {
      // Keep scrubber time in sync while playing.
      setState(() {});
    }
  }

  void _scheduleHideControls() {
    _hideTimer?.cancel();
    _hideTimer = Timer(const Duration(seconds: 3), () {
      if (mounted && _playing) {
        setState(() => _showControls = false);
      }
    });
  }

  void _onVideoTap() {
    // Primary action: play / pause (controls alone felt like “won’t play”).
    _togglePlay();
  }

  void _togglePlay() {
    final ctrl = _ctrl;
    if (!_initialized || ctrl == null || _failed) return;
    if (ctrl.value.isPlaying) {
      ctrl.pause();
    } else {
      if (ctrl.value.duration > Duration.zero &&
          ctrl.value.position >= ctrl.value.duration) {
        ctrl.seekTo(Duration.zero);
      }
      ctrl.play();
    }
    setState(() => _showControls = true);
    _scheduleHideControls();
  }

  void _toggleMute() {
    final ctrl = _ctrl;
    if (ctrl == null) return;
    _muted = !_muted;
    ctrl.setVolume(_muted ? 0 : 1);
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
    _ctrl?.removeListener(_onUpdate);
    _ctrl?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (_failed) {
      return Stack(
        fit: StackFit.expand,
        children: [
          if (_poster != null)
            CachedNetworkImage(
              imageUrl: _poster!,
              fit: BoxFit.contain,
              errorWidget: (_, __, ___) => const SizedBox.shrink(),
            ),
          ColoredBox(color: Colors.black.withValues(alpha: 0.55)),
          _MediaError(
            message: _errorMessage ?? 'Could not play this video',
            onRetry: _retry,
          ),
        ],
      );
    }

    if (!_initialized || _ctrl == null) {
      return Stack(
        fit: StackFit.expand,
        children: [
          if (_poster != null)
            CachedNetworkImage(
              imageUrl: _poster!,
              fit: BoxFit.contain,
              errorWidget: (_, __, ___) => const SizedBox.shrink(),
            ),
          const Center(
            child: CircularProgressIndicator(color: Colors.white),
          ),
        ],
      );
    }

    final value = _ctrl!.value;
    final duration = value.duration;
    final position = value.position;

    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: _onVideoTap,
      child: Stack(
        alignment: Alignment.center,
        fit: StackFit.expand,
        children: [
          Center(
            child: AspectRatio(
              aspectRatio: value.aspectRatio == 0 ? 16 / 9 : value.aspectRatio,
              child: VideoPlayer(_ctrl!),
            ),
          ),

          // Center play/pause
          AnimatedOpacity(
            opacity: (!_playing || _showControls) ? 1 : 0,
            duration: const Duration(milliseconds: 180),
            child: IgnorePointer(
              ignoring: _playing && !_showControls,
              child: Container(
                width: 64.sp,
                height: 64.sp,
                decoration: const BoxDecoration(
                  color: Colors.black54,
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  _playing ? Icons.pause_rounded : Icons.play_arrow_rounded,
                  color: Colors.white,
                  size: 40.sp,
                ),
              ),
            ),
          ),

          // Bottom controls: time + scrub + mute
          Positioned(
            left: 0,
            right: 0,
            bottom: 52.h,
            child: AnimatedOpacity(
              opacity: (!_playing || _showControls) ? 1 : 0,
              duration: const Duration(milliseconds: 180),
              child: IgnorePointer(
                ignoring: _playing && !_showControls,
                child: Padding(
                  padding: EdgeInsets.symmetric(horizontal: 12.w),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      VideoProgressIndicator(
                        _ctrl!,
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
                            '${_fmt(position)} / ${_fmt(duration)}',
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
                              _muted ? Icons.volume_off : Icons.volume_up,
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
          ),
        ],
      ),
    );
  }
}

class _YoutubePlayerPane extends StatefulWidget {
  final String videoId;
  const _YoutubePlayerPane({required this.videoId});

  @override
  State<_YoutubePlayerPane> createState() => _YoutubePlayerPaneState();
}

class _YoutubePlayerPaneState extends State<_YoutubePlayerPane> {
  late final YoutubePlayerController _ytCtrl;

  @override
  void initState() {
    super.initState();
    _ytCtrl = YoutubePlayerController(
      initialVideoId: widget.videoId,
      flags: const YoutubePlayerFlags(
        autoPlay: true,
        mute: false,
        enableCaption: false,
      ),
    );
  }

  @override
  void dispose() {
    _ytCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Center(
      child: YoutubePlayer(
        controller: _ytCtrl,
        showVideoProgressIndicator: true,
        progressIndicatorColor: Colors.white,
        progressColors: const ProgressBarColors(
          playedColor: Colors.white,
          handleColor: Colors.white,
          bufferedColor: Colors.white38,
          backgroundColor: Colors.white24,
        ),
      ),
    );
  }
}

// ── Engagement ──────────────────────────────────────────────────────────────

class _EngagementBar extends StatelessWidget {
  final FeedPostModel post;
  const _EngagementBar({required this.post});

  @override
  Widget build(BuildContext context) {
    final hasCtrl = Get.isRegistered<EventFeedController>();

    Widget buildRow(FeedPostModel p) {
      return Padding(
        padding: EdgeInsets.fromLTRB(8.w, 10.h, 8.w, 8.h),
        child: Column(
          children: [
            Padding(
              padding: EdgeInsets.symmetric(horizontal: 8.w),
              child: Row(
                children: [
                  Icon(Icons.favorite, size: 14.sp, color: Colors.redAccent),
                  SizedBox(width: 6.w),
                  Text(
                    '${p.like}',
                    style: TextStyle(color: Colors.white70, fontSize: 13.sp),
                  ),
                  const Spacer(),
                  Text(
                    '${p.comments.length} ${p.comments.length == 1 ? 'comment' : 'comments'}',
                    style: TextStyle(color: Colors.white70, fontSize: 13.sp),
                  ),
                ],
              ),
            ),
            Divider(color: Colors.white12, height: 16.h),
            Row(
              children: [
                Expanded(
                  child: _ActionButton(
                    icon: p.isLiked ? Icons.favorite : Icons.favorite_border,
                    label: 'Like',
                    color: p.isLiked ? Colors.redAccent : Colors.white70,
                    onTap: hasCtrl
                        ? () {
                            HapticFeedback.lightImpact();
                            Get.find<EventFeedController>().toggleLike(p.id);
                          }
                        : null,
                  ),
                ),
                Expanded(
                  child: _ActionButton(
                    icon: Icons.chat_bubble_outline,
                    label: 'Comment',
                    color: Colors.white70,
                    onTap: () => Navigator.pop(context),
                  ),
                ),
              ],
            ),
          ],
        ),
      );
    }

    if (!hasCtrl) return buildRow(post);

    return Obx(() {
      final ctrl = Get.find<EventFeedController>();
      final live =
          ctrl.posts.firstWhereOrNull((e) => e.id == post.id) ?? post;
      return buildRow(live);
    });
  }
}

class _ActionButton extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback? onTap;

  const _ActionButton({
    required this.icon,
    required this.label,
    required this.color,
    this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8.r),
      child: Padding(
        padding: EdgeInsets.symmetric(vertical: 10.h),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 20.sp, color: color),
            SizedBox(width: 8.w),
            Text(
              label,
              style: TextStyle(
                color: color,
                fontSize: 14.sp,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _CircleIconButton extends StatelessWidget {
  final IconData icon;
  final VoidCallback onTap;

  const _CircleIconButton({required this.icon, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.black45,
      shape: const CircleBorder(),
      child: InkWell(
        customBorder: const CircleBorder(),
        onTap: onTap,
        child: Padding(
          padding: EdgeInsets.all(8.sp),
          child: Icon(icon, color: Colors.white, size: 22.sp),
        ),
      ),
    );
  }
}
