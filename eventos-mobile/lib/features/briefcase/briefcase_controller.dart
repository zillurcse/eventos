import 'package:get/get.dart';
import '../../models/briefcase_item_model.dart';
import '../../utils/enum/enums.dart';
import '../../utils/helpers/helper_functions.dart';
import 'briefcase_service.dart';

class BriefcaseController extends GetxController {
  static BriefcaseController get to => Get.find();

  final BriefcaseService _service = BriefcaseService();

  final RxList<BriefcaseFileModel> files = <BriefcaseFileModel>[].obs;
  
  // A map to hold notes dynamically categorized by their keys
  final RxMap<String, List<BriefcaseNoteModel>> notesMap = <String, List<BriefcaseNoteModel>>{}.obs;

  // Flattened list for backwards compatibility with widgets like SessionCard, SpeakerCard
  List<BriefcaseNoteModel> get notes => notesMap.values.expand((element) => element).toList();

  final dataStatus = ApiState.initial.obs;

  Future<void> fetchAllBriefcaseItems() async {
    await handleApiClient(
      onStateChanged: (state) => dataStatus(state),
      handleApiCall: () async {
        final response = await _service.getAllBriefcaseItems();
        if (response.data is Map) {
          final data = Map<String, dynamic>.from(response.data as Map);
          if (data['status'] == 'success') {
            
            // Parse Files
            if (data['data'] is List) {
              final filesList = data['data'] as List;
              files.assignAll(filesList
                  .map((e) => BriefcaseFileModel.fromJson(Map<String, dynamic>.from(e)))
                  .toList());
            }

            // Parse Notes dynamically based on the keys available in the "notes" object
            if (data['notes'] is Map) {
              final notesObj = Map<String, dynamic>.from(data['notes'] as Map);
              final Map<String, List<BriefcaseNoteModel>> parsedNotesMap = {};

              for (final entry in notesObj.entries) {
                final String type = entry.key; // e.g., "speaker", "session", "delegate"
                final notesList = entry.value as List?;
                if (notesList != null) {
                  parsedNotesMap[type] = notesList
                      .map((e) => BriefcaseNoteModel.fromApiResponse(Map<String, dynamic>.from(e), type))
                      .toList();
                }
              }
              notesMap.assignAll(parsedNotesMap);
            }
          }
        }
      },
    );
  }

  // --- Utility APIs (assuming they might need to make API calls later, kept for UI compatibility) ---
  bool isFileInBriefcase(String url) {
    return files.any((f) => f.url == url);
  }

  void addFile(String name, String url) {
    // Currently relying on remote API, so this is just a placeholder if needed
  }

  void removeFile(String id) {
    // Should call API to delete file, for now we just remove from UI
    files.removeWhere((f) => f.id == id);
  }

  void removeFileByUrl(String url) {
    files.removeWhere((f) => f.url == url);
  }

  void addNote({
    required String noteType,
    int? entityId,
    required String entityName,
    required String entityRole,
    required String entityImage,
    required String noteText,
  }) {
    // Placeholder: Need an API to add a note
  }

  void removeNote(String id) {
    // Placeholder: Need an API to delete a note, for now just remove from UI
    for (var key in notesMap.keys) {
      notesMap[key]?.removeWhere((n) => n.id == id);
    }
    notesMap.refresh();
  }
}

