import 'package:get/get.dart';

import '../../models/delegate_model.dart';
import '../../models/exhibitor_model.dart';
import '../../models/mappers/exhibitor_mapper.dart';
import '../../models/mappers/session_mapper.dart';
import '../../models/mappers/speaker_mapper.dart';
import '../../models/session_model.dart';
import '../../models/speaker_model.dart';
import '../../utils/enum/enums.dart';
import '../../utils/helpers/helper_functions.dart';
import '../../utils/helpers/toast_msg.dart';
import '../../utils/helpers/type_helper.dart';
import 'bookmark_service.dart';

class BookmarkController extends GetxController {
  final _service = BookmarkService();

  final dataStatus = ApiState.initial.obs;

  final RxList<SpeakerItemModel> bookmarkedSpeakers = <SpeakerItemModel>[].obs;
  final RxList<SessionModel> bookmarkedSessions = <SessionModel>[].obs;
  final RxList<ExhibitorModel> bookmarkedExhibitors = <ExhibitorModel>[].obs;
  final RxList<DelegateItemModel> bookmarkedDelegates = <DelegateItemModel>[].obs;

  /// API uuid → hashed int id, per type.
  final Map<String, Set<String>> _saved = {
    'speaker': {},
    'session': {},
    'delegate': {},
    'exhibitor': {},
  };

  final Map<String, Map<int, String>> _hashToUuid = {
    'speaker': {},
    'session': {},
    'delegate': {},
    'exhibitor': {},
  };

  /// Bumped whenever saved bookmarks change so Obx widgets rebuild.
  final revision = 0.obs;

  @override
  void onInit() {
    super.onInit();
    fetchBookmarks();
  }

  bool isOn(String type, String uuid) {
    revision.value;
    return _saved[type]?.contains(uuid) ?? false;
  }

  bool isOnHashed(String type, int hashedId) {
    revision.value;
    return _hashToUuid[type]?.containsKey(hashedId) ?? false;
  }

  String? uuidFor(String type, int hashedId) => _hashToUuid[type]?[hashedId];

  Future<void> fetchBookmarks() async {
    await handleApiClient(
      onStateChanged: (state) => dataStatus(state),
      handleApiCall: () async {
        final response = await _service.getBookmarks();
        if (response.data is! Map) return;

        final body = Map<String, dynamic>.from(response.data as Map);
        final data = body['data'] is Map
            ? Map<String, dynamic>.from(body['data'] as Map)
            : <String, dynamic>{};

        for (final type in _saved.keys) {
          final list = (data[type] as List? ?? [])
              .map((e) => e.toString())
              .where((e) => e.isNotEmpty)
              .toSet();
          _saved[type] = list;
          _hashToUuid[type] = {
            for (final id in list) TypeHelper.toInt(id): id,
          };
        }

        await Future.wait([
          _hydrateSpeakers(),
          _hydrateSessions(),
          _hydrateExhibitors(),
          _hydrateDelegates(),
        ]);
        revision.value++;
      },
    );
  }

  Future<void> _hydrateSpeakers() async {
    final ids = _saved['speaker'] ?? {};
    if (ids.isEmpty) {
      bookmarkedSpeakers.clear();
      return;
    }
    try {
      final response = await _service.getSpeakers();
      if (response.data is! Map) return;
      final body = Map<String, dynamic>.from(response.data as Map);
      final payload = body['data'] is Map
          ? Map<String, dynamic>.from(body['data'] as Map)
          : body;
      final page = SpeakerMapper.pageFromV1(payload);
      bookmarkedSpeakers.assignAll(
        page.speakers.where((s) => isOnHashed('speaker', s.id)).toList(),
      );
    } catch (_) {
      bookmarkedSpeakers.clear();
    }
  }

  Future<void> _hydrateSessions() async {
    final ids = _saved['session'] ?? {};
    if (ids.isEmpty) {
      bookmarkedSessions.clear();
      return;
    }
    try {
      final response = await _service.getSessions();
      if (response.data is! Map) return;
      final body = Map<String, dynamic>.from(response.data as Map);
      final payload = body['data'] is Map
          ? Map<String, dynamic>.from(body['data'] as Map)
          : body;
      final mapped = SessionMapper.fromV1(payload);
      final sessions = mapped.days.expand((d) => d.schedules).toList();
      bookmarkedSessions.assignAll(
        sessions.where((s) => isOnHashed('session', s.id)).toList(),
      );
    } catch (_) {
      bookmarkedSessions.clear();
    }
  }

  Future<void> _hydrateExhibitors() async {
    final ids = _saved['exhibitor'] ?? {};
    if (ids.isEmpty) {
      bookmarkedExhibitors.clear();
      return;
    }
    try {
      final response = await _service.getExhibitors();
      if (response.data is! Map) return;
      final body = Map<String, dynamic>.from(response.data as Map);
      final payload = body['data'] is Map
          ? Map<String, dynamic>.from(body['data'] as Map)
          : body;
      final page = ExhibitorMapper.pageFromV1(payload);
      bookmarkedExhibitors.assignAll(
        page.exhibitors.where((e) => isOnHashed('exhibitor', e.id)).toList(),
      );
    } catch (_) {
      bookmarkedExhibitors.clear();
    }
  }

