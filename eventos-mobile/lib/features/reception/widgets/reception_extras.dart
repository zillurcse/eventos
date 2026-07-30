import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../models/reception_models.dart';
import 'reception_shared.dart';

class ReceptionAdsSection extends StatelessWidget {
  const ReceptionAdsSection({super.key, required this.ads});

  final List<ReceptionAd> ads;

  @override
  Widget build(BuildContext context) {
    final withImage = ads.where((a) => a.imageUrl != null).toList();
    if (withImage.isEmpty) return const SizedBox.shrink();

    return Column(
      children: [
        for (final ad in withImage.take(2))
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 8),
            child: AspectRatio(
              aspectRatio: 16 / 7,
              child: Stack(
                fit: StackFit.expand,
                children: [
                  NetworkOrPlaceholder(
                    url: ad.imageUrl,
                    borderRadius: BorderRadius.circular(16),
                  ),
                  Center(
                    child: Container(
                      width: 48,
                      height: 48,
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.9),
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(
                        Icons.play_arrow_rounded,
                        color: AppColors.brandPurple,
                        size: 30,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
      ],
    );
  }
}

class ReceptionLeaderboardPreview extends StatelessWidget {
  const ReceptionLeaderboardPreview({super.key});

  static const _entries = [
    (1, 'Amina Khan', 1280),
    (2, 'Leo Park', 1140),
    (3, 'Sofia Reyes', 980),
  ];

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const SectionHeader(title: 'Leaderboard'),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20),
          child: Container(
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.05),
                  blurRadius: 12,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            child: Column(
              children: [
                for (final entry in _entries)
                  ListTile(
                    leading: CircleAvatar(
                      backgroundColor: AppColors.otpButtonBg,
                      child: Text(
                        '${entry.$1}',
                        style: const TextStyle(
                          fontWeight: FontWeight.w700,
                          color: AppColors.brandPurple,
                        ),
                      ),
                    ),
                    title: Text(
                      entry.$2,
                      style: const TextStyle(
                        fontWeight: FontWeight.w600,
                        color: AppColors.headline,
                      ),
                    ),
                    trailing: Text(
                      '${entry.$3} pts',
                      style: const TextStyle(
                        fontWeight: FontWeight.w600,
                        color: AppColors.label,
                      ),
                    ),
                  ),
                Container(
                  margin: const EdgeInsets.fromLTRB(12, 0, 12, 12),
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                  decoration: BoxDecoration(
                    color: AppColors.brandPurple,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Row(
                    children: [
                      CircleAvatar(
                        radius: 16,
                        backgroundColor: Colors.white24,
                        child: Text(
                          'You',
                          style: TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.w700,
                            color: Colors.white,
                          ),
                        ),
                      ),
                      SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          'Your rank · #24',
                          style: TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                      Text(
                        '320 pts',
                        style: TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
        const ViewAllLink(label: 'View entire leaderboard'),
      ],
    );
  }
}

class ReceptionBottomCta extends StatelessWidget {
  const ReceptionBottomCta({super.key, this.onPressed});

  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 12),
        child: SizedBox(
          width: double.infinity,
          height: 52,
          child: FilledButton(
            onPressed: onPressed ?? () {},
            style: FilledButton.styleFrom(
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(14),
              ),
            ),
            child: const Text(
              'Explore + Contribute',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
            ),
          ),
        ),
      ),
    );
  }
}
