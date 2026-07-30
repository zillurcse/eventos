import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../models/speaker_model.dart';
import '../../models/session_model.dart';
import '../../utils/enum/enums.dart';
import '../../utils/helpers/helper_functions.dart';
import '../bookmarks/bookmark_controller.dart';
import 'speaker_service.dart';

class SpeakerController extends GetxController {
  final _service = SpeakerService();

  // ── List state ────────────────────────────────────────────────────────────
  final dataStatus = ApiState.initial.obs;
  final Rx<SpeakerPageModel> speakerPage = const SpeakerPageModel().obs;
  final RxnInt expandedSpeakerId = RxnInt();

  // Search & Sort state
  final RxString searchKey = "".obs;
  final TextEditingController searchController = TextEditingController();
  final RxnString sortType = RxnString();

  List<SpeakerItemModel> get speakers => speakerPage.value.speakers;
  SpeakerAdModel? get contentAd => speakerPage.value.contentAd;
  SpeakerAdModel? get featuredAd => speakerPage.value.featuredAd;

  @override
  void onInit() {
    super.onInit();
    debounce(
      searchKey,
      (_) => fetchSpeakers(),
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
    fetchSpeakers();
  }

  // ── Detail state ──────────────────────────────────────────────────────────
  final detailStatus = ApiState.initial.obs;
  final Rx<SpeakerDetailModel?> speakerDetail = Rx<SpeakerDetailModel?>(null);
  final RxList<SessionModel> speakerSessions = <SessionModel>[].obs;

  // ── API: list ─────────────────────────────────────────────────────────────
  Future<void> fetchSpeakers() async {
    await handleApiClient(
      onStateChanged: (state) => dataStatus(state),
      handleApiCall: () async {
        final response = await _service.getSpeakers(
          s: searchKey.value,
          sortBy: sortType.value,
        );
        if (response.data is Map) {
          speakerPage.value = SpeakerPageModel.fromJson(
            Map<String, dynamic>.from(response.data as Map),
          );
        }
      },
    );
  }

  // ── API: single speaker detail ────────────────────────────────────────────
  Future<void> fetchSpeakerDetail(int id) async {
    speakerDetail.value = null;
    speakerSessions.clear();
    await handleApiClient(
      onStateChanged: (state) => detailStatus(state),
      handleApiCall: () async {
        final response = await _service.getSpeakerDetail(id);
        if (response.data is Map) {
          final raw = Map<String, dynamic>.from(response.data as Map);
          final data = raw['data'];
          if (data is Map) {
            final parsedDetail = SpeakerDetailModel.fromJson(
              Map<String, dynamic>.from(data),
            );
            speakerDetail.value = parsedDetail;
            speakerSessions.assignAll(parsedDetail.sessions);
          }
        }
      },
    );
  }

  // ── API: add/update speaker note ──────────────────────────────────────────
  Future<bool> addOrUpdateSpeakerNote(int speakerId, String noteText) async {
    bool success = false;
    await handleApiClient(
      onStateChanged: (state) {
        // No explicit detailStatus state change needed as we refresh details below
      },
      handleApiCall: () async {
        final response = await _service.addOrUpdateSpeakerNote(speakerId, noteText);
        if (response.data is Map) {
          final raw = Map<String, dynamic>.from(response.data as Map);
          if (raw['status'] == 'success') {
            success = true;
            await fetchSpeakerDetail(speakerId);
            await fetchSpeakers();
          }
        }
      },
    );
    return success;
  }

  // ── API: toggle speaker bookmark ──────────────────────────────────────────
  Future<bool> toggleBookmark(int speakerId) async {
    bool success = false;
    await handleApiClient(
      onStateChanged: (state) {},
      handleApiCall: () async {
        final response = await _service.toggleSpeakerBookmark(speakerId);
        if (response.data is Map) {
          final raw = Map<String, dynamic>.from(response.data as Map);
          if (raw['status'] == 'success' || raw['status'] == 1 || raw['success'] == true) {
            success = true;
            await fetchSpeakers();
            if (speakerDetail.value?.id == speakerId) {
              await fetchSpeakerDetail(speakerId);
            }
            if (Get.isRegistered<BookmarkController>()) {
              Get.find<BookmarkController>().fetchBookmarks();
            }
          }
        }
      },
    );
    return success;
  }
}