import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:expouse/utils/enum/enums.dart';
import 'package:expouse/utils/helpers/helper_functions.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/widgets.dart';
import 'package:get/get.dart';
import 'package:get_storage/get_storage.dart';
import 'package:pusher_channels_flutter/pusher_channels_flutter.dart';
import '../../models/user.dart';
import '../../models/message_model.dart';
import '../../models/chat_room_model.dart';
import '../../utils/config/chat_config.dart';
import '../../utils/helpers/local_key.dart';
import '../../utils/helpers/type_helper.dart';
import 'chat_service.dart';
import '../../utils/helpers/toast_msg.dart';

class ChatController extends GetxController {
  final chatService = ChatService();
  final storage = GetStorage();

  User? currentUser;
  RxList<ChatRoomModel> chatRooms = <ChatRoomModel>[].obs;
  RxList<MessageModel> messages = <MessageModel>[].obs;
  RxInt currentRoomId = 0.obs;
  RxBool isExhibitorChat = false.obs;

  final roomStatus = ApiState.initial.obs;
  final messageStatus = ApiState.initial.obs;
  final sendStatus = ApiState.initial.obs;
  RxBool isPusherInitialized = false.obs;

  // ── Search ────────────────────────────────────────────────────────────────
  final RxString searchQuery = ''.obs;
  Timer? _searchDebounce;

  /// Called on every keystroke. Debounces 400 ms then hits the API.
  void onSearchChanged(String query) {
    searchQuery.value = query;
    _searchDebounce?.cancel();
    _searchDebounce = Timer(const Duration(milliseconds: 400), () {
      fetchRooms(search: query.trim());
    });
  }

  // ── Session-local read tracking ──────────────────────────────────────────
  // We track the latest message ID seen per room. On the first fetchRooms()
  // we record a baseline so pre-existing messages never trigger the badge.
  // A room is "truly unread" only when:
  //   • its latestMessage.id is higher than the recorded baseline, AND
  //   • the latest message was sent by someone else (not the current user).
  bool _isFirstRoomsFetch = true;
  final Map<int, int> _lastSeenMsgIds = {}; // roomId → latestMessage.id

  /// True only if a NEW message from another user arrived after app start.
  bool isRoomTrulyUnread(int roomId) {
    final room = chatRooms.firstWhereOrNull((r) => r.id == roomId);
    if (room == null) return false;
    final latest = room.latestMessage;
    if (latest == null || latest.id == 0) return false;
    // Ignore messages sent by the current user — only other users' messages
    // should show the unread badge/highlight.
    if (currentUser != null && latest.userId == currentUser!.id) return false;
    final lastSeen = _lastSeenMsgIds[roomId] ?? 0;
    return latest.id > lastSeen;
  }

