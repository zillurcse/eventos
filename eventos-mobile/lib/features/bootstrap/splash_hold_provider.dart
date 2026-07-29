import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Keeps the branded splash visible long enough to read, even when
/// auth bootstrap finishes immediately.
final splashHoldProvider =
    StateNotifierProvider<SplashHoldNotifier, bool>((ref) {
  return SplashHoldNotifier();
});

class SplashHoldNotifier extends StateNotifier<bool> {
  SplashHoldNotifier() : super(false) {
    Future<void>.delayed(const Duration(milliseconds: 1800), () {
      if (mounted) state = true;
    });
  }
}
