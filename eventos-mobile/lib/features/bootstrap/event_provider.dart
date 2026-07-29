import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../core/storage/secure_storage.dart';

class EventState {
  const EventState({
    this.subdomain,
    this.eventName,
    this.isLoading = false,
  });

  final String? subdomain;
  final String? eventName;
  final bool isLoading;

  bool get hasEvent => subdomain != null && subdomain!.isNotEmpty;

  EventState copyWith({
    String? subdomain,
    String? eventName,
    bool? isLoading,
  }) {
    return EventState(
      subdomain: subdomain ?? this.subdomain,
      eventName: eventName ?? this.eventName,
      isLoading: isLoading ?? this.isLoading,
    );
  }
}

class EventNotifier extends StateNotifier<EventState> {
  EventNotifier(this._ref) : super(const EventState()) {
    _restore();
  }

  final Ref _ref;

  SecureStorageService get _storage => _ref.read(secureStorageProvider);
  ApiClient get _api => _ref.read(apiClientProvider);

  Future<void> _restore() async {
    final subdomain = await _storage.readSubdomain();
    if (subdomain == null || subdomain.isEmpty) return;
    state = state.copyWith(subdomain: subdomain);
    await refreshSite();
  }

  Future<void> selectEvent(String rawSubdomain) async {
    final subdomain = rawSubdomain.trim().toLowerCase();
    if (subdomain.isEmpty) {
      throw Exception('Enter an event code or subdomain.');
    }

    state = state.copyWith(subdomain: subdomain, isLoading: true);
    await _storage.writeSubdomain(subdomain);

    try {
      await refreshSite();
    } catch (e) {
      await _storage.deleteSubdomain();
      state = const EventState();
      rethrow;
    }
  }

  Future<void> refreshSite() async {
    state = state.copyWith(isLoading: true);
    try {
      final response = await _api.get<Map<String, dynamic>>('/public/site');
      final data = response.data?['data'] ?? response.data;
      final event = data is Map<String, dynamic> ? data['event'] : null;
      final name = event is Map<String, dynamic> ? event['name'] as String? : null;
      state = state.copyWith(eventName: name, isLoading: false);
    } catch (e) {
      state = state.copyWith(isLoading: false);
      rethrow;
    }
  }

  Future<void> clearEvent() async {
    await _storage.deleteSubdomain();
    state = const EventState();
  }
}

final eventProvider =
    StateNotifierProvider<EventNotifier, EventState>((ref) => EventNotifier(ref));
