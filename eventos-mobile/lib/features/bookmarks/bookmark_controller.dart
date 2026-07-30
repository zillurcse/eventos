import 'package:get/get.dart';
import '../../models/speaker_model.dart';
import '../../models/session_model.dart';
import '../../models/exhibitor_model.dart';
import '../../models/delegate_model.dart';
import '../../utils/enum/enums.dart';
import '../../utils/helpers/helper_functions.dart';
import 'bookmark_service.dart';

class BookmarkController extends GetxController {
  final _service = BookmarkService();

  final dataStatus = ApiState.initial.obs;

  final RxList<SpeakerItemModel> bookmarkedSpeakers = <SpeakerItemModel>[].obs;
  final RxList<SessionModel> bookmarkedSessions = <SessionModel>[].obs;
  final RxList<ExhibitorModel> bookmarkedExhibitors = <ExhibitorModel>[].obs;
  final RxList<DelegateItemModel> bookmarkedDelegates = <DelegateItemModel>[].obs;

  @override
  void onInit() {
    super.onInit();
    fetchBookmarks();
  }

  Future<void> fetchBookmarks() async {
    await handleApiClient(
      onStateChanged: (state) => dataStatus(state),
      handleApiCall: () async {
        final response = await _service.getBookmarks();
        if (response.data is Map) {
          final data = Map<String, dynamic>.from(response.data as Map);
          final resData = data['data'] as Map?;
          if (resData != null) {
            // parse speakers
            final speakersList = resData['speakers'] as List? ?? [];
            bookmarkedSpeakers.assignAll(
              speakersList
                  .map((e) => SpeakerItemModel.fromJson(Map<String, dynamic>.from(e)))
                  .toList(),
            );
            // parse exhibitors
            final exhibitorsList = resData['exhibitors'] as List? ?? [];
            bookmarkedExhibitors.assignAll(
              exhibitorsList
                  .map((e) => ExhibitorModel.fromJson(Map<String, dynamic>.from(e)))
                  .toList(),
            );
            // parse delegates
            final delegatesList = resData['delegates'] as List? ?? [];
            bookmarkedDelegates.assignAll(
              delegatesList
                  .map((e) => DelegateItemModel.fromJson(Map<String, dynamic>.from(e)))
                  .toList(),
            );
            // parse schedules (sessions)
            final schedulesList = resData['schedules'] as List? ?? [];
            bookmarkedSessions.assignAll(
              schedulesList
                  .map((e) => SessionModel.fromJson(Map<String, dynamic>.from(e)))
                  .toList(),
            );
          }
        }
      },
    );
  }

  void toggleSpeakerBookmark(SpeakerItemModel speaker) {
    if (bookmarkedSpeakers.any((s) => s.id == speaker.id)) {
      bookmarkedSpeakers.removeWhere((s) => s.id == speaker.id);
    } else {
      bookmarkedSpeakers.add(speaker);
    }
  }

  void toggleSessionBookmark(SessionModel session) {
    if (bookmarkedSessions.any((s) => s.id == session.id)) {
      bookmarkedSessions.removeWhere((s) => s.id == session.id);
    } else {
      bookmarkedSessions.add(session);
    }
  }

  void toggleExhibitorBookmark(ExhibitorModel exhibitor) {
    if (bookmarkedExhibitors.any((e) => e.id == exhibitor.id)) {
      bookmarkedExhibitors.removeWhere((e) => e.id == exhibitor.id);
    } else {
      bookmarkedExhibitors.add(exhibitor);
    }
  }

  void toggleDelegateBookmark(DelegateItemModel delegate) {
    if (bookmarkedDelegates.any((d) => d.id == delegate.id)) {
      bookmarkedDelegates.removeWhere((d) => d.id == delegate.id);
    } else {
      bookmarkedDelegates.add(delegate);
    }
  }
}
