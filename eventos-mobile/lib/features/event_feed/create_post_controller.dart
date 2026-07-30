import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:get/get.dart';

import '../../utils/helpers/toast_msg.dart';
import 'event_feed_controller.dart';
import 'event_feed_service.dart';

/// Owns all create-post, file-attachment, and submit logic.
///
/// Separated from [EventFeedController] to keep each controller focused on
/// a single responsibility, consistent with the project-wide pattern:
///   view · controller · service · pages · widgets
class CreatePostController extends GetxController {
  final _service = EventFeedService();

  // ── Submission state ───────────────────────────────────────────────────────
  final RxBool isSubmitting = false.obs;

  // ── File attachment state ──────────────────────────────────────────────────
  /// Local file path of the currently picked attachment, or null.
  final Rx<String?> attachFilePath = Rx<String?>(null);

  /// Resolved attach type: 'image', 'video', or 'pdf'.
  final Rx<String?> attachType = Rx<String?>(null);

  /// The actual [File] ready to be uploaded.
  final Rx<File?> attachFile = Rx<File?>(null);

  /// Clears any previously picked attachment.
  void clearAttach() {
    attachFilePath.value = null;
    attachType.value = null;
    attachFile.value = null;
  }

  // ── File pickers ───────────────────────────────────────────────────────────

  /// Pick an image (.jpg / .jpeg / .png / .webp) — max 5 MB.
  Future<void> pickImageFile() async {
    try {
      final result = await FilePicker.pickFiles(
        type: FileType.custom,
        allowedExtensions: ['jpg', 'jpeg', 'png', 'webp'],
      );
      if (result == null || result.files.single.path == null) return;
      await _storePickedFile(
        result.files.single,
        limitBytes: 5 * 1024 * 1024,
        limitLabel: '5 MB',
      );
    } catch (_) {}
  }

  /// Pick a PDF — max 10 MB.
  Future<void> pickPdfFile() async {
    try {
      final result = await FilePicker.pickFiles(
        type: FileType.custom,
        allowedExtensions: ['pdf'],
      );
      if (result == null || result.files.single.path == null) return;
      await _storePickedFile(
        result.files.single,
        limitBytes: 10 * 1024 * 1024,
        limitLabel: '10 MB',
      );
    } catch (_) {}
  }

  /// Pick a video (.mp4 / .mov / .webm) — max 50 MB.
  Future<void> pickVideoFile() async {
    try {
      final result = await FilePicker.pickFiles(
        type: FileType.custom,
        allowedExtensions: ['mp4', 'mov', 'webm'],
      );
      if (result == null || result.files.single.path == null) return;
      await _storePickedFile(
        result.files.single,
        limitBytes: 50 * 1024 * 1024,
        limitLabel: '50 MB',
      );
    } catch (_) {}
  }

  /// Validates size, resolves attach_type, and stores the [File] reference.
  Future<void> _storePickedFile(
    PlatformFile pickedFile, {
    required int limitBytes,
    required String limitLabel,
  }) async {
    if (pickedFile.size > limitBytes) {
      ToastMsg.showErrorMessage(
        'File too large. Max allowed size is $limitLabel.',
      );
      return;
    }

    final filePath = pickedFile.path!;
    final extension = pickedFile.extension?.toLowerCase();

    final String resolvedAttachType;
    switch (extension) {
      case 'png':
      case 'webp':
      case 'jpg':
      case 'jpeg':
        resolvedAttachType = 'image';
      case 'pdf':
        resolvedAttachType = 'pdf';
      case 'mov':
      case 'webm':
      case 'mp4':
        resolvedAttachType = 'video';
      default:
        resolvedAttachType = 'image';
    }

    attachFilePath.value = filePath;
    attachType.value = resolvedAttachType;
    attachFile.value = File(filePath);
  }

  String _mapUiTypeToApi(String type) {
    return switch (type) {
      'post' => 'text',
      'looking-for' => 'looking_for',
      'offering' => 'offering',
      'poll' => 'poll',
      'video' => 'video',
      'pdf' => 'pdf',
      'image' => 'image',
      _ => 'text',
    };
  }

