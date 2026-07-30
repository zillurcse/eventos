import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import 'models/reception_models.dart';

class ReceptionState {
  const ReceptionState({
    this.data,
    this.isLoading = false,
    this.error,
  });

  final ReceptionPayload? data;
  final bool isLoading;
  final String? error;

  ReceptionState copyWith({
    ReceptionPayload? data,
    bool? isLoading,
    String? error,
    bool clearError = false,
  }) {
    return ReceptionState(
      data: data ?? this.data,
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
    );
  }
}

class ReceptionNotifier extends StateNotifier<ReceptionState> {
  ReceptionNotifier(this._ref) : super(const ReceptionState());

  final Ref _ref;

  ApiClient get _api => _ref.read(apiClientProvider);

  Future<void> load({bool force = false}) async {
    if (state.isLoading) return;
    if (state.data != null && !force) return;

    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final response = await _api.get<Map<String, dynamic>>('/public/reception');
      final raw = response.data?['data'] ?? response.data;
      if (raw is! Map<String, dynamic>) {
        throw Exception('Invalid reception response');
      }
      state = ReceptionState(
        data: ReceptionPayload.fromJson(raw),
        isLoading: false,
      );
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: e.toString().replaceFirst('Exception: ', ''),
      );
    }
  }
}

final receptionProvider =
    StateNotifierProvider<ReceptionNotifier, ReceptionState>(
  (ref) => ReceptionNotifier(ref),
);
