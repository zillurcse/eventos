import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import 'reception_shared.dart';

class ReceptionTopBar extends StatelessWidget {
  const ReceptionTopBar({
    super.key,
    required this.eventName,
    this.logoUrl,
    this.onMenu,
    this.onProfile,
  });

  final String eventName;
  final String? logoUrl;
  final VoidCallback? onMenu;
  final VoidCallback? onProfile;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(8, 4, 8, 0),
      child: Row(
        children: [
          IconButton(
            onPressed: onMenu,
            icon: const Icon(Icons.menu_rounded, color: AppColors.headline),
          ),
          Expanded(
            child: Center(
              child: logoUrl != null && logoUrl!.isNotEmpty
                  ? SizedBox(
                      height: 28,
                      child: CachedNetworkImage(
                        imageUrl: logoUrl!,
                        fit: BoxFit.contain,
                        errorWidget: (context, url, error) => _Title(eventName),
                      ),
                    )
                  : _Title(eventName),
            ),
          ),
          IconButton(
            onPressed: onProfile,
            icon: const CircleAvatar(
              radius: 16,
              backgroundColor: AppColors.otpButtonBg,
              child: Icon(Icons.person, size: 18, color: AppColors.brandPurple),
            ),
          ),
        ],
      ),
    );
  }
}

class _Title extends StatelessWidget {
  const _Title(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    return Text(
      text.toUpperCase(),
      maxLines: 1,
      overflow: TextOverflow.ellipsis,
      style: const TextStyle(
        fontSize: 16,
        fontWeight: FontWeight.w800,
        letterSpacing: 0.6,
        color: AppColors.headline,
      ),
    );
  }
}

class ReceptionIconNav extends StatelessWidget {
  const ReceptionIconNav({
    super.key,
    required this.selectedIndex,
    required this.onSelected,
  });

  final int selectedIndex;
  final ValueChanged<int> onSelected;

  static const items = [
    (Icons.home_rounded, 'Home'),
    (Icons.work_outline_rounded, 'Sessions'),
    (Icons.person_outline_rounded, 'Speakers'),
    (Icons.emoji_events_outlined, 'Awards'),
    (Icons.info_outline_rounded, 'Info'),
    (Icons.chat_bubble_outline_rounded, 'Chat'),
  ];

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 56,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        itemCount: items.length,
        separatorBuilder: (context, index) => const SizedBox(width: 8),
        itemBuilder: (context, index) {
          final item = items[index];
          final selected = index == selectedIndex;
          return InkWell(
            onTap: () => onSelected(index),
            borderRadius: BorderRadius.circular(14),
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 180),
              width: 48,
              height: 48,
              decoration: BoxDecoration(
                color: selected ? AppColors.brandPurple : Colors.white,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(
                  color: selected ? AppColors.brandPurple : AppColors.inputBorder,
                ),
                boxShadow: selected
                    ? [
                        BoxShadow(
                          color: AppColors.brandPurple.withValues(alpha: 0.25),
                          blurRadius: 10,
                          offset: const Offset(0, 4),
                        ),
                      ]
                    : null,
              ),
              child: Icon(
                item.$1,
                color: selected ? Colors.white : AppColors.label,
                size: 22,
              ),
            ),
          );
        },
      ),
    );
  }
}

class ReceptionGreeting extends StatelessWidget {
  const ReceptionGreeting({
    super.key,
    required this.name,
    this.role = 'Event Attendee',
    this.isVip = false,
  });

  final String name;
  final String role;
  final bool isVip;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Hello $name 👋',
            style: const TextStyle(
              fontSize: 26,
              fontWeight: FontWeight.w800,
              color: AppColors.headline,
              height: 1.15,
            ),
          ),
          const SizedBox(height: 6),
          Row(
            children: [
              Text(
                role,
                style: const TextStyle(
                  fontSize: 14,
                  color: AppColors.body,
                  fontWeight: FontWeight.w500,
                ),
              ),
              if (isVip) ...[
                const SizedBox(width: 8),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFFF3D6),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: const Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.workspace_premium, size: 14, color: Color(0xFFC9A227)),
                      SizedBox(width: 4),
                      Text(
                        'VIP',
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                          color: Color(0xFF9A7B1A),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ],
          ),
        ],
      ),
    );
  }
}

class ReceptionHeroBanner extends StatelessWidget {
  const ReceptionHeroBanner({super.key, required this.urls});

  final List<String> urls;

  @override
  Widget build(BuildContext context) {
    if (urls.isEmpty) {
      return Padding(
        padding: const EdgeInsets.symmetric(horizontal: 20),
        child: AspectRatio(
          aspectRatio: 16 / 8,
          child: NetworkOrPlaceholder(
            url: null,
            borderRadius: BorderRadius.circular(16),
            placeholderIcon: Icons.event,
            backgroundColor: AppColors.otpButtonBg,
          ),
        ),
      );
    }

    return SizedBox(
      height: 160,
      child: PageView.builder(
        controller: PageController(viewportFraction: 0.92),
        itemCount: urls.length,
        itemBuilder: (context, index) {
          return Padding(
            padding: const EdgeInsets.symmetric(horizontal: 6),
            child: NetworkOrPlaceholder(
              url: urls[index],
              borderRadius: BorderRadius.circular(16),
            ),
          );
        },
      ),
    );
  }
}
