import 'dart:convert';
import 'package:dio/dio.dart';
import 'package:get_storage/get_storage.dart';
import 'package:pusher_channels_flutter/pusher_channels_flutter.dart';
import 'client_config.dart';
import '../helpers/app_data_provider.dart';
import '../helpers/local_key.dart';

class ChatConfig {
  static const String _appKey = 'd14f1e5bcb578802fc80';
  static const String _cluster = 'mt1';

  static final PusherChannelsFlutter pusher = PusherChannelsFlutter.getInstance();

  /// Cached after the first successful auth so subsequent channel subscriptions
  /// skip the URL-discovery loop.
  static String? _resolvedAuthUrl;

  static Future<void> initPusher({
    required dynamic Function(PusherEvent) onEvent,
    required dynamic Function(String, int?, dynamic) onError,
    required dynamic Function(String, dynamic) onSubscriptionSucceeded,
    required dynamic Function(String, String) onConnectionStateChange,
    required dynamic Function(String, dynamic) onSubscriptionError,
    required dynamic Function(String, String) onDecryptionFailure,
    required dynamic Function(String, PusherMember) onMemberAdded,
    required dynamic Function(String, PusherMember) onMemberRemoved,
    required dynamic Function(String, int) onSubscriptionCount,
  }) async {
    final token = GetStorage().read(LocalKeyHelper.token);
    if (token == null) return;

    try {
      await pusher.init(
        apiKey: _appKey,
        cluster: _cluster,
        onAuthorizer: (channelName, socketId, _) =>
            _authorize(channelName, socketId, token),
        onEvent: onEvent,
        onError: onError,
        onSubscriptionSucceeded: onSubscriptionSucceeded,
        onConnectionStateChange: onConnectionStateChange,
        onSubscriptionError: onSubscriptionError,
        onDecryptionFailure: onDecryptionFailure,
        onMemberAdded: onMemberAdded,
        onMemberRemoved: onMemberRemoved,
        onSubscriptionCount: onSubscriptionCount,
      );
      await pusher.connect();
    } catch (_) {
      // Pusher init failure is non-fatal — the app works without real-time.
    }
  }

  /// Resolves the correct broadcasting auth URL and caches it for the session.
  static Future<dynamic> _authorize(
    String channelName,
    String socketId,
    String token,
  ) async {
    final candidates = _authUrlCandidates();

    // Fast path: use the already-resolved URL if available.
    if (_resolvedAuthUrl != null) {
      return _postAuth(_resolvedAuthUrl!, channelName, socketId, token);
    }

    // Slow path: try each candidate and cache the first that works.
    dynamic lastError;
    for (final url in candidates) {
      try {
        final result = await _postAuth(url, channelName, socketId, token);
        _resolvedAuthUrl = url; // cache for all future calls this session
        return result;
      } catch (e) {
        lastError = e;
      }
    }
    throw lastError;
  }

  static Future<dynamic> _postAuth(
    String url,
    String channelName,
    String socketId,
    String token,
  ) async {
    final response = await ClientConfig.client.post(
      url,
      data: {'socket_id': socketId, 'channel_name': channelName},
      options: Options(
        contentType: Headers.formUrlEncodedContentType,
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
          'subdomain': AppDataProvider.obj.subdomain,
        },
      ),
    );
    return response.data is String ? jsonDecode(response.data) : response.data;
  }

  static List<String> _authUrlCandidates() {
    final baseUrl = ClientConfig.client.options.baseUrl;
    if (baseUrl.endsWith('/api/')) {
      final root = baseUrl.substring(0, baseUrl.length - 5);
      return ['$root/broadcasting/auth', '${baseUrl}broadcasting/auth'];
    }
    if (baseUrl.endsWith('/api')) {
      final root = baseUrl.substring(0, baseUrl.length - 4);
      return ['$root/broadcasting/auth', '$baseUrl/broadcasting/auth'];
    }
    return ['${baseUrl}broadcasting/auth'];
  }

  static Future<void> subscribeToChannel(String channelName) async {
    try {
      await pusher.subscribe(channelName: channelName);
    } catch (_) {}
  }

  static Future<void> unsubscribeFromChannel(String channelName) async {
    try {
      await pusher.unsubscribe(channelName: channelName);
    } catch (_) {}
  }

  static Future<void> disconnect() async {
    _resolvedAuthUrl = null; // reset cache on disconnect
    await pusher.disconnect();
  }
}