  Future<void> _hydrateDelegates() async {
    final ids = (_saved['delegate'] ?? {}).toList();
    if (ids.isEmpty) {
      bookmarkedDelegates.clear();
      return;
    }
    try {
      final response = await _service.resolveDelegates(ids);
      if (response.data is! Map) return;
      final body = Map<String, dynamic>.from(response.data as Map);
      final list = body['data'] as List? ?? [];
      bookmarkedDelegates.assignAll(
        list.whereType<Map>().map((raw) {
          final m = Map<String, dynamic>.from(raw);
          return DelegateItemModel(
            id: TypeHelper.toInt(m['id']),
            name: m['name']?.toString() ?? '',
            image: m['avatar_url']?.toString() ?? '',
            designation: m['job_title']?.toString() ?? '',
            company: m['company']?.toString() ?? '',
            isFavorite: true,
          );
        }).toList(),
      );
    } catch (_) {
      bookmarkedDelegates.clear();
    }
  }

  Future<bool> toggle({
    required String type,
    required String uuid,
    SpeakerItemModel? speaker,
    SessionModel? session,
    ExhibitorModel? exhibitor,
    DelegateItemModel? delegate,
    bool persist = true,
  }) async {
    final on = !isOn(type, uuid);
    _applyLocal(type: type, uuid: uuid, on: on, speaker: speaker, session: session, exhibitor: exhibitor, delegate: delegate);

    if (!persist) return true;

    try {
      await _service.toggleBookmark(type: type, id: uuid, on: on);
      return true;
    } catch (_) {
      _applyLocal(type: type, uuid: uuid, on: !on, speaker: speaker, session: session, exhibitor: exhibitor, delegate: delegate);
      return false;
    }
  }

  /// Apply a toggle that was already persisted elsewhere (e.g. SessionController).
  void syncLocal({
    required String type,
    required String uuid,
    required bool on,
    SpeakerItemModel? speaker,
    SessionModel? session,
    ExhibitorModel? exhibitor,
    DelegateItemModel? delegate,
  }) {
    _applyLocal(
      type: type,
      uuid: uuid,
      on: on,
      speaker: speaker,
      session: session,
      exhibitor: exhibitor,
      delegate: delegate,
    );
  }

  void _applyLocal({
    required String type,
    required String uuid,
    required bool on,
    SpeakerItemModel? speaker,
    SessionModel? session,
    ExhibitorModel? exhibitor,
    DelegateItemModel? delegate,
  }) {
    final hashed = TypeHelper.toInt(uuid);
    if (on) {
      _saved[type]!.add(uuid);
      _hashToUuid[type]![hashed] = uuid;
      if (speaker != null && !bookmarkedSpeakers.any((s) => s.id == hashed)) {
        bookmarkedSpeakers.add(speaker);
      }
      if (session != null && !bookmarkedSessions.any((s) => s.id == hashed)) {
        bookmarkedSessions.add(session);
      }
      if (exhibitor != null && !bookmarkedExhibitors.any((e) => e.id == hashed)) {
        bookmarkedExhibitors.add(exhibitor);
      }
      if (delegate != null && !bookmarkedDelegates.any((d) => d.id == hashed)) {
        bookmarkedDelegates.add(delegate);
      }
    } else {
      _saved[type]!.remove(uuid);
      _hashToUuid[type]!.remove(hashed);
      bookmarkedSpeakers.removeWhere((s) => s.id == hashed);
      bookmarkedSessions.removeWhere((s) => s.id == hashed);
      bookmarkedExhibitors.removeWhere((e) => e.id == hashed);
      bookmarkedDelegates.removeWhere((d) => d.id == hashed);
    }
    revision.value++;
  }

  void toggleSpeakerBookmark(SpeakerItemModel speaker) {
    final uuid = uuidFor('speaker', speaker.id);
    if (uuid == null) {
      bookmarkedSpeakers.removeWhere((s) => s.id == speaker.id);
      return;
    }
    toggle(type: 'speaker', uuid: uuid, speaker: speaker);
  }

  Future<bool> toggleSessionBookmark(SessionModel session) async {
    final uuid = session.uuid.isNotEmpty
        ? session.uuid
        : uuidFor('session', session.id);
    if (uuid == null || uuid.isEmpty) {
      bookmarkedSessions.removeWhere((s) => s.id == session.id);
      return false;
    }
    final wasBookmarked = isOn('session', uuid);
    final success = await toggle(type: 'session', uuid: uuid, session: session);
    if (success) {
      ToastMsg.showSuccessMessage(
        wasBookmarked
            ? 'Removed from your bookmarks.'
            : 'Added to your bookmarks.',
      );
    } else {
      ToastMsg.showErrorMessage('Could not update your bookmark.');
    }
    return success;
  }

  void toggleExhibitorBookmark(ExhibitorModel exhibitor) {
    final uuid = uuidFor('exhibitor', exhibitor.id) ??
        (exhibitor.slug.isNotEmpty ? exhibitor.slug : null);
    if (uuid == null) {
      bookmarkedExhibitors.removeWhere((e) => e.id == exhibitor.id);
      return;
    }
    toggle(type: 'exhibitor', uuid: uuid, exhibitor: exhibitor);
  }

  Future<void> toggleDelegateBookmark(DelegateItemModel delegate) async {
    final uuid = uuidFor('delegate', delegate.id);
    if (uuid == null) {
      bookmarkedDelegates.removeWhere((d) => d.id == delegate.id);
      return;
    }
    final wasBookmarked = isOn('delegate', uuid);
    final name = delegate.name.isNotEmpty ? delegate.name : 'Delegate';
    final success = await toggle(
      type: 'delegate',
      uuid: uuid,
      delegate: delegate,
    );
    if (success) {
      ToastMsg.showSuccessMessage(
        wasBookmarked
            ? '$name removed from bookmarks'
            : '$name bookmarked',
      );
    } else {
      ToastMsg.showErrorMessage('Unable to update bookmark.');
    }
  }
}
