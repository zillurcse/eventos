import 'package:get/get.dart';

import '../../models/participant_badge_model.dart';
import '../../utils/enum/enums.dart';
import '../../utils/helpers/helper_functions.dart';
import 'badges_service.dart';

class BadgesController extends GetxController {
  final _service = BadgesService();

  final dataStatus = ApiState.initial.obs;
  final badges = <ParticipantBadge>[].obs;

  /// Participation id of the badge showing the full-screen QR overlay.
  final scanningId = RxnString();

  /// Per-badge face flip: participationId → showing back.
  final flipped = <String, bool>{}.obs;

  ParticipantBadge? get scanning {
    final id = scanningId.value;
    if (id == null) return null;
    for (final b in badges) {
      if (b.participationId == id) return b;
    }
    return null;
  }

  bool isFlipped(String participationId) => flipped[participationId] == true;

  void toggleFlip(String participationId) {
    flipped[participationId] = !isFlipped(participationId);
    flipped.refresh();
  }

  void showQr(ParticipantBadge badge) {
    scanningId.value = badge.participationId;
  }

  void closeQr() {
    scanningId.value = null;
  }

  Future<void> fetchBadges() async {
    await handleApiClient(
      onStateChanged: (state) => dataStatus(state),
      handleApiCall: () async {
        final response = await _service.getMyBadges();
        final body = response.data;
        if (body is! Map) return;
        final raw = body['data'];
        final list = raw is List ? raw : const [];
        badges.assignAll(
          list
              .whereType<Map>()
              .map((e) =>
                  ParticipantBadge.fromJson(Map<String, dynamic>.from(e)))
              .toList(),
        );
      },
    );
  }
}
