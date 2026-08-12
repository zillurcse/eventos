import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:youtube_player_flutter/youtube_player_flutter.dart';

import '../custom_image.dart';
import 'normal_video_card.dart';
import 'youtube_video_card.dart';

// ── Helpers ────────────────────────────────────────────────────────────────────

/// Returns true if [url] is a YouTube link.
bool isYoutubeUrl(String url) =>
    url.contains('youtube.com') || url.contains('youtu.be');

/// Returns the YouTube maxresdefault thumbnail for [url], or null if invalid.
String? _ytThumbnail(String url) {
  final regExp = RegExp(
    r'^.*(youtu\.be\/|v\/|u\/\w\/|embed\/|watch\?v=|&v=)([^#&\?]*).*',
    caseSensitive: false,
  );
  final match = regExp.firstMatch(url);
  if (match != null && match.groupCount >= 2) {
    final id = match.group(2);
    if (id != null && id.length == 11) {
      return 'https://img.youtube.com/vi/$id/maxresdefault.jpg';
    }
  }
  return null;
}

/// Opens a full-screen dark video player for [videoUrl]:
///   • YouTube URL  → [YoutubeVideoCard]
///   • Other URL    → [NormalVideoCard]
void openVideoModal(BuildContext context, String videoUrl) {
  if (videoUrl.isEmpty) return;

  final Widget player;
  if (isYoutubeUrl(videoUrl)) {
    final videoId = YoutubePlayer.convertUrlToId(videoUrl);
    if (videoId == null) return;
    player = YoutubeVideoCard(videoId: videoId);
  } else {
    player = NormalVideoCard(videoUrl: videoUrl);
  }

  Navigator.of(context).push(
    PageRouteBuilder(
      opaque: true,
      barrierColor: Colors.black,
      transitionDuration: const Duration(milliseconds: 220),
      reverseTransitionDuration: const Duration(milliseconds: 180),
      pageBuilder: (_, __, ___) => Scaffold(
        backgroundColor: Colors.black,
        body: SafeArea(child: SizedBox.expand(child: player)),
      ),
      transitionsBuilder: (_, animation, __, child) {
        return FadeTransition(opacity: animation, child: child);
      },
    ),
  );
}

// ── VideoCard ──────────────────────────────────────────────────────────────────

/// A reusable video thumbnail card that:
///   • Shows a YouTube thumbnail when the URL is a YouTube link.
///   • Shows a generic play-button placeholder for non-YouTube URLs.
///   • On tap opens the matching modal player.
///
/// Constructors:
///   [VideoCard.box]    - standard box, for use in Column / ListView etc.
///   [VideoCard.sliver] - wrapped in SliverToBoxAdapter.
///
/// The [videoUrl] parameter accepts both YouTube and regular video URLs.
/// The legacy [youtubeVideoUrl] alias is kept for backward compatibility.
class VideoCard extends StatelessWidget {
  final String? videoUrl;
  final bool _isSliver;

  /// Standard Box version - use inside Column, ListView, Stack, etc.
  const VideoCard.box({super.key, String? videoUrl, String? youtubeVideoUrl})
      : videoUrl = videoUrl ?? youtubeVideoUrl,
        _isSliver = false;

  /// Sliver version - use inside CustomScrollView's slivers list.
  const VideoCard.sliver({super.key, String? videoUrl, String? youtubeVideoUrl})
      : videoUrl = videoUrl ?? youtubeVideoUrl,
        _isSliver = true;

  @override
  Widget build(BuildContext context) {
    // ── Empty state ────────────────────────────────────────────────────────
    if (videoUrl == null || videoUrl!.isEmpty) {
      return _isSliver
          ? const SliverToBoxAdapter(child: SizedBox.shrink())
          : const SizedBox.shrink();
    }

    final url = videoUrl!;
    final isYt = isYoutubeUrl(url);
    final thumbUrl = isYt ? _ytThumbnail(url) : null;

    // ── Card content ───────────────────────────────────────────────────────
    Widget content = Padding(
      padding: EdgeInsets.fromLTRB(16.sp, 0, 16.sp, 0),
      child: GestureDetector(
        onTap: () => openVideoModal(context, url),
        child: Stack(
          alignment: Alignment.center,
          children: [
            // Thumbnail
            ClipRRect(
              borderRadius: BorderRadius.circular(8.r),
              child: isYt && thumbUrl != null
                  ? CustomImage(
                      thumbUrl,
                      width: MediaQuery.sizeOf(context).width,
                      height: MediaQuery.sizeOf(context).height * .22,
                      fit: BoxFit.cover,
                    )
                  : Container(
                      width: MediaQuery.sizeOf(context).width,
                      height: MediaQuery.sizeOf(context).height * .22,
                      color: Colors.black87,
                      child: Icon(
                        Icons.video_file_rounded,
                        color: Colors.white24,
                        size: 48.sp,
                      ),
                    ),
            ),
            // Dim overlay
            ClipRRect(
              borderRadius: BorderRadius.circular(8.r),
              child: Container(
                width: MediaQuery.sizeOf(context).width,
                height: MediaQuery.sizeOf(context).height * .22,
                color: Colors.black.withValues(alpha: 0.25),
              ),
            ),
            // Play button
            Container(
              width: 56.sp,
              height: 56.sp,
              decoration: const BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.black54,
              ),
              child: Icon(
                Icons.play_arrow_rounded,
                color: Colors.white,
                size: 32.sp,
              ),
            ),
          ],
        ),
      ),
    );

    // ── Sliver wrap ────────────────────────────────────────────────────────
    if (_isSliver) {
      return SliverToBoxAdapter(
        child: Padding(
          padding: EdgeInsets.symmetric(vertical: 16.h),
          child: content,
        ),
      );
    }

    return content;
  }
}