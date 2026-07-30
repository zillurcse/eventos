import 'dart:async';
import 'dart:convert';

import 'package:dio/dio.dart' as dio;
import 'package:expouse/utils/enum/enums.dart';
import 'package:expouse/utils/helpers/helper_functions.dart';
import 'package:file_picker/file_picker.dart';
import 'package:get/get.dart';
import 'package:get_storage/get_storage.dart';

import '../../models/chat_attachment.dart';
import '../../models/chat_person.dart';
import '../../models/chat_room_model.dart';
import '../../models/message_model.dart';
import '../../models/user.dart';
import '../../utils/config/chat_config.dart';
import '../../utils/helpers/app_data_provider.dart';
import '../../utils/helpers/local_key.dart';
import '../../utils/helpers/toast_msg.dart';
import 'chat_service.dart';

class ChatController extends GetxController {
  final chatService = ChatService();
  final storage = GetStorage();

  User? currentUser;
  String? meParticipationUuid;
  ChatPerson? myProfile;

  RxList<ChatRoomModel> chatRooms = <ChatRoomModel>[].obs;
  RxList<MessageModel> messages = <MessageModel>[].obs;
  final currentConversationId = ''.obs;
  RxBool isPusherInitialized = false.obs;

  final roomStatus = ApiState.initial.obs;
  final messageStatus = ApiState.initial.obs;
  final sendStatus = ApiState.initial.obs;

  final RxString searchQuery = ''.obs;
  Timer? _searchDebounce;
  Timer? _pollTimer;
  Timer? _globalPollTimer;
  bool _socketConnected = false;

  /// Conversation currently open — used to suppress FCM banners.
  String? get openConversationId =>
      currentConversationId.value.isEmpty ? null : currentConversationId.value;

  int get totalUnread =>
      chatRooms.fold<int>(0, (sum, r) => sum + r.unread);

  bool isRoomTrulyUnread(String roomId) {
    final room = chatRooms.firstWhereOrNull((r) => r.id == roomId);
    return (room?.unread ?? 0) > 0;
  }

  @override
  void onInit() {
    super.onInit();
    _loadCurrentUser();
    fetchRooms();
    initPusher();
    _startGlobalPoll();
  }

  void _loadCurrentUser() {
    final userData = storage.read(LocalKeyHelper.userInfo);
    if (userData != null) {
      currentUser = User.fromJson(userData);
    }
  }

  void onSearchChanged(String query) {
    searchQuery.value = query;
    _searchDebounce?.cancel();
    _searchDebounce = Timer(const Duration(milliseconds: 400), () {
      fetchRooms(search: query.trim());
    });
  }

  void _startGlobalPoll() {
    _globalPollTimer?.cancel();
    // Light fallback when Reverb is down; quiet when live socket is healthy.
    _globalPollTimer = Timer.periodic(const Duration(seconds: 15), (_) {
      if (!_socketConnected) _silentFetchRooms();
    });
  }

  // ── Reverb ────────────────────────────────────────────────────────────────
  Future<void> initPusher() async {
    if (isPusherInitialized.value) return;

    await ChatConfig.init(
      onEvent: onLiveEvent,
      onConnectionChange: (connected) {
        _socketConnected = connected;
      },
    );

    isPusherInitialized.value = true;
    await _subscribePersonalChannel();
  }

  Future<void> _subscribePersonalChannel() async {
    final eventUuid = AppDataProvider.obj.eventUuid;
    final me = meParticipationUuid;
    if (eventUuid == null || eventUuid.isEmpty || me == null || me.isEmpty) {
      return;
    }
    await ChatConfig.subscribeToPersonalChannel('event.$eventUuid.chat.$me');
  }

  // ── Room entry / exit ─────────────────────────────────────────────────────
  void enterRoom(String conversationId, {String? partnerId}) {
    _pollTimer?.cancel();
    currentConversationId.value = conversationId;
    messages.clear();
    fetchMessages(conversationId);
    markRoomAsRead(conversationId);

    _pollTimer = Timer.periodic(const Duration(seconds: 8), (_) {
      if (currentConversationId.value.isNotEmpty && !_socketConnected) {
        _silentRefreshMessages();
      }
    });
  }

  void leaveRoom() {
    _pollTimer?.cancel();
    _pollTimer = null;
    currentConversationId.value = '';
  }

  Future<void> markRoomAsRead(String conversationId) async {
    final idx = chatRooms.indexWhere((r) => r.id == conversationId);
    if (idx != -1 && chatRooms[idx].unread > 0) {
      chatRooms[idx] = chatRooms[idx].copyWith(unread: 0);
      chatRooms.refresh();
    }
    try {
      await chatService.markRead(conversationId);
    } catch (_) {}
  }

  // ── Inbox ─────────────────────────────────────────────────────────────────
  Future<void> fetchRooms({String? search}) async {
    await handleApiClient(
      onStateChanged: (state) => roomStatus(state),
      handleApiCall: () =>
          _doFetchRooms(search: search ?? searchQuery.value.trim()),
    );
  }