  // ── Submit: regular text post ──────────────────────────────────────────────
  /// Returns true on success so the caller can pop the screen.
  Future<bool> submitPost({
    required String body,
    required String type, // 'post' | 'offering' | 'looking-for'
  }) async {
    if (body.trim().isEmpty) {
      ToastMsg.showErrorMessage('Please write something before posting.');
      return false;
    }
    if (isSubmitting.value) return false;
    isSubmitting.value = true;
    try {
      await _service.createPost({
        'type': _mapUiTypeToApi(type),
        'body': body.trim(),
        'visibility': 'attendees',
      });
      ToastMsg.showSuccessMessage('Post created!');
      await _refreshFeed();
      return true;
    } catch (err) {
      ToastMsg.showApiErrorMessage(err);
      return false;
    } finally {
      isSubmitting.value = false;
    }
  }

  // ── Submit: poll ───────────────────────────────────────────────────────────
  Future<bool> submitPoll({
    required String question,
    required List<String> options,
    int lengthDays = 0,
    int lengthHours = 0,
    int lengthMinutes = 0,
  }) async {
    if (question.trim().isEmpty) {
      ToastMsg.showErrorMessage('Please write a poll question.');
      return false;
    }
    final validOptions = options.where((o) => o.trim().isNotEmpty).toList();
    if (validOptions.length < 2) {
      ToastMsg.showErrorMessage('Please add at least 2 poll options.');
      return false;
    }
    if (isSubmitting.value) return false;
    isSubmitting.value = true;
    try {
      await _service.createPost({
        'type': 'poll',
        'body': question.trim(),
        'visibility': 'attendees',
        'poll': {
          'options': validOptions,
          'allow_multiple': false,
        },
      });
      ToastMsg.showSuccessMessage('Poll created!');
      await _refreshFeed();
      return true;
    } catch (err) {
      ToastMsg.showApiErrorMessage(err);
      return false;
    } finally {
      isSubmitting.value = false;
    }
  }

  // ── Submit: post with file attachment (upload → create) ────────────────────
  /// Returns true on success so the caller can pop the screen.
  Future<bool> submitPostWithAttach({required String body}) async {
    final type = attachType.value;
    final file = attachFile.value;

    if (type == null || file == null) {
      ToastMsg.showErrorMessage('No file selected. Please pick a file first.');
      return false;
    }
    if (isSubmitting.value) return false;

    final apiType = switch (type) {
      'pdf' => 'pdf',
      'video' => 'video',
      _ => 'image',
    };

    isSubmitting.value = true;
    try {
      final uploadRes = await _service.uploadMedia(file);
      final uploadBody = uploadRes.data;
      final uploadData = uploadBody is Map && uploadBody['data'] is Map
          ? Map<String, dynamic>.from(uploadBody['data'] as Map)
          : <String, dynamic>{};
      final url = (uploadData['url'] ?? '').toString();
      if (url.isEmpty) {
        throw Exception('Upload failed — no URL returned.');
      }

      await _service.createPost({
        'type': apiType,
        'body': body.trim(),
        'visibility': 'attendees',
        'attachments': [
          {
            'kind': type,
            'url': url,
            'name': uploadData['filename'],
          },
        ],
      });
      ToastMsg.showSuccessMessage('Post created!');
      clearAttach();
      await _refreshFeed();
      return true;
    } catch (err) {
      ToastMsg.showApiErrorMessage(err);
      return false;
    } finally {
      isSubmitting.value = false;
    }
  }

  // ── Private helpers ────────────────────────────────────────────────────────
  /// Refreshes the feed list after a successful post submission.
  Future<void> _refreshFeed() async {
    try {
      await Get.find<EventFeedController>().fetchFeed();
    } catch (_) {
      // Feed controller may not be registered yet in tests — ignore.
    }
  }
}
