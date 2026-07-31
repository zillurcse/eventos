import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../models/delegate_model.dart';
import '../../models/meeting_model.dart';
import '../../models/mappers/delegate_mapper.dart';
import '../../utils/config/app_config.dart';
import '../../utils/enum/enums.dart';
import '../../utils/helpers/helper_functions.dart';
import '../../utils/helpers/toast_msg.dart';
import '../../utils/helpers/type_helper.dart';
import '../bookmarks/bookmark_controller.dart';
import '../chat/chat_controller.dart';
import '../meetings/meetings_controller.dart';
import 'delegate_service.dart';

class DelegateController extends GetxController {
  final _service = DelegateService();

  // ── List state ────────────────────────────────────────────────────────────
  final dataStatus = ApiState.initial.obs;
  final Rx<DelegatePageModel> delegatePage = const DelegatePageModel().obs;
  final RxnInt expandedDelegateId = RxnInt();
  final adImageUrl = ''.obs;

  // Search & Sort state
  final RxString searchKey = "".obs;
  final TextEditingController searchController = TextEditingController();
  final RxnString sortType = RxnString();

  // Client-side filters (mirrors web Advance Filter)
  final savedOnly = false.obs;
  final activeCompanies = <String>[].obs;
  final activeTitles = <String>[].obs;

  /// Hashed int id → participation uuid (for detail / bookmark calls).
  final Map<int, String> _idToUuid = {};

  List<DelegateItemModel> get delegates => delegatePage.value.delegates;
  DelegateAdModel? get contentAd => delegatePage.value.contentAd;
  DelegateAdModel? get featuredAd => delegatePage.value.featuredAd;

  bool get hasActiveFilters =>
      savedOnly.value ||
      activeCompanies.isNotEmpty ||
      activeTitles.isNotEmpty;

  List<String> get companyOptions => _uniqueTop(
        delegates.map((d) => d.company),
        12,
      );

  List<String> get titleOptions => _uniqueTop(
        delegates.map((d) => d.designation),
        12,
      );

  List<DelegateItemModel> get filteredDelegates {
    final saved = savedOnly.value;
    final companies = activeCompanies.toList();
    final titles = activeTitles.toList();

    if (saved && Get.isRegistered<BookmarkController>()) {
      // Touch Rx so Obx rebuilds when bookmarks change.
      Get.find<BookmarkController>().bookmarkedDelegates.length;
    }

    return delegates.where((d) {
      if (saved) {
        final uuid = _idToUuid[d.id];
        if (uuid == null ||
            !Get.isRegistered<BookmarkController>() ||
            !Get.find<BookmarkController>().isOn('delegate', uuid)) {
          return false;
        }
      }
      if (companies.isNotEmpty && !companies.contains(d.company)) {
        return false;
      }
      if (titles.isNotEmpty && !titles.contains(d.designation)) {
        return false;
      }
      return true;
    }).toList();
  }

  static List<String> _uniqueTop(Iterable<String> values, int limit) {
    final seen = <String, int>{};
    for (final v in values) {
      final t = v.trim();
      if (t.isEmpty) continue;
      seen[t] = (seen[t] ?? 0) + 1;
    }
    final entries = seen.entries.toList()
      ..sort((a, b) => b.value.compareTo(a.value));
    return entries.take(limit).map((e) => e.key).toList();
  }

  void toggleSavedOnly() => savedOnly.value = !savedOnly.value;

  void toggleCompanyFilter(String company) {
    if (activeCompanies.contains(company)) {
      activeCompanies.remove(company);
    } else {
      activeCompanies.add(company);
    }
  }

  void toggleTitleFilter(String title) {
    if (activeTitles.contains(title)) {
      activeTitles.remove(title);
    } else {
      activeTitles.add(title);
    }
  }

  void clearFilters() {
    savedOnly.value = false;
    activeCompanies.clear();
    activeTitles.clear();
  }

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

  String? uuidFor(int hashedId) => _idToUuid[hashedId];

  Set<String> _currentFavoritedUuids() {
    if (!Get.isRegistered<BookmarkController>()) return {};
    final bookmarkCtrl = Get.find<BookmarkController>();
    return {
      for (final uuid in _idToUuid.values)
        if (bookmarkCtrl.isOn('delegate', uuid)) uuid,
    };
  }

  // ── API: list ─────────────────────────────────────────────────────────────
  Future<void> fetchDelegates() async {
    await handleApiClient(
      onStateChanged: (state) => dataStatus(state),
      handleApiCall: () async {
        final response = await _service.getDelegates(
          q: searchKey.value,
          sort: DelegateMapper.apiSort(sortType.value),
        );
        if (response.data is! Map) return;

        final body = Map<String, dynamic>.from(response.data as Map);
        final rows = body['data'] as List? ?? [];
        final meta = body['meta'] is Map
            ? Map<String, dynamic>.from(body['meta'] as Map)
            : <String, dynamic>{};

        _idToUuid
          ..clear()
          ..addEntries(
            rows.whereType<Map>().map((raw) {
              final m = Map<String, dynamic>.from(raw);
              final uuid = (m['id'] ?? '').toString();
              return MapEntry(TypeHelper.toInt(uuid), uuid);
            }).where((e) => e.value.isNotEmpty),
          );

        delegatePage.value = DelegateMapper.pageFromV1(
          rows,
          currentPage: TypeHelper.toInt(meta['page'] ?? 1),
          favoritedIds: _currentFavoritedUuids(),
        );

        // Ads are optional — don't fail the directory if they error.
        _fetchAds();
      },
    );
  }

