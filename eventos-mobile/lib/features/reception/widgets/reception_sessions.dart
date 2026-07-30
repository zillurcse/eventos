import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../models/reception_models.dart';
import '../utils/reception_format.dart';
import 'reception_shared.dart';

class ReceptionSessionsSection extends StatelessWidget {
  const ReceptionSessionsSection({
    super.key,
    required this.title,
    required this.sessions,
    this.live = false,
    this.viewAllLabel,
    this.onViewAll,
  });

  final String title;
  final List<ReceptionSession> sessions;
  final bool live;
  final String? viewAllLabel;
  final VoidCallback? onViewAll;

  @override
  Widget build(BuildContext context) {
    if (sessions.isEmpty) return const SizedBox.shrink();

    final heading = live
        ? '$title (${sessions.length})'
        : title;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SectionHeader(
          title: heading,
          trailing: live
              ? Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFFE5E5),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: const Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.circle, size: 8, color: Color(0xFFE53935)),
                      SizedBox(width: 6),
                      Text(
                        'Live',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                          color: Color(0xFFE53935),
                        ),
                      ),
                    ],
                  ),
                )
              : null,
        ),
        if (live && sessions.first.imageUrl != null)
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 0, 20, 12),
            child: AspectRatio(
              aspectRatio: 16 / 9,
              child: Stack(
                fit: StackFit.expand,
                children: [
                  NetworkOrPlaceholder(
                    url: sessions.first.imageUrl,
                    borderRadius: BorderRadius.circular(16),
                  ),
                  Center(
                    child: Container(
                      width: 56,
                      height: 56,
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.92),
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(
                        Icons.play_arrow_rounded,
                        size: 36,
                        color: AppColors.brandPurple,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        SizedBox(
          height: 210,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 20),
            itemCount: sessions.length,
            separatorBuilder: (context, index) => const SizedBox(width: 12),
            itemBuilder: (context, index) {
              return ReceptionSessionCard(
                session: sessions[index],
                primaryAction: live,
              );
            },
          ),
        ),
        if (viewAllLabel != null)
          ViewAllLink(label: viewAllLabel!, onTap: onViewAll),
      ],
    );
  }
}

class ReceptionSessionCard extends StatelessWidget {
  const ReceptionSessionCard({
    super.key,
    required this.session,
    this.primaryAction = false,
  });

  final ReceptionSession session;
  final bool primaryAction;

  @override
  Widget build(BuildContext context) {
    final phase = sessionPhase(session);

    return Container(
      width: 280,
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
          Text(
            formatTimeRange(session.startsAt, session.endsAt),
            style: const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: AppColors.brandPurple,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            session.title,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w700,
              color: AppColors.headline,
              height: 1.3,
            ),
          ),
          if ((session.sessionPlace ?? '').isNotEmpty) ...[
            const SizedBox(height: 8),
            Row(
              children: [
                const Icon(Icons.location_on_outlined, size: 14, color: AppColors.body),
                const SizedBox(width: 4),
                Expanded(
                  child: Text(
                    session.sessionPlace!,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontSize: 12, color: AppColors.body),
                  ),
                ),
              ],
            ),
          ],
          const Spacer(),
          Row(
            children: [
              AvatarStack(
                urls: session.speakers.map((s) => s.imageUrl).toList(),
              ),
              const Spacer(),
              if (primaryAction || phase == SessionPhase.live)
                FilledButton(
                  onPressed: () {},
                  style: FilledButton.styleFrom(
                    minimumSize: const Size(0, 36),
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                  child: const Text('Join Now'),
                )
              else
                OutlinedButton(
                  onPressed: () {},
                  style: OutlinedButton.styleFrom(
                    foregroundColor: AppColors.brandPurple,
                    side: const BorderSide(color: AppColors.brandPurple),
                    minimumSize: const Size(0, 36),
                    padding: const EdgeInsets.symmetric(horizontal: 14),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                  child: const Text('Enter Live Room'),
                ),
            ],
          ),
        ],
      ),
    );
  }
}
