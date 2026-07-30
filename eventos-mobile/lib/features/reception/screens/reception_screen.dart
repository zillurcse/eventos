import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/app_theme.dart';
import '../../auth/auth_provider.dart';
import '../../bootstrap/event_provider.dart';
import '../models/reception_models.dart';
import '../reception_provider.dart';
import '../utils/reception_format.dart';
import '../widgets/reception_about.dart';
import '../widgets/reception_extras.dart';
import '../widgets/reception_header.dart';
import '../widgets/reception_sessions.dart';
import '../widgets/reception_speakers_partners.dart';

class ReceptionScreen extends ConsumerStatefulWidget {
  const ReceptionScreen({super.key});

  @override
  ConsumerState<ReceptionScreen> createState() => _ReceptionScreenState();
}

class _ReceptionScreenState extends ConsumerState<ReceptionScreen> {
  int _navIndex = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(receptionProvider.notifier).load();
    });
  }

  Future<void> _refresh() => ref.read(receptionProvider.notifier).load(force: true);

  void _comingSoon(String label) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('$label is coming soon'),
        behavior: SnackBarBehavior.floating,
      ),
    );
  }

  Future<void> _signOut() async {
    await ref.read(authProvider.notifier).logout();
    if (mounted) context.go('/login');
  }

  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(authProvider);
    final event = ref.watch(eventProvider);
    final reception = ref.watch(receptionProvider);
    final data = reception.data;
    final about = data?.about;
    final displayName = about?.name.isNotEmpty == true
        ? about!.name
        : (event.eventName ?? 'EventOS');
    final userName = firstName(auth.user?.name);

    final now = DateTime.now();
    final liveSessions = data?.sessions
            .where((s) => sessionPhase(s, now: now) == SessionPhase.live)
            .toList() ??
        const [];
    final featuredSessions = data?.sessions
            .where((s) => s.isFeatured && sessionPhase(s, now: now) != SessionPhase.live)
            .toList() ??
        const [];
    final upcomingFallback = featuredSessions.isNotEmpty
        ? featuredSessions
        : (data?.sessions
                .where((s) => sessionPhase(s, now: now) == SessionPhase.upcoming)
                .toList() ??
            const []);

    return Scaffold(
      backgroundColor: AppColors.screenBg,
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            ReceptionTopBar(
              eventName: displayName,
              logoUrl: about?.logoUrl ?? event.logoUrl,
              onMenu: () => _openMenu(context),
              onProfile: () => _openMenu(context),
            ),
            ReceptionIconNav(
              selectedIndex: _navIndex,
              onSelected: (index) {
                setState(() => _navIndex = index);
                if (index != 0) {
                  _comingSoon(ReceptionIconNav.items[index].$2);
                }
              },
            ),
            Expanded(
              child: RefreshIndicator(
                color: AppColors.brandPurple,
                onRefresh: _refresh,
                child: _buildBody(
                  reception: reception,
                  userName: userName,
                  liveSessions: liveSessions,
                  featuredSessions: upcomingFallback,
                ),
              ),
            ),
            ReceptionBottomCta(
              onPressed: () => _comingSoon('Explore'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildBody({
    required ReceptionState reception,
    required String userName,
    required List<ReceptionSession> liveSessions,
    required List<ReceptionSession> featuredSessions,
  }) {
    if (reception.isLoading && reception.data == null) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: const [
          SizedBox(height: 160),
          Center(child: CircularProgressIndicator(color: AppColors.brandPurple)),
        ],
      );
    }

    if (reception.error != null && reception.data == null) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(24),
        children: [
          const SizedBox(height: 80),
          const Icon(Icons.wifi_off_rounded, size: 48, color: AppColors.placeholder),
          const SizedBox(height: 16),
          Text(
            reception.error!,
            textAlign: TextAlign.center,
            style: const TextStyle(color: AppColors.body),
          ),
          const SizedBox(height: 16),
          Center(
            child: FilledButton(
              onPressed: _refresh,
              child: const Text('Try again'),
            ),
          ),
        ],
      );
    }

    final data = reception.data!;
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.only(bottom: 12),
      children: [
        ReceptionGreeting(name: userName),
        const SizedBox(height: 4),
        ReceptionHeroBanner(
          urls: data.banners.isNotEmpty
              ? data.banners
              : [
                  if ((data.about.coverUrl ?? '').isNotEmpty) data.about.coverUrl!,
                ],
        ),
        ReceptionAboutSection(about: data.about),
        const SizedBox(height: 8),
        ReceptionSessionsSection(
          title: 'Ongoing Session',
          sessions: liveSessions,
          live: true,
          viewAllLabel: 'View all sessions',
          onViewAll: () => _comingSoon('Sessions'),
        ),
        ReceptionSessionsSection(
          title: 'Featured Sessions',
          sessions: featuredSessions,
          viewAllLabel: 'View all sessions',
          onViewAll: () => _comingSoon('Sessions'),
        ),
        ReceptionSpeakersSection(
          speakers: data.speakers,
          onViewAll: () => _comingSoon('Speakers'),
        ),
        ReceptionPartnersSection(
          title: 'Featured Exhibitors',
          partners: data.exhibitors,
          viewAllLabel: 'View all exhibitors',
          onViewAll: () => _comingSoon('Exhibitors'),
        ),
        ReceptionPartnersSection(
          title: 'Featured Sponsors',
          partners: data.sponsors,
          viewAllLabel: 'View all sponsors',
          onViewAll: () => _comingSoon('Sponsors'),
        ),
        const ReceptionLeaderboardPreview(),
        ReceptionAdsSection(ads: [...data.ads.strip, ...data.ads.sidebar]),
        const SizedBox(height: 8),
      ],
    );
  }

  void _openMenu(BuildContext context) {
    showModalBottomSheet<void>(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(8, 12, 8, 8),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                ListTile(
                  leading: const Icon(Icons.refresh),
                  title: const Text('Refresh reception'),
                  onTap: () {
                    Navigator.pop(context);
                    _refresh();
                  },
                ),
                ListTile(
                  leading: const Icon(Icons.logout, color: Colors.redAccent),
                  title: const Text('Sign out'),
                  onTap: () {
                    Navigator.pop(context);
                    _signOut();
                  },
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}
