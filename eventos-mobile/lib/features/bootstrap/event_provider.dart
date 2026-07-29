import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../core/storage/secure_storage.dart';

class EventState {
  const EventState({
    this.subdomain,
    this.eventName,
    this.eventUuid,
    this.logoUrl,
    this.signupEnabled = true,
    this.otpEnabled = false,
    this.socialChannels = const {},
    this.isLoading = false,
  });

  final String? subdomain;
  final String? eventName;
  final String? eventUuid;
  final String? logoUrl;
  final bool signupEnabled;
  final bool otpEnabled;
  final Map<String, bool> socialChannels;
  final bool isLoading;

  bool get hasEvent => subdomain != null && subdomain!.isNotEmpty;

  EventState copyWith({
    String? subdomain,
    String? eventName,
    String? eventUuid,
    String? logoUrl,
    bool? signupEnabled,
    bool? otpEnabled,
    Map<String, bool>? socialChannels,
    bool? isLoading,
  }) {
    return EventState(
      subdomain: subdomain ?? this.subdomain,
      eventName: eventName ?? this.eventName,
      eventUuid: eventUuid ?? this.eventUuid,
      logoUrl: logoUrl ?? this.logoUrl,
      signupEnabled: signupEnabled ?? this.signupEnabled,
      otpEnabled: otpEnabled ?? this.otpEnabled,
      socialChannels: socialChannels ?? this.socialChannels,
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
      if (data is! Map<String, dynamic>) {
        throw Exception('Invalid site response');
      }

      final event = data['event'];
      final branding = data['branding'];
      final login = data['login'];
      final channels = login is Map ? login['channels'] : null;

      final social = <String, bool>{};
      if (channels is Map) {
        for (final key in ['facebook', 'google', 'linkedin']) {
          social[key] = channels[key] == true;
        }
      }

      state = state.copyWith(
        eventName: event is Map ? event['name'] as String? : null,
        eventUuid: event is Map ? event['uuid']?.toString() : null,
        logoUrl: branding is Map ? branding['logo_url'] as String? : null,
        signupEnabled: channels is Map ? channels['signup'] != false : true,
        otpEnabled: channels is Map ? channels['otp'] == true : false,
        socialChannels: social,
        isLoading: false,
      );
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
