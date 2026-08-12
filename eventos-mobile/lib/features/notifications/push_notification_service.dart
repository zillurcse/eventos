import 'dart:convert';
import 'dart:io';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:get/get.dart';
import 'package:get_storage/get_storage.dart';
import 'package:permission_handler/permission_handler.dart';

import '../../utils/helpers/local_key.dart';
import '../../utils/helpers/secure_auth_storage.dart';
import '../chat/chat_controller.dart';
import '../chat/pages/chat_detail_view.dart';
import 'device_token_service.dart';

/// Top-level background handler (must be a top-level or static function).
@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  // No UI work here - OS tray already shows notification payload.
}

/// FCM + local notifications. Safe when Firebase is not configured yet:
/// init() catches failures and leaves push disabled.
class PushNotificationService {
  PushNotificationService._();
  static final PushNotificationService instance = PushNotificationService._();

  /// Lazily set after [Firebase.initializeApp] succeeds.
  FirebaseMessaging? _messaging;
  final _local = FlutterLocalNotificationsPlugin();
  final _tokenApi = DeviceTokenService();
  final _storage = GetStorage();

  bool _ready = false;
  String? _fcmToken;
  Map<String, dynamic>? _pendingOpen;

  static const _androidChannel = AndroidNotificationChannel(
    'eventos_chat',
    'Chat messages',
    description: 'New chat messages and event alerts',
    importance: Importance.high,
  );

  Future<void> init() async {
    if (_ready) return;
    try {
      if (Firebase.apps.isEmpty) {
        await Firebase.initializeApp();
      }
    } catch (e) {
      debugPrint('Firebase init skipped: $e');
      return;
    }

    _messaging = FirebaseMessaging.instance;

    FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);

    const androidInit = AndroidInitializationSettings('@mipmap/ic_launcher');
    const iosInit = DarwinInitializationSettings(
      requestAlertPermission: false,
      requestBadgePermission: false,
      requestSoundPermission: false,
    );
    await _local.initialize(
      InitializationSettings(android: androidInit, iOS: iosInit),
      onDidReceiveNotificationResponse: _onLocalTap,
    );

    await _local
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(_androidChannel);

    await _requestPermission();

    FirebaseMessaging.onMessage.listen(_onForegroundMessage);
    FirebaseMessaging.onMessageOpenedApp.listen(_onMessageOpened);

    final messaging = _messaging!;
    final initial = await messaging.getInitialMessage();
    if (initial != null) {
      _pendingOpen = Map<String, dynamic>.from(initial.data);
    }

    messaging.onTokenRefresh.listen((token) async {
      _fcmToken = token;
      await _registerToken(token);
    });

    _ready = true;

    if (SecureAuthStorage.instance.hasToken) {
      await registerCurrentToken();
    }
  }

  Future<void> _requestPermission() async {
    final messaging = _messaging;
    if (messaging == null) return;

    if (Platform.isAndroid) {
      await Permission.notification.request();
    }
    await messaging.requestPermission(
      alert: true,
      badge: true,
      sound: true,
    );
  }

  Future<void> registerCurrentToken() async {
    if (!_ready) return;
    final messaging = _messaging;
    if (messaging == null) return;
    try {
      final token = await messaging.getToken();
      if (token == null || token.isEmpty) return;
      _fcmToken = token;
      await _registerToken(token);
    } catch (e) {
      debugPrint('FCM token register skipped: $e');
    }
  }

  Future<void> _registerToken(String token) async {
    if (!SecureAuthStorage.instance.hasToken) return;
    final platform = Platform.isIOS ? 'ios' : 'android';
    try {
      await _tokenApi.register(platform: platform, token: token);
      await _storage.write(LocalKeyHelper.fcmToken, token);
    } catch (e) {
      debugPrint('device-tokens register failed: $e');
    }
  }

  Future<void> unregisterOnLogout() async {
    final token = _fcmToken ?? _storage.read(LocalKeyHelper.fcmToken);
    if (token is String && token.isNotEmpty) {
      try {
        await _tokenApi.unregister(token);
      } catch (_) {}
    }
    try {
      if (_ready) await _messaging?.deleteToken();
    } catch (_) {}
    _fcmToken = null;
    await _storage.remove(LocalKeyHelper.fcmToken);
  }

  Future<void> _onForegroundMessage(RemoteMessage message) async {
    final data = message.data;
    final conversationId = data['conversation_id']?.toString();

    // Suppress system banner when the user is already in that thread.
    if (conversationId != null &&
        Get.isRegistered<ChatController>() &&
        Get.find<ChatController>().openConversationId == conversationId) {
      if (Get.isRegistered<ChatController>()) {
        Get.find<ChatController>().fetchRooms();
      }
      return;
    }

    final title = message.notification?.title ??
        data['title']?.toString() ??
        'New message';
    final body =
        message.notification?.body ?? data['body']?.toString() ?? '';

    await _local.show(
      message.hashCode,
      title,
      body,
      NotificationDetails(
        android: AndroidNotificationDetails(
          _androidChannel.id,
          _androidChannel.name,
          channelDescription: _androidChannel.description,
          importance: Importance.high,
          priority: Priority.high,
          icon: '@mipmap/ic_launcher',
        ),
        iOS: const DarwinNotificationDetails(),
      ),
      payload: jsonEncode(data),
    );

    if (Get.isRegistered<ChatController>()) {
      Get.find<ChatController>().fetchRooms();
    }
  }

  void _onMessageOpened(RemoteMessage message) {
    _navigateFromData(Map<String, dynamic>.from(message.data));
  }

  void _onLocalTap(NotificationResponse response) {
    final payload = response.payload;
    if (payload == null || payload.isEmpty) return;
    try {
      final data = jsonDecode(payload);
      if (data is Map) {
        _navigateFromData(Map<String, dynamic>.from(data));
      }
    } catch (_) {}
  }

  /// Call after the main shell is ready to consume a cold-start tap.
  void consumePendingOpen() {
    final pending = _pendingOpen;
    _pendingOpen = null;
    if (pending != null) {
      _navigateFromData(pending);
    }
  }

  void _navigateFromData(Map<String, dynamic> data) {
    final type = data['type']?.toString();
    final conversationId = data['conversation_id']?.toString();
    if (type != 'chat' || conversationId == null || conversationId.isEmpty) {
      return;
    }

    Future.microtask(() async {
      if (!Get.isRegistered<ChatController>()) {
        Get.put(ChatController());
      }
      final chat = Get.find<ChatController>();
      await chat.fetchRooms();
      final room =
          chat.chatRooms.firstWhereOrNull((r) => r.id == conversationId);
      Get.to(
        () => ChatDetailView(
          conversationId: conversationId,
          partnerId: room?.partner?.id ?? '',
          partnerName: room?.partner?.name ??
              data['sender_name']?.toString() ??
              'Chat',
          partnerImageUrl: room?.partner?.avatarUrl,
        ),
      );
    });
  }
}
