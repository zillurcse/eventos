import 'package:get/get.dart';
import '../../models/leaderboard_entry_model.dart';
import '../../utils/enum/enums.dart';
import '../../utils/helpers/helper_functions.dart';
import 'leaderboard_service.dart';

class LeaderboardController extends GetxController {
  final _service = LeaderboardService();

  final dataStatus = ApiState.initial.obs;
  final RxList<LeaderboardEntryModel> leaderboard = <LeaderboardEntryModel>[].obs;

  Future<void> fetchLeaderboard() async {
    await handleApiClient(
      onStateChanged: (state) => dataStatus(state),
      handleApiCall: () async {
        final response = await _service.getLeaderboard();
        if (response.data is Map) {
          final data = Map<String, dynamic>.from(response.data as Map);
          final entries = data['user_leaderboard'] as List? ?? [];
          leaderboard.assignAll(
            entries
                .asMap()
                .entries
                .map((e) => LeaderboardEntryModel.fromJson(
                    Map<String, dynamic>.from(e.value),
                    rank: e.key + 1))
                .toList(),
          );
        }
      },
    );
  }
}
