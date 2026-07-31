import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:video_player/video_player.dart';
import 'package:youtube_player_flutter/youtube_player_flutter.dart';

import '../../../models/session_detail_response_model.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/cards/video_card.dart';
import '../session_phase.dart';
import '../session_service.dart';

enum _PlayerKind {
  none,
  upcoming,
  youtube,
  video,
  join,
  replay,
}

class _PlayerSpec {
  final _PlayerKind kind;
  final String? src;
  final String? label;
  final String? note;

  const _PlayerSpec(this.kind, {this.src, this.label, this.note});
}

class SessionPlayer extends StatefulWidget {
  final SessionDetailModel detail;

  const SessionPlayer({super.key, required this.detail});

  @override
  State<SessionPlayer> createState() => _SessionPlayerState();
}

class _SessionPlayerState extends State<SessionPlayer> {
  late SessionPhase _phase;
  Timer? _ticker;
  YoutubePlayerController? _ytController;
  VideoPlayerController? _videoController;
  bool _videoReady = false;
  bool _joining = false;

  static const _hostLabels = {
    'youtube': 'YouTube',
    'meet': 'Google Meet',
    'zoom': 'Zoom',
    'rtmp': 'Live Stream',
    'self': 'Live Stream',
    'jitsi': 'Jitsi',
    'agora': 'Live Stream',
  };

  @override
  void initState() {
    super.initState();
    _phase = _computePhase();
    _ticker = Timer.periodic(const Duration(seconds: 15), (_) {
      final next = _computePhase();
      if (next != _phase) {
        setState(() => _phase = next);
        _setupPlayers();
      }
      // Countdown text is owned by [_UpcomingCountdown]; no setState here.
    });
    _setupPlayers();
  }

