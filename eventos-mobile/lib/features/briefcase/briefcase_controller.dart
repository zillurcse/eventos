import 'package:get/get.dart';

import '../../models/briefcase_item_model.dart';
import '../../utils/enum/enums.dart';
import '../../utils/helpers/helper_functions.dart';
import '../../utils/helpers/type_helper.dart';
import 'briefcase_service.dart';

class BriefcaseController extends GetxController {
  static BriefcaseController get to => Get.find();

  final BriefcaseService _service = BriefcaseService();

  final RxList<BriefcaseFileModel> files = <BriefcaseFileModel>[].obs;

  // Notes keyed by display type: Speaker / Session / Delegate
  final RxMap<String, List<BriefcaseNoteModel>> notesMap =
      <String, List<BriefcaseNoteModel>>{}.obs;

  List<BriefcaseNoteModel> get notes =>
      notesMap.values.expand((element) => element).toList();

  final dataStatus = ApiState.initial.obs;

  @override
  void onInit() {
    super.onInit();
    fetchAllBriefcaseItems();
  }

  static String _kindFromUrl(String url) {
    final ext = (url.split('?').first.split('.').last).toLowerCase();
    if (ext == 'pdf') return 'pdf';
    if (['doc', 'docx'].contains(ext)) return 'doc';
    if (['xls', 'xlsx', 'csv'].contains(ext)) return 'excel';
    if (['png', 'jpg', 'jpeg', 'gif', 'webp'].contains(ext)) return 'image';
    return 'file';
  }

  Future<void> fetchAllBriefcaseItems() async {
    await handleApiClient(
      onStateChanged: (state) => dataStatus(state),
      handleApiCall: () async {
        final results = await Future.wait([
          _service.getBriefcase(),
          _service.getNotes(),
        ]);

        final filesRes = results[0];
        if (filesRes.data is Map) {
          final body = Map<String, dynamic>.from(filesRes.data as Map);
          final list = body['data'] as List? ?? [];
          files.assignAll(
            list
                .whereType<Map>()
                .map((e) => BriefcaseFileModel.fromJson(
                      Map<String, dynamic>.from(e),
                    ))
                .toList(),
          );
        }

        final notesRes = results[1];
        if (notesRes.data is Map) {
          final body = Map<String, dynamic>.from(notesRes.data as Map);
          final notesObj = body['data'] is Map
              ? Map<String, dynamic>.from(body['data'] as Map)
              : <String, dynamic>{};

          final parsed = <String, List<BriefcaseNoteModel>>{};
          for (final entry in notesObj.entries) {
            final type = entry.key;
            final notesList = entry.value as List? ?? [];
            if (notesList.isEmpty) continue;
            final displayKey = type.isEmpty
                ? type
                : '${type[0].toUpperCase()}${type.substring(1)}';
            parsed[displayKey] = notesList
                .whereType<Map>()
                .map((e) => BriefcaseNoteModel.fromApiResponse(
                      Map<String, dynamic>.from(e),
                      type,
                    ))
                .toList();
          }
          notesMap.assignAll(parsed);
        }
      },
    );
  }

  bool isFileInBriefcase(String url) => files.any((f) => f.url == url);

  Future<void> addFile(String name, String url) async {
    if (url.isEmpty || isFileInBriefcase(url)) return;
    final kind = _kindFromUrl(url);
    final temp = BriefcaseFileModel(
      id: 'tmp-${DateTime.now().millisecondsSinceEpoch}',
      name: name,
      url: url,
      kind: kind,
      createdAt: DateTime.now(),
    );
    files.add(temp);
    try {
      final response = await _service.addFile(title: name, url: url, kind: kind);
      if (response.data is Map) {
        final body = Map<String, dynamic>.from(response.data as Map);
        if (body['data'] is Map) {
          final saved = BriefcaseFileModel.fromJson(
            Map<String, dynamic>.from(body['data'] as Map),
          );
          final i = files.indexWhere((f) => f.id == temp.id);
          if (i >= 0) files[i] = saved;
        }
      }
    } catch (_) {
      files.removeWhere((f) => f.id == temp.id);
    }
  }

