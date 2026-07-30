import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../models/speaker_model.dart';
import '../../models/session_model.dart';
import '../../models/mappers/speaker_mapper.dart';
import '../../utils/enum/enums.dart';
import '../../utils/helpers/helper_functions.dart';
import '../../utils/helpers/type_helper.dart';
import '../bookmarks/bookmark_controller.dart';
import 'speaker_service.dart';

class SpeakerController extends GetxController {
  final _service = SpeakerService();

  final dataStatus = ApiState.initial.obs;
  final Rx<SpeakerPageModel> speakerPage = const SpeakerPageModel().obs;
  final RxnInt expandedSpeakerId = RxnInt();

  final RxString searchKey = "".obs;
  final TextEditingController searchController = TextEditingController();
  final RxnString sortType = RxnString();

  List<Map<String, dynamic>> _allRawSpeakers = [];
  List<Map<String, dynamic>> _allRawSessions = [];

  List<SpeakerItemModel> get speakers => speakerPage.value.speakers;
  SpeakerAdModel? get contentAd => speakerPage.value.contentAd;
  SpeakerAdModel? get featuredAd => speakerPage.value.featuredAd;

  @override
  void onInit() {
    super.onInit();
    debounce(
      searchKey,
      (_) => _applyFilters(),
      time: const Duration(milliseconds: 500),
    );
  }

  void setSearchKey(String val) {
    searchKey.value = val;
  }

  void clearSearch() {
    searchController.clear();
    searchKey.value = "";
  }

  void setSortType(String? type) {
    sortType.value = type;
    _applyFilters();
  }

  final detailStatus = ApiState.initial.obs;
  final Rx<SpeakerDetailModel?> speakerDetail = Rx<SpeakerDetailModel?>(null);
  final RxList<SessionModel> speakerSessions = <SessionModel>[].obs;

  Future<void> fetchSpeakers() async {
    await handleApiClient(
      onStateChanged: (state) => dataStatus(state),
      handleApiCall: () async {
        final response = await _service.getSpeakers();
        final body = response.data;
        if (body is! Map) return;

        final payload = body['data'] is Map
            ? Map<String, dynamic>.from(body['data'] as Map)
            : Map<String, dynamic>.from(body);

        _allRawSpeakers = (payload['speakers'] as List? ?? [])
            .whereType<Map>()
            .map((e) => Map<String, dynamic>.from(e))
            .toList();

        _applyFilters();
      },
    );
  }

  void _applyFilters() {
    final filtered = SpeakerMapper.filterRaw(
      speakers: _allRawSpeakers,
      search: searchKey.value,
      sortBy: sortType.value,
    );
    speakerPage.value = SpeakerMapper.pageFromV1({'speakers': filtered});
  }

  Future<void> fetchSpeakerDetail(int id) async {
    speakerDetail.value = null;
    speakerSessions.clear();
    await handleApiClient(
      onStateChanged: (state) => detailStatus(state),
      handleApiCall: () async {
        if (_allRawSpeakers.isEmpty) {
          final response = await _service.getSpeakers();
          final body = response.data;
          if (body is Map) {
            final payload = body['data'] is Map
                ? Map<String, dynamic>.from(body['data'] as Map)
                : Map<String, dynamic>.from(body);
            _allRawSpeakers = (payload['speakers'] as List? ?? [])
                .whereType<Map>()
                .map((e) => Map<String, dynamic>.from(e))
                .toList();
          }
        }

        Map<String, dynamic>? raw;
        for (final s in _allRawSpeakers) {
          if (TypeHelper.toInt(s['id']) == id) {
            raw = s;
            break;
          }
        }
        if (raw == null) {
          throw Exception('Speaker not found');
        }

        if (_allRawSessions.isEmpty) {
          try {
            final sessionsResponse = await _service.getSessions();
            final body = sessionsResponse.data;
            if (body is Map) {
              final payload = body['data'] is Map
                  ? Map<String, dynamic>.from(body['data'] as Map)
                  : Map<String, dynamic>.from(body);
              final sessionsRaw = payload['sessions'];
              final list = sessionsRaw is Map && sessionsRaw['data'] is List
                  ? sessionsRaw['data'] as List
                  : (sessionsRaw as List? ?? []);
              _allRawSessions = list
                  .whereType<Map>()
                  .map((e) => Map<String, dynamic>.from(e))
                  .toList();
            }
          } catch (_) {}
        }

        final sessions = SpeakerMapper.sessionsForSpeaker(
          allSessions: _allRawSessions,
          speakerId: id,
        );

        final detail = SpeakerMapper.detailFromV1(raw, sessions: sessions);
        speakerDetail.value = detail;
        speakerSessions.assignAll(sessions);
      },
    );
  }

  Future<bool> addOrUpdateSpeakerNote(int speakerId, String noteText) async {
    return false;
  }

  Future<bool> toggleBookmark(int speakerId) async {
    if (Get.isRegistered<BookmarkController>()) {
      // Phase 2
    }
    return false;
  }
}
