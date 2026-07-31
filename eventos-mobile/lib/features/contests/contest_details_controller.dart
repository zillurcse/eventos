import 'dart:io';

import 'package:dio/dio.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../models/contest_model.dart';
import '../../utils/enum/enums.dart';
import '../../utils/helpers/helper_functions.dart';
import '../../utils/helpers/toast_msg.dart';
import 'contests_service.dart';

class ContestDetailsController extends GetxController {
  ContestDetailsController({required this.contestId});

  final String contestId;
  final _service = ContestsService();

  final dataStatus = ApiState.initial.obs;
  final entriesLoading = false.obs;
  final submitting = false.obs;
  final uploading = false.obs;

  final contest = Rxn<Contest>();
  final entries = <ContestEntry>[].obs;
  final sort = 'recent'.obs;
  final mineOnly = false.obs;
  final comments = <String, List<ContestEntry>>{}.obs;
  final expandedComments = <String>{}.obs;

  // Composer
  final bodyController = TextEditingController();
  final bodyText = ''.obs;
  final draftAttachments = <ContestAttachment>[].obs;
  final composerMessage = ''.obs;
  final composerError = ''.obs;

  @override
  void onInit() {
    super.onInit();
    bodyController.addListener(() => bodyText.value = bodyController.text);
    fetchAll();
  }

  @override
  void onClose() {
    bodyController.dispose();
    super.onClose();
  }

  Future<void> fetchAll() async {
    await handleApiClient(
      onStateChanged: (state) => dataStatus(state),
      handleApiCall: () async {
        final response = await _service.getContest(contestId);
        final body = response.data;
        if (body is! Map) return;
        final data = body['data'];
        if (data is Map) {
          contest.value =
              Contest.fromJson(Map<String, dynamic>.from(data));
        }
      },
    );
    if (contest.value != null) {
      await fetchEntries();
    }
  }

  Future<void> fetchEntries() async {
    entriesLoading.value = true;
    try {
      final response = await _service.getEntries(
        contestId,
        sort: sort.value,
        mineOnly: mineOnly.value,
      );
      final body = response.data;
      if (body is! Map) return;
      final raw = body['data'];
      final list = raw is List ? raw : const [];
      entries.assignAll(
        list
            .whereType<Map>()
            .map((e) => ContestEntry.fromJson(Map<String, dynamic>.from(e)))
            .toList(),
      );
    } catch (e) {
      entries.clear();
      ToastMsg.showApiErrorMessage(e);
    } finally {
      entriesLoading.value = false;
    }
  }

  void setSort(String value) {
    if (sort.value == value) return;
    sort.value = value;
    fetchEntries();
  }

  void setMineOnly(bool value) {
    if (mineOnly.value == value) return;
    mineOnly.value = value;
    fetchEntries();
  }

  Future<void> pickMedia() async {
    final c = contest.value;
    if (c == null || uploading.value) return;

    final allowImage = c.allowPhotos || c.allowSelfie;
    final allowVideo = c.allowVideos;
    if (!allowImage && !allowVideo) return;

    final remaining = 5 - draftAttachments.length;
    if (remaining <= 0) {
      ToastMsg.showErrorMessage('You can attach up to 5 files.');
      return;
    }

    final type = allowImage && allowVideo
        ? FileType.custom
        : allowVideo
            ? FileType.video
            : FileType.image;

    final result = await FilePicker.pickFiles(
      type: type,
      allowedExtensions: type == FileType.custom
          ? const ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'webm']
          : null,
      allowMultiple: remaining > 1,
      withData: false,
    );
    if (result == null || result.files.isEmpty) return;