  Future<void> removeFile(String id) async {
    final prev = List<BriefcaseFileModel>.from(files);
    files.removeWhere((f) => f.id == id);
    try {
      await _service.removeFile(id);
    } catch (_) {
      files.assignAll(prev);
    }
  }

  Future<void> removeFileByUrl(String url) async {
    final match = files.firstWhereOrNull((f) => f.url == url);
    if (match == null) return;
    await removeFile(match.id);
  }

  Future<void> addNote({
    required String noteType,
    int? entityId,
    String? targetId,
    required String entityName,
    required String entityRole,
    required String entityImage,
    required String noteText,
  }) async {
    final apiType = noteType.toLowerCase();
    final key = apiType.isEmpty
        ? noteType
        : '${apiType[0].toUpperCase()}${apiType.substring(1)}';

    final tid = targetId ?? '';
    final hashed = entityId ?? (tid.isNotEmpty ? TypeHelper.toInt(tid) : null);

    // Optimistic local upsert.
    final list = List<BriefcaseNoteModel>.from(notesMap[key] ?? []);
    final existingIdx = list.indexWhere((n) =>
        (tid.isNotEmpty && n.targetId == tid) ||
        (hashed != null && n.entityId == hashed));
    final local = BriefcaseNoteModel(
      id: existingIdx >= 0
          ? list[existingIdx].id
          : 'tmp-${DateTime.now().millisecondsSinceEpoch}',
      noteType: key,
      targetId: tid.isNotEmpty
          ? tid
          : (existingIdx >= 0 ? list[existingIdx].targetId : ''),
      entityId: hashed,
      entityName: entityName,
      entityRole: entityRole,
      entityImage: entityImage,
      noteText: noteText,
      createdAt: DateTime.now(),
    );
    if (existingIdx >= 0) {
      list[existingIdx] = local;
    } else {
      list.add(local);
    }
    notesMap[key] = list;
    notesMap.refresh();

    if (tid.isNotEmpty) {
      try {
        final response = await _service.saveNote(
          type: apiType,
          targetId: tid,
          text: noteText,
        );
        if (response.data is Map) {
          final body = Map<String, dynamic>.from(response.data as Map);
          if (body['data'] is Map) {
            final saved = BriefcaseNoteModel.fromApiResponse(
              Map<String, dynamic>.from(body['data'] as Map),
              apiType,
              entityName: entityName,
              entityRole: entityRole,
              entityImage: entityImage,
            );
            final i = (notesMap[key] ?? []).indexWhere(
              (n) => n.targetId == tid || n.id == local.id,
            );
            if (i >= 0) {
              notesMap[key]![i] = saved;
              notesMap.refresh();
            }
          }
        }
      } catch (_) {
        // Keep optimistic local note; next fetch reconciles.
      }
    }
  }

  Future<void> removeNote(String id) async {
    BriefcaseNoteModel? found;
    String? foundKey;
    for (final entry in notesMap.entries) {
      final match = entry.value.firstWhereOrNull((n) => n.id == id);
      if (match != null) {
        found = match;
        foundKey = entry.key;
        break;
      }
    }
    if (found == null || foundKey == null) return;

    final prev = Map<String, List<BriefcaseNoteModel>>.from(
      notesMap.map((k, v) => MapEntry(k, List<BriefcaseNoteModel>.from(v))),
    );
    notesMap[foundKey]?.removeWhere((n) => n.id == id);
    if (notesMap[foundKey]?.isEmpty ?? false) {
      notesMap.remove(foundKey);
    }
    notesMap.refresh();

    final tid = found.targetId;
    if (tid.isEmpty) return;
    try {
      await _service.removeNote(
        type: found.noteType.toLowerCase(),
        targetId: tid,
      );
    } catch (_) {
      notesMap.assignAll(prev);
    }
  }
}
