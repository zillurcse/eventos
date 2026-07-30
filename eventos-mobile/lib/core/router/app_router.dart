import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/auth_provider.dart';
import '../../features/auth/screens/login_screen.dart';
import '../../features/auth/screens/signup_screen.dart';
import '../../features/bootstrap/event_provider.dart';
import '../../features/bootstrap/onboarding_provider.dart';
import '../../features/bootstrap/screens/event_picker_screen.dart';
import '../../features/bootstrap/screens/onboarding_screen.dart';
import '../../features/bootstrap/screens/splash_screen.dart';
import '../../features/bootstrap/splash_hold_provider.dart';
import '../../features/reception/screens/reception_screen.dart';

final appRouterProvider = Provider<GoRouter>((ref) {
  final auth = ref.watch(authProvider);
  final event = ref.watch(eventProvider);
  final onboarding = ref.watch(onboardingProvider);
  final splashReady = ref.watch(splashHoldProvider);

  return GoRouter(
    initialLocation: '/splash',
    refreshListenable: _RouterRefresh(ref),
    redirect: (context, state) {
      final path = state.matchedLocation;

      if (!splashReady || auth.isBootstrapping || onboarding.isLoading) {
        return path == '/splash' ? null : '/splash';
      }

      if (!onboarding.completed) {
        return path == '/onboarding' ? null : '/onboarding';
      }

      if (!event.hasEvent) {
        return path == '/event' ? null : '/event';
      }

      if (!auth.isAuthenticated) {
        if (path == '/login' || path == '/signup') return null;
        return '/login';
      }

      if (path == '/splash' ||
          path == '/onboarding' ||
          path == '/event' ||
          path == '/login' ||
          path == '/signup') {
        return '/home';
      }

      return null;
    },
    routes: [
      GoRoute(
        path: '/splash',
        builder: (context, state) => const SplashScreen(),
      ),
      GoRoute(
        path: '/onboarding',
        builder: (context, state) => const OnboardingScreen(),
      ),
      GoRoute(
        path: '/event',
        builder: (context, state) => const EventPickerScreen(),
      ),
      GoRoute(
        path: '/login',
        builder: (context, state) => const LoginScreen(),
      ),
      GoRoute(
        path: '/signup',
        builder: (context, state) {
          final email = state.extra is String ? state.extra as String : null;
          return SignUpScreen(initialEmail: email);
        },
      ),
      GoRoute(
        path: '/home',
        builder: (context, state) => const ReceptionScreen(),
      ),
    ],
  );
});

class _RouterRefresh extends ChangeNotifier {
  _RouterRefresh(this._ref) {
    _ref.listen(authProvider, (_, _) => notifyListeners());
    _ref.listen(eventProvider, (_, _) => notifyListeners());
    _ref.listen(onboardingProvider, (_, _) => notifyListeners());
    _ref.listen(splashHoldProvider, (_, _) => notifyListeners());
  }

  final Ref _ref;
}