  @override
  void didUpdateWidget(covariant SessionPlayer oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.detail.uuid != widget.detail.uuid) {
      _disposePlayers();
      _phase = _computePhase();
      _setupPlayers();
    }
  }

  @override
  void dispose() {
    _ticker?.cancel();
    _disposePlayers();
    super.dispose();
  }

  SessionPhase _computePhase() => SessionPhaseHelper.resolve(
        status: widget.detail.status,
        startsAt: widget.detail.startsAt,
        endsAt: widget.detail.endsAt,
      );

  void _disposePlayers() {
    _ytController?.dispose();
    _ytController = null;
    _videoController?.dispose();
    _videoController = null;
    _videoReady = false;
  }

  String _decodeLink(String url) {
    final u = url.trim();
    if (!RegExp(r'%[0-9a-fA-F]{2}').hasMatch(u)) return u;
    try {
      return Uri.decodeComponent(u);
    } catch (_) {
      return u;
    }
  }

  String? _youtubeId(String? url) {
    if (url == null || url.isEmpty) return null;
    return YoutubePlayer.convertUrlToId(_decodeLink(url));
  }

  String? _mediaUrl(String? url) {
    if (url == null || url.isEmpty) return null;
    final u = url.trim();
    if (RegExp(r'\.(m3u8|mpd|mp4|webm|ogv|ogg)(\?.*)?$', caseSensitive: false)
        .hasMatch(u)) {
      return u;
    }
    return null;
  }

  _PlayerSpec _resolve() {
    final s = widget.detail;

    if (_phase == SessionPhase.live && s.isStream) {
      if (s.whoWillHost == 'youtube') {
        final id = _youtubeId(s.streamLink);
        if (id != null) return _PlayerSpec(_PlayerKind.youtube, src: id);
      }
      if (s.whoWillHost == 'zoom' &&
          s.streamLink != null &&
          s.streamLink!.isNotEmpty) {
        return _PlayerSpec(_PlayerKind.join,
            src: s.streamLink, label: 'Join on Zoom');
      }
      if (s.whoWillHost == 'jitsi') {
        return _PlayerSpec(_PlayerKind.join,
            src: s.streamLink, label: 'Join on Jitsi');
      }
      if (s.whoWillHost == 'agora') {
        return _PlayerSpec(_PlayerKind.join,
            src: s.streamLink, label: 'Join Live Stream');
      }
      if (s.vimeoLiveId != null && s.vimeoLiveId!.isNotEmpty) {
        return _PlayerSpec(
          _PlayerKind.join,
          src: 'https://vimeo.com/event/${s.vimeoLiveId}/embed',
          label: 'Watch on Vimeo',
        );
      }

      final live = _mediaUrl(s.streamLink);
      if (live != null) return _PlayerSpec(_PlayerKind.video, src: live);

      if (s.streamLink != null && s.streamLink!.isNotEmpty) {
        final host = s.whoWillHost ?? '';
        final label = host == 'youtube'
            ? 'Watch on YouTube'
            : 'Join on ${_hostLabels[host] ?? 'Live Stream'}';
        return _PlayerSpec(_PlayerKind.join, src: s.streamLink, label: label);
      }
    }

    if (_phase == SessionPhase.upcoming) {
      return const _PlayerSpec(_PlayerKind.upcoming);
    }

    final recording = s.onDemandRecordingLink;
    if (recording != null && recording.isNotEmpty) {
      final ytId = _youtubeId(recording);
      if (ytId != null) {
        return _PlayerSpec(_PlayerKind.youtube, src: ytId, note: 'Recording');
      }
      final file = _mediaUrl(recording);
      if (file != null) {
        return _PlayerSpec(_PlayerKind.video, src: file, note: 'Recording');
      }
      return _PlayerSpec(_PlayerKind.replay, src: recording, label: 'Watch Recording');
    }

    return const _PlayerSpec(_PlayerKind.none);
  }

  Future<void> _setupPlayers() async {
    final spec = _resolve();
    _disposePlayers();

    if (spec.kind == _PlayerKind.youtube && spec.src != null) {
      _ytController = YoutubePlayerController(
        initialVideoId: spec.src!,
        flags: YoutubePlayerFlags(
          autoPlay: _phase == SessionPhase.live,
          mute: false,
        ),
      );
      if (mounted) setState(() {});
      return;
    }

    if (spec.kind == _PlayerKind.video && spec.src != null) {
      final controller = VideoPlayerController.networkUrl(Uri.parse(spec.src!));
      _videoController = controller;
      try {
        await controller.initialize();
        if (_phase == SessionPhase.live) {
          await controller.play();
        }
        if (mounted) setState(() => _videoReady = true);
      } catch (_) {
        if (mounted) setState(() => _videoReady = false);
      }
    } else if (mounted) {
      setState(() {});
    }
  }

  Future<void> _openJoin(String? url, {String? host}) async {
    if (_joining) return;
    setState(() => _joining = true);
    try {
      var link = url;
      final service = SessionService();
      final uuid = widget.detail.uuid;

      if (host == 'zoom' && uuid.isNotEmpty) {
        try {
          final res = await service.getZoomSignature(uuid);
          final data = res.data is Map
              ? (res.data['data'] is Map
                  ? Map<String, dynamic>.from(res.data['data'] as Map)
                  : Map<String, dynamic>.from(res.data as Map))
              : <String, dynamic>{};
          final meeting = data['meeting_number']?.toString();
          final password = data['password']?.toString() ?? '';
          if (meeting != null && meeting.isNotEmpty) {
            link = password.isNotEmpty
                ? 'https://zoom.us/j/$meeting?pwd=$password'
                : 'https://zoom.us/j/$meeting';
          }
        } catch (_) {}
      } else if (host == 'jitsi' && uuid.isNotEmpty) {
        try {
          final res = await service.getJitsiToken(uuid);
          final data = res.data is Map
              ? (res.data['data'] is Map
                  ? Map<String, dynamic>.from(res.data['data'] as Map)
                  : Map<String, dynamic>.from(res.data as Map))
              : <String, dynamic>{};
          final roomUrl = (data['url'] ?? data['room_url'] ?? data['join_url'])
              ?.toString();
          if (roomUrl != null && roomUrl.isNotEmpty) link = roomUrl;
        } catch (_) {}
      }

      if (link == null || link.isEmpty) {
        Get.snackbar(
          'Stream',
          'Join link is not available yet.',
          snackPosition: SnackPosition.BOTTOM,
        );
        return;
      }
      final uri = Uri.tryParse(link);
      if (uri != null) {
        await launchUrl(uri, mode: LaunchMode.externalApplication);
      }
    } finally {
      if (mounted) setState(() => _joining = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final spec = _resolve();
    if (spec.kind == _PlayerKind.none) return const SizedBox.shrink();

    return Container(
      width: double.infinity,
      color: Colors.black,
      child: AspectRatio(
        aspectRatio: 16 / 9,
        child: Stack(
          fit: StackFit.expand,
          children: [
            if (spec.kind == _PlayerKind.youtube && _ytController != null)
              YoutubePlayer(
                controller: _ytController!,
                showVideoProgressIndicator: true,
              )
            else if (spec.kind == _PlayerKind.video &&
                _videoReady &&
                _videoController != null)
              GestureDetector(
                onTap: () {
                  final c = _videoController!;
                  if (c.value.isPlaying) {
                    c.pause();
                  } else {
                    c.play();
                  }
                  setState(() {});
                },
                child: VideoPlayer(_videoController!),
              )
            else if (spec.kind == _PlayerKind.upcoming)
              _UpcomingCountdown(
                startsAt: widget.detail.startsAt,
                startTimeLabel: widget.detail.startTime,
              )
            else if (spec.kind == _PlayerKind.join ||
                spec.kind == _PlayerKind.replay)
              _placeholder(
                context,
                icon: Icons.play_circle_outline,
                title: spec.label ?? 'Watch',
                subtitle: spec.note,
                actionLabel: spec.label ?? 'Open',
                onAction: () => _openJoin(
                  spec.src,
                  host: widget.detail.whoWillHost,
                ),
                loading: _joining,
              )
            else
              _placeholder(
                context,
                icon: Icons.videocam_off_outlined,
                title: 'Stream unavailable',
              ),
            if (spec.note != null &&
                (spec.kind == _PlayerKind.youtube ||
                    spec.kind == _PlayerKind.video))
              Positioned(
                left: 12.w,
                top: 12.h,
                child: Container(
                  padding:
                      EdgeInsets.symmetric(horizontal: 8.w, vertical: 4.h),
                  decoration: BoxDecoration(
                    color: Colors.black54,
                    borderRadius: BorderRadius.circular(4.r),
                  ),
                  child: Text(
                    spec.note!,
                    style: context.specialCaption2
                        ?.copyWith(color: Colors.white),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _placeholder(
    BuildContext context, {
    required IconData icon,
    required String title,
    String? subtitle,
    String? actionLabel,
    VoidCallback? onAction,
    bool loading = false,
  }) {
    return Container(
      color: const Color(0xFF1A1A1A),
      alignment: Alignment.center,
      padding: EdgeInsets.all(24.sp),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(icon, color: Colors.white70, size: 40.sp),
          SizedBox(height: 12.h),
          Text(
            title,
            textAlign: TextAlign.center,
            style: context.h2?.copyWith(color: Colors.white),
          ),
          if (subtitle != null) ...[
            SizedBox(height: 6.h),
            Text(
              subtitle,
              textAlign: TextAlign.center,
              style: context.bodyRegular?.copyWith(color: Colors.white70),
            ),
          ],
          if (onAction != null) ...[
            SizedBox(height: 16.h),
            ElevatedButton(
              onPressed: loading ? null : onAction,
              style: ElevatedButton.styleFrom(
                backgroundColor: context.primaryTheme,
                padding:
                    EdgeInsets.symmetric(horizontal: 20.w, vertical: 10.h),
              ),
              child: loading
                  ? SizedBox(
                      width: 18.sp,
                      height: 18.sp,
                      child: const CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    )
                  : Text(
                      actionLabel ?? 'Open',
                      style: context.buttonMediumBold
                          ?.copyWith(color: Colors.white),
                    ),
            ),
          ],
        ],
      ),
    );
  }
}

/// Owns its own timer so countdown ticks don't rebuild the video player.
class _UpcomingCountdown extends StatefulWidget {
  final String? startsAt;
  final String startTimeLabel;

  const _UpcomingCountdown({
    required this.startsAt,
    required this.startTimeLabel,
  });

  @override
  State<_UpcomingCountdown> createState() => _UpcomingCountdownState();
}

class _UpcomingCountdownState extends State<_UpcomingCountdown> {
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _timer = Timer.periodic(const Duration(seconds: 15), (_) {
      if (mounted) setState(() {});
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  String get _label {
    final start = DateTime.tryParse(widget.startsAt ?? '');
    if (start == null) return 'Starts soon';
    final diff = start.difference(DateTime.now());
    if (diff.isNegative) return 'Starting soon';
    if (diff.inDays > 0) {
      return 'Starts in ${diff.inDays}d ${diff.inHours % 24}h';
    }
    if (diff.inHours > 0) {
      return 'Starts in ${diff.inHours}h ${diff.inMinutes % 60}m';
    }
    return 'Starts in ${diff.inMinutes}m';
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      color: const Color(0xFF1A1A1A),
      alignment: Alignment.center,
      padding: EdgeInsets.all(24.sp),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.schedule, color: Colors.white70, size: 40.sp),
          SizedBox(height: 12.h),
          Text(
            _label,
            textAlign: TextAlign.center,
            style: context.h2?.copyWith(color: Colors.white),
          ),
          if (widget.startTimeLabel.isNotEmpty) ...[
            SizedBox(height: 6.h),
            Text(
              'Scheduled for ${widget.startTimeLabel}',
              textAlign: TextAlign.center,
              style: context.bodyRegular?.copyWith(color: Colors.white70),
            ),
          ],
        ],
      ),
    );
  }
}

/// Shared helper used by sticky CTA.
Future<void> openSessionStream(SessionDetailModel detail) async {
  final phase = SessionPhaseHelper.resolve(
    status: detail.status,
    startsAt: detail.startsAt,
    endsAt: detail.endsAt,
  );

  if (phase == SessionPhase.upcoming) {
    Get.snackbar(
      'Upcoming',
      'This session has not started yet.',
      snackPosition: SnackPosition.BOTTOM,
    );
    return;
  }

  String? link = detail.streamLink;
  if (phase == SessionPhase.ended) {
    link = detail.onDemandRecordingLink ?? detail.streamLink;
  }

  if (link != null && link.isNotEmpty && isYoutubeUrl(link)) {
    final id = YoutubePlayer.convertUrlToId(link);
    if (id != null && Get.context != null) {
      openVideoModal(Get.context!, link);
      return;
    }
  }

  if (link == null || link.isEmpty) {
    Get.snackbar(
      'Stream',
      'Stream link is not available yet.',
      snackPosition: SnackPosition.BOTTOM,
    );
    return;
  }

  final uri = Uri.tryParse(link);
  if (uri != null) {
    await launchUrl(uri, mode: LaunchMode.externalApplication);
  }
}
