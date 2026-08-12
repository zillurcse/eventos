import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../models/exhibitor_models.dart';
import '../../models/mappers/exhibitor_mapper.dart';
import '../../utils/enum/enums.dart';
import '../../utils/helpers/helper_functions.dart';
import '../../utils/service/engagement_service.dart';
import '../bookmarks/bookmark_controller.dart';
import 'exhibitor_service.dart';

class ExhibitorController extends GetxController {
  final _service = ExhibitorService();

  final dataStatus = ApiState.initial.obs;
  final Rx<ExhibitorPageModel> exhibitorPage = const ExhibitorPageModel().obs;
  final RxnString selectedType = RxnString();
  final RxnInt expandedExhibitorId = RxnInt();

  final RxString searchKey = "".obs;
  final TextEditingController searchController = TextEditingController();

  Map<String, dynamic> _lastPayload = {};

  List<ExhibitorModel> get exhibitors => exhibitorPage.value.exhibitors;
  ExhibitorAdModel? get contentAd => exhibitorPage.value.contentAd;
  ExhibitorAdModel? get featuredAd => exhibitorPage.value.featuredAd;

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

  Future<void> fetchExhibitors() async {
    await handleApiClient(
      onStateChanged: (state) => dataStatus(state),
      handleApiCall: () async {
        final response = await _service.getExhibitors();
        final body = response.data;
        if (body is! Map) return;

        _lastPayload = body['data'] is Map
            ? Map<String, dynamic>.from(body['data'] as Map)
            : Map<String, dynamic>.from(body);

        _applyFilters();
      },
    );
  }

  void _applyFilters() {
    if (_lastPayload.isEmpty) {
      exhibitorPage.value = const ExhibitorPageModel();
      return;
    }
    exhibitorPage.value = ExhibitorMapper.pageFromV1(
      _lastPayload,
      typeFilter: selectedType.value,
      search: searchKey.value,
    );
  }

  void setType(String? type) {
    selectedType.value = type;
    _applyFilters();
  }

  final detailStatus = ApiState.initial.obs;
  final Rxn<ExhibitorModel> exhibitorDetail = Rxn<ExhibitorModel>();

  Future<void> fetchExhibitorDetail(String slugOrUuid) async {
    await handleApiClient(
      onStateChanged: (state) => detailStatus(state),
      handleApiCall: () async {
        final response = await _service.getExhibitorDetails(slugOrUuid);
        final body = response.data;
        if (body is! Map) {
          throw Exception('Unexpected exhibitor response');
        }
        exhibitorDetail.value =
            ExhibitorMapper.detailFromV1Response(Map<String, dynamic>.from(body));
        final detail = exhibitorDetail.value;
        if (detail != null) {
          final isSponsor =
              detail.exhibitorType.toLowerCase().contains('sponsor');
          final eng = EngagementService.instance;
          eng.track(
            actionType: isSponsor ? 'sponsor.booth_visited' : 'booth.visited',
            objectType: isSponsor ? 'sponsor' : 'exhibitor',
            objectUuid: detail.slug.isNotEmpty ? detail.slug : slugOrUuid,
            objectId: detail.id > 0 ? detail.id : null,
            idempotencyKey: eng.onceKey(
              isSponsor ? 'sponsor.booth_visited' : 'booth.visited',
              detail.slug.isNotEmpty ? detail.slug : slugOrUuid,
            ),
            metadata: {'name': detail.name},
          );
        }
      },
    );
  }

  Future<bool> toggleBookmark(int exhibitorId) async {
    final exhibitor = exhibitors.firstWhereOrNull((e) => e.id == exhibitorId);
    final uuid = exhibitor?.slug;
    if (uuid == null || uuid.isEmpty) return false;
    if (!Get.isRegistered<BookmarkController>()) return false;
    return Get.find<BookmarkController>().toggle(
      type: 'exhibitor',
      uuid: uuid,
      exhibitor: exhibitor,
    );
  }

  @override
  void onClose() {
    searchController.dispose();
    super.onClose();
  }
}
