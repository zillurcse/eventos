import 'package:get/get.dart';
import '../../models/leaderboard_entry_model.dart';
import '../../utils/enum/enums.dart';
import '../../utils/helpers/helper_functions.dart';
import 'leaderboard_service.dart';

class LeaderboardController extends GetxController {
  final _service = LeaderboardService();

  final dataStatus = ApiState.initial.obs;
  final RxList<LeaderboardEntryModel> leaderboard = <LeaderboardEntryModel>[].obs;
  final myPoints = 0.obs;
  final enabled = true.obs;

  Future<void> fetchLeaderboard() async {
    await handleApiClient(
      onStateChanged: (state) => dataStatus(state),
      handleApiCall: () async {
        final response = await _service.getLeaderboard();
        if (response.data is! Map) return;

        final body = Map<String, dynamic>.from(response.data as Map);
        final data = body['data'] is Map
            ? Map<String, dynamic>.from(body['data'] as Map)
            : body;

        enabled(data['enabled'] != false);
        myPoints(int.tryParse('${data['my_points'] ?? 0}') ?? 0);

        final entries = data['leaderboard'] as List? ??
            data['user_leaderboard'] as List? ??
            [];

        leaderboard.assignAll(
          entries.asMap().entries.map((e) {
            final map = Map<String, dynamic>.from(e.value as Map);
            return LeaderboardEntryModel.fromJson(map, rank: e.key + 1);
          }).toList(),
        );
      },
    );
  }
}