  /// Call when the user opens a chat room — advances the baseline so the badge
  /// clears, and will re-appear only if an even newer message arrives later.
  void markRoomAsRead(int roomId) {
    final room = chatRooms.firstWhereOrNull((r) => r.id == roomId);
    final latestId = room?.latestMessage?.id ?? 0;
    if (latestId > 0) _lastSeenMsgIds[roomId] = latestId;
    // Defer the reactive refresh to avoid setState-during-build.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      chatRooms.refresh();
    });
  }

  /// Total rooms with genuinely new messages from other users (header badge).
  int get totalUnread => chatRooms.where((r) => isRoomTrulyUnread(r.id)).length;

  // ── Polling timers ────────────────────────────────────────────────────────
  /// Per-room poll (5 s) — active only while inside a chat detail view.
  Timer? _pollTimer;

  /// Global poll (30 s) — keeps room list and badge fresh without a spinner.
  Timer? _globalPollTimer;

  // ── Lifecycle ─────────────────────────────────────────────────────────────
  @override
  void onInit() {
    super.onInit();
    _loadCurrentUser();
    fetchRooms();
    initPusher();
    _startGlobalPoll();
  }

  void _startGlobalPoll() {
    _globalPollTimer?.cancel();
    _globalPollTimer = Timer.periodic(const Duration(seconds: 10), (_) {
      _silentFetchRooms();
    });
  }

  void _loadCurrentUser() {
    final userData = storage.read(LocalKeyHelper.userInfo);
    if (userData != null) {
      currentUser = User.fromJson(userData);
    }
  }

  // ── Pusher ────────────────────────────────────────────────────────────────
  Future<void> initPusher() async {
    if (isPusherInitialized.value) return;

    await ChatConfig.initPusher(
      onEvent: onEvent,
      onError: (_, __, ___) {},
      onSubscriptionSucceeded: (_, __) {},
      onConnectionStateChange: (_, __) {},
      onSubscriptionError: (_, __) {},
      onDecryptionFailure: (_, __) {},
      onMemberAdded: (_, __) {},
      onMemberRemoved: (_, __) {},
      onSubscriptionCount: (_, __) {},
    );

    isPusherInitialized.value = true;
    if (currentUser != null) subscribeToNotifications();
  }

  void subscribeToNotifications() {
    if (currentUser == null) return;
    final userId = currentUser!.id;
    ChatConfig.subscribeToChannel('private-user-message-notify.$userId');
    ChatConfig.subscribeToChannel('private-user-message.$userId');
    ChatConfig.subscribeToChannel('private-user.$userId');
    ChatConfig.subscribeToChannel('private-App.Models.User.$userId');
    ChatConfig.subscribeToChannel('private-App.User.$userId');
    ChatConfig.subscribeToChannel('private-messages.$userId');
    ChatConfig.subscribeToChannel('private-notification.$userId');
  }

  // ── Room entry / exit ─────────────────────────────────────────────────────
  void enterRoom(int roomId, int targetUserId, {bool isExhibitor = false}) {
    _pollTimer?.cancel();

    // Mark this room as read locally so the badge clears immediately.
    markRoomAsRead(roomId);

    currentRoomId.value = roomId;
    _currentPartnerId = targetUserId;
    isExhibitorChat.value = isExhibitor;
    messages.clear();
    fetchMessages(targetUserId);

    if (isExhibitor) {
      ChatConfig.subscribeToChannel('private-exhibitor-message.$roomId');
      ChatConfig.subscribeToChannel('private-exhibitor.$roomId');
    } else {
      ChatConfig.subscribeToChannel('private-user-message.$roomId');
      ChatConfig.subscribeToChannel('private-chat.$roomId');
      ChatConfig.subscribeToChannel('private-room.$roomId');
      ChatConfig.subscribeToChannel('private-chat-room.$roomId');
      ChatConfig.subscribeToChannel('private-message.$roomId');
      ChatConfig.subscribeToChannel('private-messages.$roomId');
      ChatConfig.subscribeToChannel('chat.$roomId');
      ChatConfig.subscribeToChannel('message.$roomId');
      ChatConfig.subscribeToChannel('room.$roomId');
    }

    // Per-room polling fallback — guarantees messages appear within 5 s even
    // if Pusher events are not delivered.
    _pollTimer = Timer.periodic(const Duration(seconds: 5), (_) {
      if (currentRoomId.value != 0 && _currentPartnerId != 0) {
        _silentRefreshMessages();
      }
    });
  }

  /// Partner user ID for the current open room — used by the per-room poll.
  int _currentPartnerId = 0;

  void leaveRoom() {
    _pollTimer?.cancel();
    _pollTimer = null;
    _currentPartnerId = 0;

    if (currentRoomId.value != 0) {
      final roomId = currentRoomId.value;
      if (isExhibitorChat.value) {
        ChatConfig.unsubscribeFromChannel('private-exhibitor-message.$roomId');
        ChatConfig.unsubscribeFromChannel('private-exhibitor.$roomId');
      } else {
        ChatConfig.unsubscribeFromChannel('private-user-message.$roomId');
        ChatConfig.unsubscribeFromChannel('private-chat.$roomId');
        ChatConfig.unsubscribeFromChannel('private-room.$roomId');
        ChatConfig.unsubscribeFromChannel('private-chat-room.$roomId');
        ChatConfig.unsubscribeFromChannel('private-message.$roomId');
        ChatConfig.unsubscribeFromChannel('private-messages.$roomId');
        ChatConfig.unsubscribeFromChannel('chat.$roomId');
        ChatConfig.unsubscribeFromChannel('message.$roomId');
        ChatConfig.unsubscribeFromChannel('room.$roomId');
      }
      currentRoomId.value = 0;
    }
  }

  // ── Room fetch ────────────────────────────────────────────────────────────

  /// Public fetch — shows loading indicator (initial load & pull-to-refresh).
  Future<void> fetchRooms({String? search}) async {
    await handleApiClient(
      onStateChanged: (state) => roomStatus(state),
      handleApiCall: () => _doFetchRooms(search: search ?? searchQuery.value.trim()),
    );
  }

  /// Silent fetch — updates chatRooms WITHOUT changing roomStatus so the UI
  /// never shows a loading spinner for background polls or Pusher events.
  /// Search is intentionally bypassed for background polls so the list
  /// stays complete when the user is not actively searching.
  Future<void> _silentFetchRooms() async {
    // Don't disrupt an active search result with a background poll.
    if (searchQuery.value.trim().isNotEmpty) return;
    try {
      final response = await chatService.getChatRooms();
      dynamic roomsData = response.data;
      if (roomsData is Map && roomsData.containsKey('data')) {
        roomsData = roomsData['data'];
      }
      if (roomsData is List) await _doFetchRooms(preloaded: roomsData);
    } catch (_) {
      // Silent — background poll failures are non-critical.
    }
  }

  /// Core fetch logic shared by fetchRooms and _silentFetchRooms.
  Future<void> _doFetchRooms({List<dynamic>? preloaded, String? search}) async {
    List<dynamic> rawList;
    if (preloaded != null) {
      rawList = preloaded;
    } else {
      final response = await chatService.getChatRooms(search: search);
      dynamic roomsData = response.data;
      if (roomsData is Map && roomsData.containsKey('data')) {
        roomsData = roomsData['data'];
      }
      if (roomsData is! List) return;
      rawList = roomsData;
    }

    final parsed = rawList.map((e) => ChatRoomModel.fromJson(e)).toList();

    if (_isFirstRoomsFetch) {
      // Seed the baseline — record every room's current latestMessage.id.
      // Only rooms whose ID grows beyond this value (and from other users)
      // will be flagged as truly unread.
      _isFirstRoomsFetch = false;
      for (final r in parsed) {
        _lastSeenMsgIds[r.id] = r.latestMessage?.id ?? 0;
      }
    }

    chatRooms.assignAll(parsed);
  }

  // ── Message fetch ─────────────────────────────────────────────────────────
  Future<void> fetchMessages(int targetUserId) async {
    await handleApiClient(
      onStateChanged: (state) => messageStatus(state),
      handleApiCall: () async {
        final response = await chatService.getRoomDetails(targetUserId);
        final roomData = response.data is Map && response.data.containsKey('data')
            ? response.data['data']
            : response.data;

        if (roomData != null && roomData is Map) {
          final dynamic rawMessages = roomData['messages'];
          if (rawMessages is List) {
            messages.assignAll(rawMessages.map((e) => MessageModel.fromJson(e)).toList());
          }
          final serverRoomId = TypeHelper.toInt(roomData['id']);
          if (serverRoomId != 0 && serverRoomId != currentRoomId.value) {
            final oldId = currentRoomId.value;
            currentRoomId.value = serverRoomId;
            if (!isExhibitorChat.value) {
              for (final ch in _roomChannels(oldId)) {
                ChatConfig.unsubscribeFromChannel(ch);
              }
              for (final ch in _roomChannels(serverRoomId)) {
                ChatConfig.subscribeToChannel(ch);
              }
            }
          } else if (serverRoomId != 0) {
            currentRoomId.value = serverRoomId;
          }
        }
      },
    );
  }

  /// Returns all standard channel names for a given room ID.
  List<String> _roomChannels(int roomId) => [
        'private-user-message.$roomId',
        'private-chat.$roomId',
        'private-room.$roomId',
        'private-chat-room.$roomId',
        'private-message.$roomId',
        'private-messages.$roomId',
        'chat.$roomId',
        'message.$roomId',
        'room.$roomId',
      ];

  /// Silent per-room refresh used by the polling timer.
  Future<void> _silentRefreshMessages() async {
    if (_currentPartnerId == 0) return;
    try {
      final response = await chatService.getRoomDetails(_currentPartnerId);
      final roomData = response.data is Map && response.data.containsKey('data')
          ? response.data['data']
          : response.data;

      if (roomData != null && roomData is Map) {
        final dynamic rawMessages = roomData['messages'];
        if (rawMessages is List) {
          final fetched = rawMessages.map((e) => MessageModel.fromJson(e)).toList();
          final optimistic = messages.where((m) => m.id == -1).toList();

          final hasNew = fetched.length != messages.where((m) => m.id != -1).length ||
              fetched.any((c) => !messages.any((m) => m.id == c.id));
          if (!hasNew) return;

          final merged = [...fetched];
          for (final opt in optimistic) {
            if (!fetched.any((c) => c.body == opt.body && c.userId == opt.userId)) {
              merged.add(opt);
            }
          }
          messages.assignAll(merged);
        }
      }
    } catch (_) {
      // Silent.
    }
  }

  // ── Pusher event handler ──────────────────────────────────────────────────
  dynamic onEvent(PusherEvent event) {
    if (event.eventName.contains('subscription')) return;

    try {
      dynamic data = event.data;
      if (data is String) {
        try { data = jsonDecode(data); } catch (_) {}
      }

      final bool isMessage =
          event.eventName.toLowerCase().contains('message') ||
          event.eventName.toLowerCase().contains('sent') ||
          (data is Map && (data.containsKey('message') || data.containsKey('context')));

      if (isMessage) {
        final messageData = data is Map && data.containsKey('message') ? data['message'] : data;
        if (messageData is Map) {
          final newMessage = MessageModel.fromJson(Map<String, dynamic>.from(messageData));
          final bool isCurrent =
              event.channelName.contains(currentRoomId.value.toString()) ||
              (data is Map && data.containsKey('room_id') &&
                  TypeHelper.toInt(data['room_id']) == currentRoomId.value) ||
              event.channelName.contains('notify');

          if (currentRoomId.value == 0 || isCurrent) {
            final exists = messages.any((m) =>
                (m.id == newMessage.id && m.id != 0 && m.id != -1) ||
                (m.id == -1 && m.body == newMessage.body && m.userId == newMessage.userId));
            if (!exists) {
              messages.add(newMessage);
            } else {
              final idx = messages.indexWhere(
                  (m) => m.id == -1 && m.body == newMessage.body && m.userId == newMessage.userId);
              if (idx != -1) messages[idx] = newMessage;
            }
          }
        }
      }

      // Silent room-list refresh to update badge and list without a spinner.
      _silentFetchRooms();
    } catch (_) {}
  }

  // ── Send message ──────────────────────────────────────────────────────────
  Future<void> sendMessage({
    String? message,
    String? attachType,
    String? fileBase64,
    String? localFilePath,
  }) async {
    if (currentRoomId.value == 0) return;

    // Optimistic update — show message immediately while upload is in progress.
    messages.add(MessageModel(
      id: -1,
      userId: currentUser?.id ?? 0,
      body: message,
      attachType: attachType,
      attach: localFilePath,
      createdAt: DateTime.now().toIso8601String(),
    ));

    await handleApiClient(
      onStateChanged: (state) => sendStatus(state),
      handleApiCall: () async {
        await chatService.sendMessage(
          roomId: currentRoomId.value,
          message: message,
          attachType: attachType,
          fileBase64: fileBase64,
        );
      },
    );
  }

  // ── File picker ───────────────────────────────────────────────────────────
  Future<void> pickFile() async {
    try {
      final result = await FilePicker.pickFiles(
        type: FileType.custom,
        allowedExtensions: ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'mp4'],
      );

      if (result == null || result.files.single.path == null) return;

      final pickedFile = result.files.single;
      final filePath = pickedFile.path!;
      final extension = pickedFile.extension?.toLowerCase();

      const maxBytes = 5 * 1024 * 1024;
      if (pickedFile.size > maxBytes) {
        ToastMsg.showErrorMessage('File too large. Maximum allowed size is 5 MB.');
        return;
      }

      String attachType;
      String mimeType;
      switch (extension) {
        case 'pdf':
          attachType = 'pdf';
          mimeType = 'application/pdf';
        case 'mp4':
          attachType = 'video';
          mimeType = 'video/mp4';
        case 'png':
          attachType = 'image';
          mimeType = 'image/png';
        case 'gif':
          attachType = 'image';
          mimeType = 'image/gif';
        case 'webp':
          attachType = 'image';
          mimeType = 'image/webp';
        default:
          attachType = 'image';
          mimeType = 'image/jpeg';
      }

      final bytes = await File(filePath).readAsBytes();
      final fileBase64 = 'data:$mimeType;base64,${base64Encode(bytes)}';

      await sendMessage(
        attachType: attachType,
        fileBase64: fileBase64,
        localFilePath: filePath,
      );
    } catch (_) {}
  }

  // ── Cleanup ───────────────────────────────────────────────────────────────
  @override
  void onClose() {
    _searchDebounce?.cancel();
    _pollTimer?.cancel();
    _globalPollTimer?.cancel();
    leaveRoom();
    ChatConfig.disconnect();
    super.onClose();
  }
}
