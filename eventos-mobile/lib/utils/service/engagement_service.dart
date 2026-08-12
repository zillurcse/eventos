import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:math';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:get_storage/get_storage.dart';

import '../config/dio_config.dart';
import '../helpers/app_data_provider.dart';
import '../helpers/secure_auth_storage.dart';

/// Fire-and-forget engagement tracking for the mobile app.
///
/// POSTs to `events/{uuid}/engagement/track` with `platform: ios|android`.
/// Failed events are queued in GetStorage and retried on the next track call.
class EngagementService {
  EngagementService._();
  static final EngagementService instance = EngagementService._();
  factory EngagementService() => instance;

  static const _queueKey = 'engagement_pending_queue';
  static const _sessionKey = 'engagement_client_session';
  static const _maxQueue = 100;

  final _storage = GetStorage();
  bool _flushing = false;

  String get _platform => Platform.isIOS ? 'ios' : 'android';

  String get clientSessionId {
    final existing = _storage.read(_sessionKey) as String?;
    if (existing != null && existing.isNotEmpty) return existing;
    final id =
        'm-${DateTime.now().millisecondsSinceEpoch}-${Random().nextInt(1 << 32)}';
    _storage.write(_sessionKey, id);
    return id;
  }

  /// Stable once-per-target key for noisy actions (profile / booth views).
  String onceKey(String action, Object target) {
    final day = DateTime.now().toIso8601String().substring(0, 10);
    return '$action:$target:$day:$clientSessionId';
  }

  /// Record an engagement action. Never throws; never blocks UX.
  void track({
    required String actionType,
    String? objectType,
    int? objectId,
    String? objectUuid,
    int? durationMs,
    String? occurredAt,
    String? idempotencyKey,
    Map<String, dynamic>? metadata,
  }) {
    if (actionType.isEmpty) return;
    if (!SecureAuthStorage.instance.hasToken) return;
    final eventUuid = AppDataProvider.obj.eventUuid;
    if (eventUuid == null || eventUuid.isEmpty) return;

    final meta = <String, dynamic>{...?metadata};
    if (objectUuid != null && objectUuid.isNotEmpty) {
      meta['object_uuid'] = objectUuid;
    }

    final body = <String, dynamic>{
      'action_type': actionType,
      'platform': _platform,
      'device_class': 'phone',
      'client_session_id': clientSessionId,
      if (objectType != null) 'object_type': objectType,
      if (objectId != null) 'object_id': objectId,
      if (durationMs != null) 'duration_ms': durationMs,
      if (occurredAt != null) 'occurred_at': occurredAt,
      if (idempotencyKey != null) 'idempotency_key': idempotencyKey,
      if (meta.isNotEmpty) 'metadata': meta,
    };

    unawaited(_sendOrQueue(eventUuid, body));
  }

  Future<void> _sendOrQueue(String eventUuid, Map<String, dynamic> body) async {
    final dio = DioConfig.obj.dio;
    if (dio == null) {
      _enqueue(body);
      return;
    }

    try {
      await dio.post(
        'events/$eventUuid/engagement/track',
        data: body,
        options: Options(
          extra: {'skipAuthLogout': true},
          validateStatus: (s) => s != null && s >= 200 && s < 300,
        ),
      );
      unawaited(flushQueue());
    } catch (e) {
      if (kDebugMode) {
        debugPrint('[engagement] track failed, queued: $e');
      }
      _enqueue(body);
    }
  }

  void _enqueue(Map<String, dynamic> body) {
    final raw = _storage.read(_queueKey);
    final list = <Map<String, dynamic>>[];
    if (raw is List) {
      for (final item in raw) {
        if (item is Map) {
          list.add(Map<String, dynamic>.from(item));
        } else if (item is String) {
          try {
            final decoded = jsonDecode(item);
            if (decoded is Map) {
              list.add(Map<String, dynamic>.from(decoded));
            }
          } catch (_) {}
        }
      }
    }
    list.add(body);
    while (list.length > _maxQueue) {
      list.removeAt(0);
    }
    _storage.write(_queueKey, list);
  }

  /// Retry queued events (best-effort). Safe to call often.
  Future<void> flushQueue() async {
    if (_flushing) return;
    if (!SecureAuthStorage.instance.hasToken) return;
    final eventUuid = AppDataProvider.obj.eventUuid;
    if (eventUuid == null || eventUuid.isEmpty) return;
    final dio = DioConfig.obj.dio;
    if (dio == null) return;

    final raw = _storage.read(_queueKey);
    if (raw is! List || raw.isEmpty) return;

    _flushing = true;
    try {
      final remaining = <Map<String, dynamic>>[];
      for (final item in raw) {
        Map<String, dynamic>? body;
        if (item is Map) {
          body = Map<String, dynamic>.from(item);
        }
        if (body == null) continue;
        try {
          await dio.post(
            'events/$eventUuid/engagement/track',
            data: body,
            options: Options(
              extra: {'skipAuthLogout': true},
              validateStatus: (s) => s != null && s >= 200 && s < 300,
            ),
          );
        } catch (_) {
          remaining.add(body);
        }
      }
      if (remaining.isEmpty) {
        _storage.remove(_queueKey);
      } else {
        _storage.write(_queueKey, remaining);
      }
    } finally {
      _flushing = false;
    }
  }
}
