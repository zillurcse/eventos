import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/storage/secure_storage.dart';

class OnboardingState {
  const OnboardingState({
    this.isLoading = true,
    this.completed = false,
  });

  final bool isLoading;
  final bool completed;

  OnboardingState copyWith({bool? isLoading, bool? completed}) {
    return OnboardingState(
      isLoading: isLoading ?? this.isLoading,
      completed: completed ?? this.completed,
    );
  }
}

class OnboardingNotifier extends StateNotifier<OnboardingState> {
  OnboardingNotifier(this._ref) : super(const OnboardingState()) {
    _restore();
  }

  final Ref _ref;

  SecureStorageService get _storage => _ref.read(secureStorageProvider);

  Future<void> _restore() async {
    final done = await _storage.readOnboardingDone();
    state = OnboardingState(isLoading: false, completed: done);
  }

  Future<void> complete() async {
    await _storage.writeOnboardingDone();
    state = state.copyWith(completed: true, isLoading: false);
  }
}

final onboardingProvider =
    StateNotifierProvider<OnboardingNotifier, OnboardingState>(
  (ref) => OnboardingNotifier(ref),
);
