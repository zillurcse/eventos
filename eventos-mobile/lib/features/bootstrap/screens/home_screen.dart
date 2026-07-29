import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../auth/auth_provider.dart';
import '../../bootstrap/event_provider.dart';

class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authProvider);
    final event = ref.watch(eventProvider);

    return Scaffold(
      appBar: AppBar(
        title: Text(event.eventName ?? 'EventOS'),
        actions: [
          IconButton(
            tooltip: 'Sign out',
            onPressed: () async {
              await ref.read(authProvider.notifier).logout();
              if (context.mounted) context.go('/login');
            },
            icon: const Icon(Icons.logout),
          ),
        ],
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Welcome${auth.user?.name.isNotEmpty == true ? ', ${auth.user!.name}' : ''}!',
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: 8),
              Text(
                'Mobile shell is ready. Agenda, chat, and networking screens come next.',
                style: Theme.of(context).textTheme.bodyLarge,
              ),
              const SizedBox(height: 24),
              Card(
                child: ListTile(
                  leading: const Icon(Icons.event),
                  title: const Text('Event subdomain'),
                  subtitle: Text(event.subdomain ?? '—'),
                ),
              ),
              Card(
                child: ListTile(
                  leading: const Icon(Icons.person),
                  title: const Text('Signed in as'),
                  subtitle: Text(auth.user?.email ?? '—'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
