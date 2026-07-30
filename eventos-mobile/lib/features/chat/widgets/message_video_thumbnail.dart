import 'dart:io';
import 'package:flutter/material.dart';
import 'package:video_player/video_player.dart';

class MessageVideoThumbnail extends StatefulWidget {
  final String url;
  final bool isMe;

  const MessageVideoThumbnail({super.key, required this.url, required this.isMe});

  @override
  State<MessageVideoThumbnail> createState() => MessageVideoThumbnailState();
}

class MessageVideoThumbnailState extends State<MessageVideoThumbnail> {
  late VideoPlayerController _controller;
  bool _initialized = false;
  bool _playing = false;

  @override
  void initState() {
    super.initState();
    _initController();
  }

  void _initController() {
    final bool isLocal = widget.url.startsWith('/') || widget.url.startsWith('file://');
    _controller = isLocal
        ? VideoPlayerController.file(File(widget.url))
        : VideoPlayerController.networkUrl(Uri.parse(widget.url));

    _controller.initialize().then((_) {
      if (mounted) setState(() => _initialized = true);
    });

    _controller.addListener(() {
      if (mounted) setState(() => _playing = _controller.value.isPlaying);
    });
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _togglePlay() {
    if (_controller.value.isPlaying) {
      _controller.pause();
    } else {
      if (_controller.value.position >= _controller.value.duration) {
        _controller.seekTo(Duration.zero);
      }
      _controller.play();
    }
  }

  @override
  Widget build(BuildContext context) {
    if (!_initialized) {
      return Container(
        height: 180,
        color: Colors.black26,
        child: const Center(child: CircularProgressIndicator(color: Colors.white)),
      );
    }

    return Stack(
      alignment: Alignment.center,
      children: [
        AspectRatio(
          aspectRatio: _controller.value.aspectRatio,
          child: VideoPlayer(_controller),
        ),
        GestureDetector(
          onTap: _togglePlay,
          child: AnimatedOpacity(
            opacity: _playing ? 0.0 : 1.0,
            duration: const Duration(milliseconds: 200),
            child: Container(
              width: 52,
              height: 52,
              decoration: const BoxDecoration(color: Colors.black54, shape: BoxShape.circle),
              child: const Icon(Icons.play_arrow, color: Colors.white, size: 32),
            ),
          ),
        ),
        if (_playing)
          GestureDetector(
            onTap: _togglePlay,
            child: Container(color: Colors.transparent),
          ),
        Positioned(
          bottom: 0,
          left: 0,
          right: 0,
          child: VideoProgressIndicator(
            _controller,
            allowScrubbing: true,
            colors: VideoProgressColors(
              playedColor: widget.isMe ? Colors.white : Theme.of(context).primaryColor,
              bufferedColor: Colors.white38,
              backgroundColor: Colors.white24,
            ),
          ),
        ),
      ],
    );
  }
}