  Future<void> _fetchAds() async {
    try {
      final response = await _service.getAds();
      final body = response.data;
      if (body is! Map) return;
      final data = body['data'];
      if (data is! Map) return;
      final strip = data['strip'];
      if (strip is! List || strip.isEmpty) return;
      final first = strip.first;
      if (first is! Map) return;
      final url = (first['image_url'] ?? first['image'] ?? first['url'] ?? '')
          .toString();
      final resolved = AppConfig.resolveMediaUrl(url);
      if (resolved.isNotEmpty) adImageUrl.value = resolved;
    } catch (_) {
      // Ads are optional.
    }
  }

  // ── API: single delegate detail ───────────────────────────────────────────
  Future<void> fetchDelegateDetail(int id) async {
    delegateDetail.value = null;
    await handleApiClient(
      onStateChanged: (state) => detailStatus(state),
      handleApiCall: () async {
        final uuid = _idToUuid[id];
        if (uuid == null || uuid.isEmpty) {
          throw Exception('Delegate not found');
        }

        final response = await _service.getDelegateDetail(uuid);
        if (response.data is! Map) return;

        final raw = Map<String, dynamic>.from(response.data as Map);
        final data = raw['data'];
        if (data is! Map) return;

        final isFavorite = Get.isRegistered<BookmarkController>() &&
            Get.find<BookmarkController>().isOn('delegate', uuid);

        delegateDetail.value = DelegateMapper.detailFromV1(
          Map<String, dynamic>.from(data),
          isFavorite: isFavorite,
        );
      },
    );
  }

  // ── API: toggle delegate bookmark ─────────────────────────────────────────
  Future<bool> toggleBookmark(int delegateId) async {
    final uuid = _idToUuid[delegateId];
    if (uuid == null || uuid.isEmpty) {
      ToastMsg.showErrorMessage('Unable to bookmark this delegate.');
      return false;
    }
    if (!Get.isRegistered<BookmarkController>()) {
      ToastMsg.showErrorMessage('Unable to bookmark this delegate.');
      return false;
    }

    final bookmarkCtrl = Get.find<BookmarkController>();
    final wasBookmarked = bookmarkCtrl.isOn('delegate', uuid);
    var delegate = delegates.firstWhereOrNull((d) => d.id == delegateId);
    if (delegate == null && delegateDetail.value?.id == delegateId) {
      final d = delegateDetail.value!;
      delegate = DelegateItemModel(
        id: d.id,
        name: d.name,
        image: d.image,
        designation: d.designation,
        company: d.company,
        country: d.country,
        isFavorite: d.isFavorite,
      );
    }
    final name = (delegate?.name.isNotEmpty == true)
        ? delegate!.name
        : 'Delegate';

    final success = await bookmarkCtrl.toggle(
      type: 'delegate',
      uuid: uuid,
      delegate: delegate,
    );

    if (success) {
      final favorited = _currentFavoritedUuids();
      delegatePage.value = DelegatePageModel(
        currentPage: delegatePage.value.currentPage,
        delegates: delegates
            .map(
              (d) => DelegateItemModel(
                id: d.id,
                name: d.name,
                image: d.image,
                designation: d.designation,
                company: d.company,
                country: d.country,
                isFavorite: favorited.contains(_idToUuid[d.id]),
              ),
            )
            .toList(),
        nextPageUrl: delegatePage.value.nextPageUrl,
        featuredAd: delegatePage.value.featuredAd,
        contentAd: delegatePage.value.contentAd,
      );

      if (delegateDetail.value?.id == delegateId) {
        final detail = delegateDetail.value!;
        delegateDetail.value = DelegateDetailModel(
          id: detail.id,
          name: detail.name,
          firstName: detail.firstName,
          lastName: detail.lastName,
          image: detail.image,
          designation: detail.designation,
          company: detail.company,
          country: detail.country,
          about: detail.about,
          industry: detail.industry,
          interests: detail.interests,
          gender: detail.gender,
          state: detail.state,
          cityTown: detail.cityTown,
          address: detail.address,
          website: detail.website,
          email: detail.email,
          mobileNumber: detail.mobileNumber,
          timezone: detail.timezone,
          isFavorite: bookmarkCtrl.isOn('delegate', uuid),
        );
      }

      ToastMsg.showSuccessMessage(
        wasBookmarked
            ? '$name removed from bookmarks'
            : '$name bookmarked',
      );
    } else {
      ToastMsg.showErrorMessage('Unable to update bookmark.');
    }
    return success;
  }

  Future<void> startChat(
    int delegateId, {
    String? name,
    String? imageUrl,
  }) async {
    final uuid = _idToUuid[delegateId];
    if (uuid == null || uuid.isEmpty) {
      ToastMsg.showErrorMessage('Unable to start chat.');
      return;
    }
    if (!Get.isRegistered<ChatController>()) {
      ToastMsg.showErrorMessage('Chat is not available.');
      return;
    }
    await Get.find<ChatController>().startChatWith(
      uuid,
      name: name,
      imageUrl: imageUrl,
    );
  }

  Future<void> requestMeet(
    int delegateId, {
    String? name,
    String? designation,
    String? company,
    String? imageUrl,
  }) async {
    final uuid = _idToUuid[delegateId];
    if (uuid == null || uuid.isEmpty) {
      ToastMsg.showErrorMessage('Unable to request meeting.');
      return;
    }
    if (!Get.isRegistered<MeetingsController>()) {
      ToastMsg.showErrorMessage('Meetings are not available.');
      return;
    }
    final partner = MeetingPartner(
      id: uuid,
      name: name ?? '',
      role: 'attendee',
      company: company ?? '',
      jobTitle: designation ?? '',
      avatarUrl: (imageUrl == null || imageUrl.isEmpty) ? null : imageUrl,
    );
    await Get.find<MeetingsController>().requestMeetingWith(partner);
  }

  @override
  void onClose() {
    searchController.dispose();
    super.onClose();
  }
}
