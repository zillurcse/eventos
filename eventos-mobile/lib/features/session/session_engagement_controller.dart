import 'dart:async';

import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../models/session_engagement_models.dart';
import 'session_service.dart';

enum SessionEngagementTab { chat, qa, polls, attendees }

class SessionEngagementController extends GetxController {
  SessionEngagementController({
    required this.sessionUuid,
    required this.enabledTabs,
  });

  final String sessionUuid;
  final List<SessionEngagementTab> enabledTabs;
  final _service = SessionService();

  final activeTab = SessionEngagementTab.chat.obs;
  final loading = false.obs;
  /// True after the first non-silent fetch for the current tab finishes.
  final hasLoadedOnce = false.obs;
  final sending = false.obs;

  final chat = <SessionPanelMessage>[].obs;
  final questions = <SessionPanelMessage>[].obs;
  final polls = <SessionPoll>[].obs;
  final attendees = <SessionPanelAttendee>[].obs;
  final attendeeOnline = 0.obs;
  final attendeeTotal = 0.obs;
  final attendeeSearch = ''.obs;

  final isMuted = false.obs;
  final canChat = true.obs;
  final canAsk = true.obs;
  final canVotePoll = true.obs;

  final chatInput = TextEditingController();
  final qaInput = TextEditingController();

  Timer? _pollTimer;

  @override
  void onInit() {
    super.onInit();
    if (enabledTabs.isNotEmpty) {
      activeTab.value = enabledTabs.first;
    }
    refreshActiveTab();
    _restartPolling();
  }

  @override
  void onClose() {
    _pollTimer?.cancel();
    chatInput.dispose();
    qaInput.dispose();
    super.onClose();
  }

  void selectTab(SessionEngagementTab tab) {
    if (!enabledTabs.contains(tab)) return;
    activeTab.value = tab;
    hasLoadedOnce.value = false;
    refreshActiveTab();
    _restartPolling();
  }

  void _restartPolling() {
    _pollTimer?.cancel();
    final interval = activeTab.value == SessionEngagementTab.attendees
        ? const Duration(seconds: 15)
        : const Duration(seconds: 5);
    _pollTimer = Timer.periodic(interval, (_) => refreshActiveTab(silent: true));
  }

  void _absorbMeta(Map<String, dynamic>? meta) {
    if (meta == null) return;
    if (meta.containsKey('is_muted')) isMuted.value = meta['is_muted'] == true;
    if (meta.containsKey('can_chat')) canChat.value = meta['can_chat'] == true;
    if (meta.containsKey('can_ask')) canAsk.value = meta['can_ask'] == true;
    if (meta.containsKey('can_vote_poll')) {
      canVotePoll.value = meta['can_vote_poll'] == true;
    }
    if (meta.containsKey('online')) {
      attendeeOnline.value = _toInt(meta['online']);
    }
    if (meta.containsKey('total')) {
      attendeeTotal.value = _toInt(meta['total']);
    }
  }

  Map<String, dynamic>? _metaOf(dynamic body) {
    if (body is! Map) return null;
    final meta = body['meta'];
    if (meta is Map) return Map<String, dynamic>.from(meta);
    return null;
  }

  List<Map<String, dynamic>> _dataList(dynamic body) {
    if (body is! Map) return const [];
    final data = body['data'];
    if (data is List) {
      return data
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList();
    }
    return const [];
  }

  Future<void> refreshActiveTab({bool silent = false}) async {
    if (sessionUuid.isEmpty || enabledTabs.isEmpty) return;
    if (!silent) loading.value = true;
    try {
      switch (activeTab.value) {
        case SessionEngagementTab.chat:
          await _loadChat();
        case SessionEngagementTab.qa:
          await _loadQuestions();
        case SessionEngagementTab.polls:
          await _loadPolls();
        case SessionEngagementTab.attendees:
          await _loadAttendees();
      }
      hasLoadedOnce.value = true;
    } catch (_) {
      // Keep last good data on poll failures.
    } finally {
      if (!silent) loading.value = false;
    }
  }

  Future<void> _loadChat() async {
    final res = await _service.getChat(sessionUuid);
    chat.assignAll(
      _dataList(res.data).map(SessionPanelMessage.fromJson),
    );
    _absorbMeta(_metaOf(res.data));
  }

  Future<void> _loadQuestions() async {
    final res = await _service.getQuestions(sessionUuid);
    questions.assignAll(
      _dataList(res.data).map(SessionPanelMessage.fromJson),
    );
    _absorbMeta(_metaOf(res.data));
  }

  Future<void> _loadPolls() async {
    final res = await _service.getPolls(sessionUuid);
    polls.assignAll(_dataList(res.data).map(SessionPoll.fromJson));
    _absorbMeta(_metaOf(res.data));
  }

  Future<void> _loadAttendees() async {
    final res = await _service.getAttendees(sessionUuid);
    attendees.assignAll(
      _dataList(res.data).map(SessionPanelAttendee.fromJson),
    );
    _absorbMeta(_metaOf(res.data));
  }

  Future<void> sendChat() async {
    final body = chatInput.text.trim();
    if (body.isEmpty || isMuted.value || !canChat.value) return;
    sending.value = true;
    try {
      final res = await _service.sendChat(sessionUuid, body);
      final data = res.data is Map && res.data['data'] is Map
          ? Map<String, dynamic>.from(res.data['data'] as Map)
          : (res.data is Map
              ? Map<String, dynamic>.from(res.data as Map)
              : null);
      if (data != null) {
        chat.add(SessionPanelMessage.fromJson(data));
      } else {
        await _loadChat();
      }
      chatInput.clear();
    } catch (_) {
      Get.snackbar('Chat', 'Could not send message.',
          snackPosition: SnackPosition.BOTTOM);
    } finally {
      sending.value = false;
    }
  }

  Future<void> askQuestion() async {
    final body = qaInput.text.trim();
    if (body.isEmpty || !canAsk.value) return;
    sending.value = true;
    try {
      await _service.askQuestion(sessionUuid, body);
      qaInput.clear();
      await _loadQuestions();
    } catch (_) {
      Get.snackbar('Q&A', 'Could not submit question.',
          snackPosition: SnackPosition.BOTTOM);
    } finally {
      sending.value = false;
    }
  }

  Future<void> upvoteQuestion(int messageId) async {
    try {
      await _service.upvoteQuestion(sessionUuid, messageId);
      await _loadQuestions();
    } catch (_) {}
  }

  Future<void> votePoll(int pollId, String optionId) async {
    if (!canVotePoll.value) return;
    try {
      final res = await _service.votePoll(sessionUuid, pollId, optionId);
      final list = _dataList(res.data);
      if (list.isNotEmpty) {
        polls.assignAll(list.map(SessionPoll.fromJson));
      } else {
        await _loadPolls();
      }
      _absorbMeta(_metaOf(res.data));
    } catch (_) {
      Get.snackbar('Polls', 'Could not submit vote.',
          snackPosition: SnackPosition.BOTTOM);
    }
  }

  List<SessionPanelAttendee> get filteredAttendees {
    final q = attendeeSearch.value.trim().toLowerCase();
    if (q.isEmpty) return attendees;
    return attendees
        .where((a) =>
            a.name.toLowerCase().contains(q) ||
            (a.headline ?? '').toLowerCase().contains(q))
        .toList();
  }

  static int _toInt(dynamic v) {
    if (v is int) return v;
    if (v is num) return v.toInt();
    return int.tryParse(v?.toString() ?? '') ?? 0;
  }
}
