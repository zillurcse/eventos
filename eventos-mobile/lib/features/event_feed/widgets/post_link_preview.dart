import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../models/event_feed_model.dart';
import '../../../utils/extension/size_ext.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_image.dart';
import '../../../widgets/shimmer_box.dart';

class PostLinkPreview extends StatefulWidget {
  final String url;
  final FeedPostModel post;

  const PostLinkPreview({super.key, required this.url, required this.post});

  @override
  State<PostLinkPreview> createState() => _PostLinkPreviewState();
}

class _PostLinkPreviewState extends State<PostLinkPreview> {
  // OG data
  String? _ogImage;
  String? _ogTitle;
  String? _ogDescription;
  String? _ogSiteName;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _fetchOgData();
  }

  // ── OG metadata fetcher ────────────────────────────────────────────────────

  Future<void> _fetchOgData() async {
    try {
      final response = await Dio().get<String>(
        widget.url,
        options: Options(
          headers: {
            'User-Agent':
                'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
          },
          responseType: ResponseType.plain,
          sendTimeout: const Duration(seconds: 8),
          receiveTimeout: const Duration(seconds: 8),
        ),
      );

      final html = response.data ?? '';
      if (mounted) {
        setState(() {
          _ogImage = _parseMeta(html, 'og:image') ??
              _parseMeta(html, 'twitter:image');
          _ogTitle = _parseMeta(html, 'og:title') ??
              _parseMeta(html, 'twitter:title') ??
              _parseTitle(html);
          _ogDescription = _parseMeta(html, 'og:description') ??
              _parseMeta(html, 'twitter:description');
          _ogSiteName = _parseMeta(html, 'og:site_name');
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  /// Parses `content` value of a <meta property/name="[key]"> tag.
  String? _parseMeta(String html, String key) {
    // property="key" content="value"
    final r1 = RegExp(
      '<meta[^>]*(?:property|name)=["\']${RegExp.escape(key)}["\'][^>]*content=["\']([^"\']+)["\']',
      caseSensitive: false,
    ).firstMatch(html);
    if (r1 != null) return _decode(r1.group(1));

    // content="value" property="key"
    final r2 = RegExp(
      '<meta[^>]*content=["\']([^"\']+)["\'][^>]*(?:property|name)=["\']${RegExp.escape(key)}["\']',
      caseSensitive: false,
    ).firstMatch(html);
    if (r2 != null) return _decode(r2.group(1));

    return null;
  }

  String? _parseTitle(String html) {
    final m = RegExp(r'<title[^>]*>([^<]+)</title>', caseSensitive: false)
        .firstMatch(html);
    return m != null ? _decode(m.group(1)) : null;
  }

  String _decode(String? raw) {
    return (raw ?? '')
        .replaceAll('&amp;', '&')
        .replaceAll('&lt;', '<')
        .replaceAll('&gt;', '>')
        .replaceAll('&quot;', '"')
        .replaceAll('&#39;', "'")
        .trim();
  }

  // ── Helpers ────────────────────────────────────────────────────────────────

  String get _displayTitle =>
      _ogTitle?.isNotEmpty == true ? _ogTitle! : _domainTitle;

  String get _displayDomain {
    try {
      return _ogSiteName?.isNotEmpty == true
          ? _ogSiteName!
          : Uri.parse(widget.url).host;
    } catch (_) {
      return widget.url;
    }
  }

  String get _domainTitle {
    try {
      final host = Uri.parse(widget.url).host.replaceFirst('www.', '');
      return host
          .split('.')
          .first
          .split('-')
          .map((w) => w.isEmpty ? '' : '${w[0].toUpperCase()}${w.substring(1)}')
          .join(' ');
    } catch (_) {
      return widget.url;
    }
  }

  String? get _imageUrl =>
      _ogImage?.isNotEmpty == true
          ? _ogImage
          : (widget.post.attach?.isNotEmpty == true ? widget.post.attach : null);

  Future<void> _open() async {
    final uri = Uri.tryParse(widget.url);
    if (uri != null && await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  // ── Build ──────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: _open,
      child: Container(
        width: context.width,
        clipBehavior: Clip.hardEdge,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(12.r),
          border: Border.all(color: context.strokeLight),
          color: context.backgroundColor,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // ── Image area ──────────────────────────────────────────────────
            SizedBox(
              width: context.width,
              height: context.height * .18,
              child: _loading
                  ? ShimmerBox(
                      width: context.width,
                      height: context.height * .18,
                      topRadius: 12.r,
                    )
                  : ClipRRect(
                      borderRadius: BorderRadius.only(
                        topLeft: Radius.circular(12.r),
                        topRight: Radius.circular(12.r),
                      ),
                      child: _imageUrl != null
                          ? CustomImage(
                              _imageUrl!,
                              width: context.width,
                              height: context.height * .18,
                              radius: 0,
                              fit: BoxFit.cover,
                            )
                          : Container(
                              color: context.primaryFocused,
                              child: Center(
                                child: Icon(
                                  Icons.link_rounded,
                                  size: 48.sp,
                                  color: context.primaryTheme
                                      .withValues(alpha: 0.4),
                                ),
                              ),
                            ),
                    ),
            ),

            // ── Meta row ────────────────────────────────────────────────────
            Padding(
              padding:
                  EdgeInsets.symmetric(horizontal: 12.w, vertical: 10.h),
              child: _loading
                  ? Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        ShimmerBox(
                            width: context.width * .55, height: 13.h),
                        SizedBox(height: 6.h),
                        ShimmerBox(
                            width: context.width * .38, height: 10.h),
                      ],
                    )
                  : Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          _displayTitle,
                          style: context.titleRegular?.copyWith(
                            color: context.heading,
                            fontWeight: FontWeight.w600,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                        SizedBox(height: 2.h),
                        if (_ogDescription?.isNotEmpty == true) ...[
                          Text(
                            _ogDescription!,
                            style: context.specialCaption2?.copyWith(
                              color: context.caption,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                          SizedBox(height: 2.h),
                        ],
                        Text(
                          _displayDomain,
                          style: context.specialCaption2?.copyWith(
                            color: context.ghost,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ],
                    ),
            ),
          ],
        ),
      ),
    );
  }
}
