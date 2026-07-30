import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../models/exhibitor_models.dart';
import '../../utils/enum/enums.dart';
import '../../utils/helpers/helper_functions.dart';
import '../bookmarks/bookmark_controller.dart';
import 'exhibitor_service.dart';

class ExhibitorController extends GetxController {
  final _service = ExhibitorService();

  // ── List state ────────────────────────────────────────────────────────────
  final dataStatus = ApiState.initial.obs;
  final Rx<ExhibitorPageModel> exhibitorPage = const ExhibitorPageModel().obs;
  final RxnString selectedType = RxnString();
  final RxnInt expandedExhibitorId = RxnInt();

  // Search state
  final RxString searchKey = "".obs;
  final TextEditingController searchController = TextEditingController();

  List<ExhibitorModel> get exhibitors => exhibitorPage.value.exhibitors;
  ExhibitorAdModel? get contentAd => exhibitorPage.value.contentAd;
  ExhibitorAdModel? get featuredAd => exhibitorPage.value.featuredAd;

  @override
  void onInit() {
    super.onInit();
    debounce(
      searchKey,
      (_) => fetchExhibitors(),
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

  // ── API: list ─────────────────────────────────────────────────────────────
  Future<void> fetchExhibitors() async {
    await handleApiClient(
      onStateChanged: (state) => dataStatus(state),
      handleApiCall: () async {
        final response = await _service.getExhibitors(
          type: selectedType.value,
          s: searchKey.value,
        );
        if (response.data is Map) {
          exhibitorPage.value = ExhibitorPageModel.fromJson(
            Map<String, dynamic>.from(response.data as Map),
          );
        }
      },
    );
  }

  void setType(String? type) {
    selectedType.value = type;
    fetchExhibitors();
  }

  // ── Detail state ──────────────────────────────────────────────────────────
  final detailStatus = ApiState.initial.obs;
  final Rxn<ExhibitorModel> exhibitorDetail = Rxn<ExhibitorModel>();

  // ── API: details ──────────────────────────────────────────────────────────
  Future<void> fetchExhibitorDetail(String slug) async {
    await handleApiClient(
      onStateChanged: (state) => detailStatus(state),
      handleApiCall: () async {
        final response = await _service.getExhibitorDetails(slug);
        if (response.data is Map) {
          final data = Map<String, dynamic>.from(response.data as Map);
          if (data['exhibitor'] is Map) {
            exhibitorDetail.value = ExhibitorModel.fromJson(
              Map<String, dynamic>.from(data['exhibitor'] as Map),
            );
          }
        }
      },
    );
  }

  // ── API: toggle exhibitor bookmark ────────────────────────────────────────
  Future<bool> toggleBookmark(int exhibitorId) async {
    bool success = false;
    await handleApiClient(
      onStateChanged: (state) {},
      handleApiCall: () async {
        final response = await _service.toggleExhibitorBookmark(exhibitorId);
        if (response.data is Map) {
          final raw = Map<String, dynamic>.from(response.data as Map);
          if (raw['status'] == 'success' || raw['status'] == 1 || raw['success'] == true) {
            success = true;
            await fetchExhibitors();
            if (exhibitorDetail.value?.id == exhibitorId) {
              await fetchExhibitorDetail(exhibitorDetail.value!.slug);
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
