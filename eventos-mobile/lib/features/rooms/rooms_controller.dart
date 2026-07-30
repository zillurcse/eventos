import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../models/lounge_model.dart';
import '../../models/room_model.dart';
import '../../utils/enum/enums.dart';
import '../../utils/helpers/helper_functions.dart';
import '../../utils/helpers/toast_msg.dart';
import '../lounge/pages/lounge_room_view.dart';
import 'rooms_service.dart';
import 'widgets/room_access_code_sheet.dart';
import 'widgets/room_detail_bottom_sheet.dart';

class RoomsController extends GetxController {
  final _service = RoomsService();

  final dataStatus = ApiState.initial.obs;
  final rooms = <BreakoutRoom>[].obs;
  final searchKey = ''.obs;
  final searchController = TextEditingController();
  final selectedType = 'all'.obs;
  final joiningId = 0.obs;
  final activeRoomId = 0.obs;

  Timer? _pollTimer;
  bool _loadedOnce = false;

  List<String> get availableTypes {
    final seen = <String>{};
    for (final r in rooms) {
      if (r.type.isNotEmpty) seen.add(r.type);
    }
    return seen.toList()..sort();
  }

  List<BreakoutRoom> get filteredRooms {
    final q = searchKey.value.trim().toLowerCase();
    final type = selectedType.value;
    return rooms.where((r) {
      if (type != 'all' && r.type != type) return false;
      if (q.isEmpty) return true;
      return r.name.toLowerCase().contains(q) ||
          (r.description ?? '').toLowerCase().contains(q) ||
          r.typeLabel.toLowerCase().contains(q);
    }).toList();
  }

  @override
  void onClose() {
    _pollTimer?.cancel();
    searchController.dispose();
    super.onClose();
  }

  void setSearchKey(String val) => searchKey.value = val;

  void clearSearch() {
    searchController.clear();
    searchKey.value = '';
  }

  void setType(String type) => selectedType.value = type;

  void startPolling() {
    _pollTimer?.cancel();
    _pollTimer = Timer.periodic(const Duration(seconds: 15), (_) {
      if (activeRoomId.value != 0) return;
      fetchRooms(silent: true);
    });
  }

  void stopPolling() {
    _pollTimer?.cancel();
    _pollTimer = null;
  }

  Future<void> fetchRooms({bool silent = false}) async {
    if (silent && _loadedOnce) {
      try {
        await _loadRooms();
      } catch (_) {}
      return;
    }

    await handleApiClient(
      onStateChanged: (state) => dataStatus(state),
      handleApiCall: _loadRooms,
    );
  }

  Future<void> _loadRooms() async {
    final response = await _service.getRooms();
    final body = response.data;
    if (body is! Map) return;

    final raw = body['data'];
    final list = raw is List ? raw : const [];
    rooms.assignAll(
      list
          .whereType<Map>()
          .map((e) => BreakoutRoom.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
    );
    _loadedOnce = true;

    if (selectedType.value != 'all' &&
        !availableTypes.contains(selectedType.value)) {
      selectedType.value = 'all';
    }
  }

  void openRoomDetail(BreakoutRoom room) {
    showRoomDetailBottomSheet(
      room: room,
      onJoin: () {
        Get.back();
        onJoinPressed(room);
      },
    );
  }

  Future<void> onJoinPressed(BreakoutRoom room) async {
    if (joiningId.value != 0) return;

    if (room.isPrivate && room.hasAccessCode) {
      showRoomAccessCodeSheet(
        room: room,
        onJoin: (code) => joinRoom(room, accessCode: code),
      );
      return;
    }

    await joinRoom(room);
  }

  Future<void> joinRoom(BreakoutRoom room, {String? accessCode}) async {
    if (joiningId.value != 0) return;

    if (room.provider == 'webrtc') {
      final cam = await Permission.camera.request();
      final mic = await Permission.microphone.request();
      if (!cam.isGranted && !mic.isGranted) {
        ToastMsg.showErrorMessage(
          'Camera or microphone permission is required to join a room.',
        );
        return;
      }
    }

    joiningId.value = room.id;
    try {
      final response = await _service.joinRoom(
        roomId: room.id,
        accessCode: accessCode,
      );
      final body = response.data;
      if (body is! Map) throw Exception('Unexpected join response');
      final data = body['data'] is Map
          ? Map<String, dynamic>.from(body['data'] as Map)
          : Map<String, dynamic>.from(body);

      final provider = (data['provider'] ?? room.provider).toString();
      final url = (data['url'] ?? '').toString();
      final token = (data['token'] ?? '').toString();
      final meetingUrl =
          (data['meeting_url'] ?? room.meetingUrl ?? '').toString();

      if (provider == 'webrtc') {
        if (url.isEmpty || token.isEmpty) {
          throw Exception('Join did not return a media token');
        }
        final config = LoungeJoinConfig(
          provider: provider,
          url: url,
          room: (data['room'] ?? '').toString(),
          token: token,
          title: room.name,
        );
        activeRoomId.value = room.id;
        await Get.to(
          () => LoungeRoomView(config: config),
          transition: Transition.fadeIn,
        );
        activeRoomId.value = 0;
        await fetchRooms(silent: true);
      } else if (meetingUrl.isNotEmpty) {
        final uri = Uri.tryParse(meetingUrl);
        if (uri != null) {
          await launchUrl(uri, mode: LaunchMode.externalApplication);
        } else {
          ToastMsg.showErrorMessage('Could not open this room link.');
        }
      } else {
        ToastMsg.showErrorMessage(
          "The '$provider' provider is not yet available.",
        );
      }
    } on DioException catch (e) {
      _showJoinError(e);
    } catch (e) {
      ToastMsg.showErrorMessage(e.toString());
    } finally {
      joiningId.value = 0;
    }
  }

  void _showJoinError(DioException e) {
    final data = e.response?.data;
    if (data is Map) {
      final errors = data['errors'];
      if (errors is Map) {
        for (final key in ['access_code', 'room', 'provider']) {
          final list = errors[key];
          if (list is List && list.isNotEmpty) {
            ToastMsg.showErrorMessage(list.first.toString());
            return;
          }
        }
      }
      final msg = data['message']?.toString();
      if (msg != null && msg.isNotEmpty) {
        ToastMsg.showErrorMessage(msg);
        return;
      }
    }
    ToastMsg.showApiErrorMessage(e);
  }
}
