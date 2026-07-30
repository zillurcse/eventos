import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../models/delegate_model.dart';
import '../../utils/enum/enums.dart';
import '../../utils/helpers/helper_functions.dart';
import '../bookmarks/bookmark_controller.dart';
import 'delegate_service.dart';

class DelegateController extends GetxController {
  final _service = DelegateService();

  // ── List state ────────────────────────────────────────────────────────────
  final dataStatus = ApiState.initial.obs;
  final Rx<DelegatePageModel> delegatePage = const DelegatePageModel().obs;
  final RxnInt expandedDelegateId = RxnInt();

  // Search & Sort state
  final RxString searchKey = "".obs;
  final TextEditingController searchController = TextEditingController();
  final RxnString sortType = RxnString();

  List<DelegateItemModel> get delegates => delegatePage.value.delegates;
  DelegateAdModel? get contentAd => delegatePage.value.contentAd;
  DelegateAdModel? get featuredAd => delegatePage.value.featuredAd;

  @override
  void onInit() {
    super.onInit();
    debounce(
      searchKey,
      (_) => fetchDelegates(),
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
    fetchDelegates();
  }

  // ── Detail state ──────────────────────────────────────────────────────────
  final detailStatus = ApiState.initial.obs;
  final Rx<DelegateDetailModel?> delegateDetail =
      Rx<DelegateDetailModel?>(null);

  // ── API: list ─────────────────────────────────────────────────────────────
  Future<void> fetchDelegates() async {
    await handleApiClient(
      onStateChanged: (state) => dataStatus(state),
      handleApiCall: () async {
        final response = await _service.getDelegates(
          s: searchKey.value,
          sortBy: sortType.value,
        );
        if (response.data is Map) {
          delegatePage.value = DelegatePageModel.fromJson(
            Map<String, dynamic>.from(response.data as Map),
          );
        }
      },
    );
  }

  // ── API: single delegate detail ───────────────────────────────────────────
  Future<void> fetchDelegateDetail(int id) async {
    delegateDetail.value = null;
    await handleApiClient(
      onStateChanged: (state) => detailStatus(state),
      handleApiCall: () async {
        final response = await _service.getDelegateDetail(id);
        if (response.data is Map) {
          final raw = Map<String, dynamic>.from(response.data as Map);
          final data = raw['data'];
          if (data is Map) {
            delegateDetail.value = DelegateDetailModel.fromJson(
              Map<String, dynamic>.from(data),
            );
          }
        }
      },
    );
  }

  // ── API: toggle delegate bookmark ─────────────────────────────────────────
  Future<bool> toggleBookmark(int delegateId) async {
    bool success = false;
    await handleApiClient(
      onStateChanged: (state) {},
      handleApiCall: () async {
        final response = await _service.toggleDelegateBookmark(delegateId);
        if (response.data is Map) {
          final raw = Map<String, dynamic>.from(response.data as Map);
          if (raw['status'] == 'success' || raw['status'] == 1 || raw['success'] == true) {
            success = true;
            await fetchDelegates();
            if (delegateDetail.value?.id == delegateId) {
              await fetchDelegateDetail(delegateId);
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
