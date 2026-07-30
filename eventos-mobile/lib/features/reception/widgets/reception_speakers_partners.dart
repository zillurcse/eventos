import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../models/reception_models.dart';
import 'reception_shared.dart';

class ReceptionSpeakersSection extends StatelessWidget {
  const ReceptionSpeakersSection({
    super.key,
    required this.speakers,
    this.onViewAll,
  });

  final List<ReceptionSpeaker> speakers;
  final VoidCallback? onViewAll;

  @override
  Widget build(BuildContext context) {
    if (speakers.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const SectionHeader(title: 'Featured Speakers'),
        SizedBox(
          height: 250,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 20),
            itemCount: speakers.length,
            separatorBuilder: (context, index) => const SizedBox(width: 12),
            itemBuilder: (context, index) {
              final speaker = speakers[index];
              return Container(
                width: 160,
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
                clipBehavior: Clip.antiAlias,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: NetworkOrPlaceholder(
                        url: speaker.imageUrl,
                        placeholderIcon: Icons.person,
                        backgroundColor: AppColors.otpButtonBg,
                      ),
                    ),
                    Padding(
                      padding: const EdgeInsets.fromLTRB(12, 10, 12, 12),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            speaker.name ?? 'Speaker',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w700,
                              color: AppColors.headline,
                            ),
                          ),
                          if (speaker.subtitle.isNotEmpty) ...[
                            const SizedBox(height: 4),
                            Text(
                              speaker.subtitle,
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontSize: 12,
                                color: AppColors.body,
                                height: 1.3,
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ],
                ),
              );
            },
          ),
        ),
        ViewAllLink(label: 'View all speakers', onTap: onViewAll),
      ],
    );
  }
}

class ReceptionPartnersSection extends StatelessWidget {
  const ReceptionPartnersSection({
    super.key,
    required this.title,
    required this.partners,
    required this.viewAllLabel,
    this.onViewAll,
  });

  final String title;
  final List<ReceptionPartner> partners;
  final String viewAllLabel;
  final VoidCallback? onViewAll;

  @override
  Widget build(BuildContext context) {
    if (partners.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SectionHeader(title: title),
        SizedBox(
          height: 140,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 20),
            itemCount: partners.length,
            separatorBuilder: (context, index) => const SizedBox(width: 12),
            itemBuilder: (context, index) {
              final partner = partners[index];
              return Container(
                width: 180,
                padding: const EdgeInsets.all(14),
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
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: NetworkOrPlaceholder(
                        url: partner.logoUrl,
                        borderRadius: BorderRadius.circular(10),
                        fit: BoxFit.contain,
                        placeholderIcon: Icons.apartment,
                        backgroundColor: AppColors.screenBg,
                      ),
                    ),
                    const SizedBox(height: 10),
                    Text(
                      partner.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                        color: AppColors.headline,
                      ),
                    ),
                    if ((partner.booth ?? '').isNotEmpty)
                      Text(
                        'Booth ${partner.booth}',
                        style: const TextStyle(fontSize: 11, color: AppColors.body),
                      ),
                  ],
                ),
              );
            },
          ),
        ),
        ViewAllLink(label: viewAllLabel, onTap: onViewAll),
      ],
    );
  }
}
