import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';

/// Inline ad banner injected into the speaker list between items.
class AdBannerItem extends StatelessWidget {
  final String imageUrl;
  const AdBannerItem({super.key, required this.imageUrl});

  @override
  Widget build(BuildContext context) {
    final dpr = MediaQuery.devicePixelRatioOf(context);
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(8),
        child: CachedNetworkImage(
          imageUrl: imageUrl,
          fit: BoxFit.cover,
          height: 80,
          width: double.infinity,
          memCacheHeight: (80 * dpr).round(),
          errorWidget: (_, __, ___) => const SizedBox.shrink(),
        ),
      ),
    );
  }
}
