import 'dart:async';

import 'package:pusher_reverb_flutter/pusher_reverb_flutter.dart';

import 'app_config.dart';

/// Laravel Reverb client for live chat (same stack as eventos-event Echo).
///
/// Chat channels are public: `event.{eventUuid}.chat.{participationUuid}`.
class ChatConfig {
  static bool _initialized = false;
  static String? _subscribedChannel;
  static StreamSubscription? _eventSub;
  static void Function(String eventName, dynamic data)? _onEvent;

  static Future<void> init({
    required void Function(String eventName, dynamic data) onEvent,
    required void Function(bool connected) onConnectionChange,
  }) async {
    if (_initialized) {
      _onEvent = onEvent;
      return;
    }

    _onEvent = onEvent;

    try {
      final client = ReverbClient.instance(
        host: AppConfig.reverbHost,
        port: AppConfig.reverbPort,
        appKey: AppConfig.reverbKey,
        useTLS: AppConfig.reverbUseTls,
        onConnected: (_) => onConnectionChange(true),
        onDisconnected: () => onConnectionChange(false),
        onError: (_) => onConnectionChange(false),
      );
      await client.connect();
      _initialized = true;
      onConnectionChange(true);
    } catch (_) {
      // Reverb failure is non-fatal — REST + light poll still work.
      onConnectionChange(false);
    }
  }

  static Future<void> subscribeToPersonalChannel(String channelName) async {
    if (!_initialized) return;
    if (_subscribedChannel == channelName) return;

    await unsubscribeCurrent();

    try {
      final client = ReverbClient.instance();
      final channel = client.subscribeToChannel(channelName);
      _eventSub = channel.on('chat.message.created').listen((event) {
        _onEvent?.call('chat.message.created', event.data);
      });
      _subscribedChannel = channelName;
    } catch (_) {}
  }

  static Future<void> unsubscribeCurrent() async {
    await _eventSub?.cancel();
    _eventSub = null;
    final name = _subscribedChannel;
    _subscribedChannel = null;
    if (name == null || !_initialized) return;
    try {
      ReverbClient.instance().unsubscribeFromChannel(name);
    } catch (_) {}
  }

  static Future<void> disconnect() async {
    await unsubscribeCurrent();
    try {
      if (_initialized) {
        ReverbClient.instance().disconnect();
      }
    } catch (_) {}
    try {
      ReverbClient.resetInstance();
    } catch (_) {}
    _initialized = false;
    _onEvent = null;
  }
}