  Future<void> _silentFetchRooms() async {
    if (searchQuery.value.trim().isNotEmpty) return;
    try {
      await _doFetchRooms();
    } catch (_) {}
  }

  Future<void> _doFetchRooms({String? search}) async {
    final response = await chatService.getInbox();
    final payload = response.data;
    if (payload is! Map) return;

    meParticipationUuid = payload['me']?.toString();
    if (payload['profile'] is Map) {
      myProfile = ChatPerson.fromJson(
        Map<String, dynamic>.from(payload['profile'] as Map),
      );
    }

    final raw = payload['data'];
    if (raw is! List) return;

    var parsed = raw
        .whereType<Map>()
        .map((e) => ChatRoomModel.fromJson(Map<String, dynamic>.from(e)))
        .toList();

    final q = (search ?? '').trim().toLowerCase();
    if (q.isNotEmpty) {
      parsed = parsed
          .where((r) =>
              (r.partner?.name.toLowerCase().contains(q) ?? false) ||
              (r.latestMessage?.body?.toLowerCase().contains(q) ?? false))
          .toList();
    }

    chatRooms.assignAll(parsed);
    await _subscribePersonalChannel();
  }

  // ── Messages ──────────────────────────────────────────────────────────────
  Future<void> fetchMessages(String conversationId) async {
    await handleApiClient(
      onStateChanged: (state) => messageStatus(state),
      handleApiCall: () async {
        final response = await chatService.getMessages(conversationId);
        final payload = response.data;
        if (payload is! Map) return;
        final raw = payload['data'];
        if (raw is! List) return;
        messages.assignAll(
          raw
              .whereType<Map>()
              .map((e) =>
                  MessageModel.fromJson(Map<String, dynamic>.from(e)))
              .toList(),
        );
      },
    );
  }

  Future<void> _silentRefreshMessages() async {
    final id = currentConversationId.value;
    if (id.isEmpty) return;
    try {
      final response = await chatService.getMessages(id);
      final payload = response.data;
      if (payload is! Map) return;
      final raw = payload['data'];
      if (raw is! List) return;
      final fetched = raw
          .whereType<Map>()
          .map((e) => MessageModel.fromJson(Map<String, dynamic>.from(e)))
          .toList();
      final optimistic = messages.where((m) => m.isOptimistic).toList();
      final merged = [...fetched];
      for (final opt in optimistic) {
        if (!fetched.any((c) =>
            c.body == opt.body && c.mine == opt.mine && c.id != opt.id)) {
          merged.add(opt);
        }
      }
      messages.assignAll(merged);
    } catch (_) {}
  }

  // ── Live events ───────────────────────────────────────────────────────────
  void onLiveEvent(String eventName, dynamic rawData) {
    final isChatEvent = eventName == 'chat.message.created' ||
        eventName == '.chat.message.created' ||
        eventName.endsWith('chat.message.created');
    if (!isChatEvent) return;

    try {
      dynamic data = rawData;
      if (data is String) {
        try {
          data = jsonDecode(data);
        } catch (_) {}
      }
      if (data is! Map) return;

      final conversationId = data['conversation_id']?.toString() ?? '';
      final messageRaw = data['message'];
      if (conversationId.isEmpty || messageRaw is! Map) return;

      final msgMap = Map<String, dynamic>.from(messageRaw);
      final sender = msgMap['sender']?.toString();
      final mine = sender != null &&
          meParticipationUuid != null &&
          sender == meParticipationUuid;

      final attachments = (msgMap['attachments'] is List)
          ? (msgMap['attachments'] as List)
              .whereType<Map>()
              .map((e) =>
                  ChatAttachment.fromJson(Map<String, dynamic>.from(e)))
              .toList()
          : <ChatAttachment>[];

      final newMessage = MessageModel(
        id: msgMap['id']?.toString() ?? '',
        body: msgMap['body']?.toString(),
        attachments: attachments,
        mine: mine,
        createdAt: msgMap['created_at']?.toString() ?? '',
        updatedAt: msgMap['created_at'] != null
            ? DateTime.tryParse(msgMap['created_at'].toString())
            : null,
      );

      final preview = msgMap['preview']?.toString() ?? newMessage.body ?? '';

      _touchInboxPreview(
        conversationId,
        preview,
        mine,
        newMessage.createdAt,
      );

      if (currentConversationId.value == conversationId) {
        final exists = messages.any((m) =>
            (m.id == newMessage.id && newMessage.id.isNotEmpty) ||
            (m.isOptimistic &&
                m.body == newMessage.body &&
                m.mine == newMessage.mine));
        if (!exists) {
          messages.add(newMessage);
        } else {
          final idx = messages.indexWhere((m) =>
              m.isOptimistic &&
              m.body == newMessage.body &&
              m.mine == newMessage.mine);
          if (idx != -1) messages[idx] = newMessage;
        }
        if (!mine) markRoomAsRead(conversationId);
      } else if (!mine) {
        final idx = chatRooms.indexWhere((r) => r.id == conversationId);
        if (idx == -1) {
          _silentFetchRooms();
        } else {
          chatRooms[idx] =
              chatRooms[idx].copyWith(unread: chatRooms[idx].unread + 1);
          chatRooms.refresh();
        }
      }
    } catch (_) {}
  }