    uploading.value = true;
    composerError.value = '';
    try {
      for (final file in result.files.take(remaining)) {
        final path = file.path;
        if (path == null || path.isEmpty) continue;
        final response = await _service.uploadMedia(File(path));
        final body = response.data;
        final data = body is Map && body['data'] is Map
            ? Map<String, dynamic>.from(body['data'] as Map)
            : <String, dynamic>{};
        final url = (data['url'] ?? '').toString();
        if (url.isEmpty) continue;
        final mime = (data['mime_type'] ?? file.extension ?? '').toString();
        final kind = mime.startsWith('video') ||
                ['mp4', 'mov', 'webm'].contains(file.extension?.toLowerCase())
            ? 'video'
            : 'image';
        draftAttachments.add(
          ContestAttachment(
            kind: kind,
            url: url,
            name: (data['filename'] ?? file.name).toString(),
          ),
        );
      }
    } catch (e) {
      composerError.value = 'That file couldn’t be uploaded.';
      ToastMsg.showApiErrorMessage(e);
    } finally {
      uploading.value = false;
    }
  }

  void removeDraftAttachment(int index) {
    if (index < 0 || index >= draftAttachments.length) return;
    draftAttachments.removeAt(index);
  }

  Future<void> submitEntry() async {
    final c = contest.value;
    if (c == null || submitting.value || uploading.value) return;

    final text = bodyText.value.trim();
    if (text.isEmpty && draftAttachments.isEmpty) {
      composerError.value = 'Write something or attach media.';
      return;
    }
    if (c.attachMandatory && draftAttachments.isEmpty) {
      composerError.value = 'This contest requires an attachment.';
      return;
    }
    if (text.length > c.characterLimit) {
      composerError.value = 'Your text is too long.';
      return;
    }

    submitting.value = true;
    composerError.value = '';
    composerMessage.value = '';
    try {
      final response = await _service.submitEntry(
        contestId,
        body: text.isEmpty ? null : text,
        attachments: draftAttachments.map((a) => a.toJson()).toList(),
      );
      final body = response.data;
      final data = body is Map && body['data'] is Map
          ? Map<String, dynamic>.from(body['data'] as Map)
          : null;
      if (data != null) {
        final entry = ContestEntry.fromJson(data);
        entries.insert(0, entry);
        final current = contest.value!;
        contest.value = current.copyWith(
          myEntryCount: current.myEntryCount + 1,
          entryCount: entry.status == 'approved'
              ? current.entryCount + 1
              : current.entryCount,
          canEnter: current.allowMultipleEntries,
        );
        bodyController.clear();
        draftAttachments.clear();
        composerMessage.value = entry.status == 'pending'
            ? 'Submitted — the organizer will review it before it appears.'
            : 'Your entry is in. Good luck!';
      }
    } on DioException catch (e) {
      composerError.value = _extractError(e) ?? 'Could not submit your entry.';
    } catch (e) {
      composerError.value = 'Could not submit your entry.';
    } finally {
      submitting.value = false;
    }
  }

  Future<void> toggleLike(ContestEntry entry) async {
    final c = contest.value;
    if (c == null || entry.isMine || c.isEnded) return;

    final index = entries.indexWhere((e) => e.id == entry.id);
    if (index < 0) return;

    final before = entries[index];
    final liked = !before.liked;
    entries[index] = before.copyWith(
      liked: liked,
      likeCount: before.likeCount + (liked ? 1 : -1),
    );

    try {
      final response = await _service.toggleLike(entry.id);
      final body = response.data;
      final data = body is Map && body['data'] is Map
          ? Map<String, dynamic>.from(body['data'] as Map)
          : null;
      if (data != null) {
        entries[index] = entries[index].copyWith(
          liked: data['liked'] == true,
          likeCount: data['like_count'] is int
              ? data['like_count'] as int
              : int.tryParse('${data['like_count']}') ??
                  entries[index].likeCount,
        );
      }
    } catch (e) {
      entries[index] = before;
      ToastMsg.showApiErrorMessage(e);
    }
  }

  Future<void> toggleComments(ContestEntry entry) async {
    if (expandedComments.contains(entry.id)) {
      expandedComments.remove(entry.id);
      expandedComments.refresh();
      return;
    }
    expandedComments.add(entry.id);
    expandedComments.refresh();
    if (!comments.containsKey(entry.id)) {
      await fetchComments(entry.id);
    }
  }

  Future<void> fetchComments(String entryId) async {
    try {
      final response = await _service.getComments(entryId);
      final body = response.data;
      if (body is! Map) return;
      final raw = body['data'];
      final list = raw is List ? raw : const [];
      comments[entryId] = list
          .whereType<Map>()
          .map((e) => ContestEntry.fromJson(Map<String, dynamic>.from(e)))
          .toList();
      comments.refresh();
    } catch (_) {
      comments[entryId] = <ContestEntry>[];
      comments.refresh();
    }
  }

  Future<void> addComment(ContestEntry entry, String text) async {
    final trimmed = text.trim();
    if (trimmed.isEmpty) return;
    try {
      final response = await _service.addComment(entry.id, trimmed);
      final body = response.data;
      final data = body is Map && body['data'] is Map
          ? Map<String, dynamic>.from(body['data'] as Map)
          : null;
      if (data == null) return;
      final comment = ContestEntry.fromJson(data);
      final existing = comments[entry.id] ?? <ContestEntry>[];
      comments[entry.id] = <ContestEntry>[...existing, comment];
      comments.refresh();
      final index = entries.indexWhere((e) => e.id == entry.id);
      if (index >= 0) {
        entries[index] =
            entries[index].copyWith(commentCount: entries[index].commentCount + 1);
      }
    } catch (e) {
      ToastMsg.showApiErrorMessage(e);
    }
  }

  Future<void> removeEntry(ContestEntry entry) async {
    try {
      await _service.removeEntry(entry.id);
      entries.removeWhere((e) => e.id == entry.id);
      final current = contest.value;
      if (current != null) {
        contest.value = current.copyWith(
          myEntryCount: (current.myEntryCount - 1).clamp(0, 999999),
          entryCount: entry.status == 'approved'
              ? (current.entryCount - 1).clamp(0, 999999)
              : current.entryCount,
          canEnter: current.isOngoing &&
              (current.allowMultipleEntries ||
                  current.myEntryCount - 1 <= 0),
        );
      }
      ToastMsg.showSuccessMessage('Entry removed');
    } catch (e) {
      ToastMsg.showApiErrorMessage(e);
    }
  }

  String? _extractError(DioException e) {
    final data = e.response?.data;
    if (data is Map) {
      final errors = data['errors'];
      if (errors is Map) {
        for (final key in ['body', 'attachments']) {
          final list = errors[key];
          if (list is List && list.isNotEmpty) return list.first.toString();
        }
      }
      final msg = data['message']?.toString();
      if (msg != null && msg.isNotEmpty) return msg;
    }
    return null;
  }
}
