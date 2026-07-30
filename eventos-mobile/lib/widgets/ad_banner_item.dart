import 'package:flutter/material.dart';

/// Inline ad banner injected into the speaker list between items.
class AdBannerItem extends StatelessWidget {
  final String imageUrl;
  const AdBannerItem({super.key, required this.imageUrl});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(8),
        child: Image.network(
          imageUrl,
          fit: BoxFit.cover,
          height: 80,
          width: double.infinity,
          errorBuilder: (context, error, stack) => const SizedBox.shrink(),
        ),
      ),
    );
  }
}
