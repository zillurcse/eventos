import 'package:get/get.dart';

import '../../models/contest_model.dart';
import '../../utils/enum/enums.dart';
import '../../utils/helpers/helper_functions.dart';
import 'contest_details_controller.dart';
import 'contests_service.dart';
import 'pages/contest_details_view.dart';

class ContestsController extends GetxController {
  final _service = ContestsService();

  final dataStatus = ApiState.initial.obs;
  final contests = <Contest>[].obs;
  final filter = 'all'.obs;
  final adImageUrl = ''.obs;

  List<Contest> get shown {
    final f = filter.value;
    if (f == 'all') return contests.toList();
    return contests.where((c) => c.phase == f).toList();
  }

  Map<String, int> get counts => {
        'all': contests.length,
        'ongoing': contests.where((c) => c.isOngoing).length,
        'upcoming': contests.where((c) => c.isUpcoming).length,
        'ended': contests.where((c) => c.isEnded).length,
      };

  Future<void> fetchContests() async {
    await handleApiClient(
      onStateChanged: (state) => dataStatus(state),
      handleApiCall: () async {
        final response = await _service.getContests();
        final body = response.data;
        if (body is! Map) return;
        final raw = body['data'];
        final list = raw is List ? raw : const [];
        contests.assignAll(
          list
              .whereType<Map>()
              .map((e) => Contest.fromJson(Map<String, dynamic>.from(e)))
              .toList(),
        );
      },
    );
    _fetchAds();
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
      final url = (first['image_url'] ??
              first['image'] ??
              first['url'] ??
              '')
          .toString();
      if (url.isNotEmpty) adImageUrl.value = url;
    } catch (_) {
      // Ads are optional.
    }
  }

  void setFilter(String value) => filter.value = value;

  void openContest(Contest contest) {
    if (Get.isRegistered<ContestDetailsController>()) {
      Get.delete<ContestDetailsController>(force: true);
    }
    Get.to(
      () => ContestDetailsView(contestId: contest.id),
      binding: BindingsBuilder(() {
        Get.put(ContestDetailsController(contestId: contest.id));
      }),
      transition: Transition.cupertino,
    );
  }
}
