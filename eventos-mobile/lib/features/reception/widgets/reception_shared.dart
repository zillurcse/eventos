import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';

class NetworkOrPlaceholder extends StatelessWidget {
  const NetworkOrPlaceholder({
    super.key,
    this.url,
    this.borderRadius,
    this.fit = BoxFit.cover,
    this.placeholderIcon = Icons.image_outlined,
    this.backgroundColor,
  });

  final String? url;
  final BorderRadius? borderRadius;
  final BoxFit fit;
  final IconData placeholderIcon;
  final Color? backgroundColor;

  @override
  Widget build(BuildContext context) {
    final radius = borderRadius ?? BorderRadius.zero;
    final bg = backgroundColor ?? AppColors.divider;

    if (url == null || url!.isEmpty) {
      return DecoratedBox(
        decoration: BoxDecoration(color: bg, borderRadius: radius),
        child: Center(
          child: Icon(placeholderIcon, color: AppColors.placeholder, size: 28),
        ),
      );
    }

    return ClipRRect(
      borderRadius: radius,
      child: CachedNetworkImage(
        imageUrl: url!,
        fit: fit,
        width: double.infinity,
        height: double.infinity,
        placeholder: (context, url) => ColoredBox(color: bg),
        errorWidget: (context, url, error) => ColoredBox(
          color: bg,
          child: Icon(placeholderIcon, color: AppColors.placeholder, size: 28),
        ),
      ),
    );
  }
}

class SectionHeader extends StatelessWidget {
  const SectionHeader({
    super.key,
    required this.title,
    this.trailing,
  });

  final String title;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 12),
      child: Row(
        children: [
          Expanded(
            child: Text(
              title,
              style: const TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w700,
                color: AppColors.headline,
              ),
            ),
          ),
          ?trailing,
        ],
      ),
    );
  }
}

class ViewAllLink extends StatelessWidget {
  const ViewAllLink({super.key, required this.label, this.onTap});

  final String label;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 4, 20, 8),
      child: Align(
        alignment: Alignment.centerLeft,
        child: TextButton(
          onPressed: onTap ?? () {},
          style: TextButton.styleFrom(
            foregroundColor: AppColors.brandPurple,
            padding: EdgeInsets.zero,
            minimumSize: Size.zero,
            tapTargetSize: MaterialTapTargetSize.shrinkWrap,
          ),
          child: Text(
            label,
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w600,
              decoration: TextDecoration.underline,
              decorationColor: AppColors.brandPurple,
            ),
          ),
        ),
      ),
    );
  }
}

class AvatarStack extends StatelessWidget {
  const AvatarStack({super.key, required this.urls, this.size = 28});

  final List<String?> urls;
  final double size;

  @override
  Widget build(BuildContext context) {
    final items = urls.take(4).toList();
    if (items.isEmpty) return const SizedBox.shrink();

    return SizedBox(
      height: size,
      width: size + (items.length - 1) * (size * 0.62),
      child: Stack(
        children: [
          for (var i = 0; i < items.length; i++)
            Positioned(
              left: i * size * 0.62,
              child: Container(
                width: size,
                height: size,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  border: Border.all(color: Colors.white, width: 2),
                  color: AppColors.divider,
                ),
                clipBehavior: Clip.antiAlias,
                child: NetworkOrPlaceholder(
                  url: items[i],
                  placeholderIcon: Icons.person,
                  borderRadius: BorderRadius.circular(size),
                ),
              ),
            ),
        ],
      ),
    );
  }
}