  void _touchInboxPreview(
    String conversationId,
    String body,
    bool mine,
    String createdAt,
  ) {
    final idx = chatRooms.indexWhere((r) => r.id == conversationId);
    if (idx == -1) return;
    final room = chatRooms[idx];
    chatRooms[idx] = room.copyWith(
      latestMessage: MessageModel(
        body: body,
        mine: mine,
        createdAt: createdAt,
        updatedAt: DateTime.tryParse(createdAt),
      ),
    );
    // Move to top.
    final updated = chatRooms.removeAt(idx);
    chatRooms.insert(0, updated);
  }

  // ── Send ──────────────────────────────────────────────────────────────────
  Future<void> sendMessage({
    String? message,
    List<Map<String, dynamic>>? attachments,
    String? localFilePath,
    String? attachKind,
  }) async {
    final conversationId = currentConversationId.value;
    if (conversationId.isEmpty) return;

    final tempId = 'temp-${DateTime.now().millisecondsSinceEpoch}';
    final optimisticAttachments = attachments
            ?.map((a) => ChatAttachment.fromJson(a))
            .toList() ??
        (localFilePath != null && attachKind != null
            ? [
                ChatAttachment(
                  kind: attachKind,
                  url: localFilePath,
                  name: localFilePath.split(RegExp(r'[/\\]')).last,
                )
              ]
            : <ChatAttachment>[]);

    messages.add(MessageModel(
      id: tempId,
      body: message,
      attachments: optimisticAttachments,
      mine: true,
      createdAt: DateTime.now().toIso8601String(),
      updatedAt: DateTime.now(),
      localFilePath: localFilePath,
    ));

    await handleApiClient(
      onStateChanged: (state) => sendStatus(state),
      handleApiCall: () async {
        final response = await chatService.sendMessage(
          conversationId: conversationId,
          body: message,
          attachments: attachments,
        );
        final payload = response.data;
        if (payload is Map && payload['data'] is Map) {
          final server = MessageModel.fromJson(
            Map<String, dynamic>.from(payload['data'] as Map),
          );
          final idx = messages.indexWhere((m) => m.id == tempId);
          if (idx != -1) {
            messages[idx] = server;
          } else if (!messages.any((m) => m.id == server.id)) {
            messages.add(server);
          }
          _touchInboxPreview(
            conversationId,
            server.body?.isNotEmpty == true
                ? server.body!
                : (server.attachType == 'image'
                    ? '📷 Photo'
                    : server.attachType == 'video'
                        ? '🎬 Video'
                        : '📎 File'),
            true,
            server.createdAt,
          );
        }
      },
    );
  }

  Future<void> pickFile() async {
    try {
      final result = await FilePicker.pickFiles(
        type: FileType.custom,
        allowedExtensions: [
          'jpg',
          'jpeg',
          'png',
          'gif',
          'webp',
          'pdf',
          'mp4',
          'webm',
          'mov',
        ],
      );

      if (result == null || result.files.single.path == null) return;

      final pickedFile = result.files.single;
      final filePath = pickedFile.path!;
      final extension = pickedFile.extension?.toLowerCase();

      const maxBytes = 20 * 1024 * 1024;
      if (pickedFile.size > maxBytes) {
        ToastMsg.showErrorMessage(
          'File too large. Maximum allowed size is 20 MB.',
        );
        return;
      }

      String kind;
      switch (extension) {
        case 'pdf':
          kind = 'pdf';
        case 'mp4':
        case 'webm':
        case 'mov':
          kind = 'video';
        default:
          kind = 'image';
      }

      final form = dio.FormData.fromMap({
        'file': await dio.MultipartFile.fromFile(
          filePath,
          filename: pickedFile.name,
        ),
        'collection': 'chat',
      });

      final upload = await chatService.uploadAttachment(form);
      final data = upload.data;
      if (data is! Map || data['data'] is! Map) {
        ToastMsg.showErrorMessage('Upload failed.');
        return;
      }
      final fileData = Map<String, dynamic>.from(data['data'] as Map);
      final url = fileData['url']?.toString();
      if (url == null || url.isEmpty) {
        ToastMsg.showErrorMessage('Upload failed.');
        return;
      }

      await sendMessage(
        attachments: [
          {
            'kind': kind,
            'url': url,
            'name': fileData['filename']?.toString() ?? pickedFile.name,
          }
        ],
        localFilePath: filePath,
        attachKind: kind,
      );
    } catch (_) {
      ToastMsg.showErrorMessage('Could not attach file.');
    }
  }

  /// Open a conversation from a push / deep link.
  Future<void> openConversationById(
    String conversationId, {
    String? partnerName,
    String? partnerImageUrl,
    String? partnerId,
  }) async {
    if (conversationId.isEmpty) return;
    await fetchRooms();
    final room = chatRooms.firstWhereOrNull((r) => r.id == conversationId);
    enterRoom(
      conversationId,
      partnerId: partnerId ?? room?.partner?.id,
    );
  }

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
