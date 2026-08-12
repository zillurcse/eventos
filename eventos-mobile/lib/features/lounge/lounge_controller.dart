import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:get_storage/get_storage.dart';
import 'package:permission_handler/permission_handler.dart';

import '../../models/lounge_model.dart';
import '../../models/user.dart';
import '../../utils/enum/enums.dart';
import '../../utils/helpers/helper_functions.dart';
import '../../utils/helpers/local_key.dart';
import '../../utils/helpers/toast_msg.dart';
import '../../utils/service/engagement_service.dart';
import 'lounge_service.dart';
import 'pages/lounge_room_view.dart';

enum LoungeViewMode { classic, cozy }

class LoungeController extends GetxController {
  final _service = LoungeService();
  final _storage = GetStorage();

  final dataStatus = ApiState.initial.obs;
  final enabled = false.obs;
  final tabs = const LoungeTabs().obs;
  final selectedKind = LoungeTableKind.attendee.obs;
  final searchKey = ''.obs;
  final searchController = TextEditingController();
  final joiningId = ''.obs;
  final activeTableId = ''.obs;
  final viewMode = LoungeViewMode.classic.obs;

  Timer? _pollTimer;
  bool _loadedOnce = false;

  String get meId {
    final raw = _storage.read(LocalKeyHelper.userInfo);
    if (raw is! Map) return '';
    final user = User.fromJson(Map<String, dynamic>.from(raw));
    final uuid = user.uid.isNotEmpty ? user.uid : '';
    return uuid.isEmpty ? '' : 'user_$uuid';
  }

  String? get _avatarUrl {
    final raw = _storage.read(LocalKeyHelper.userInfo);
    if (raw is! Map) return null;
    final user = User.fromJson(Map<String, dynamic>.from(raw));
    return user.profilePhotoUrl.isEmpty ? null : user.profilePhotoUrl;
  }

  List<LoungeTable> get filteredTables {
    final q = searchKey.value.trim().toLowerCase();
    final list = tabs.value.forKind(selectedKind.value);
    if (q.isEmpty) return list;
    return list.where((t) => t.name.toLowerCase().contains(q)).toList();
  }

  @override
  void onInit() {
    super.onInit();
    debounce(
      searchKey,
      (_) {},
      time: const Duration(milliseconds: 300),
    );
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

  void setKind(LoungeTableKind kind) => selectedKind.value = kind;

  void setViewMode(LoungeViewMode mode) => viewMode.value = mode;

  void startPolling() {
    _pollTimer?.cancel();
    _pollTimer = Timer.periodic(const Duration(seconds: 15), (_) {
      if (activeTableId.value.isNotEmpty) return;
      fetchTables(silent: true);
    });
  }

  void stopPolling() {
    _pollTimer?.cancel();
    _pollTimer = null;
  }

  Future<void> fetchTables({bool silent = false}) async {
    if (silent && _loadedOnce) {
      try {
        await _loadTables();
      } catch (_) {}
      return;
    }

    await handleApiClient(
      onStateChanged: (state) => dataStatus(state),
      handleApiCall: _loadTables,
    );
  }

  Future<void> _loadTables() async {
    final response = await _service.getTables();
    final body = response.data;
    if (body is! Map) return;

    final data = body['data'] is Map
        ? Map<String, dynamic>.from(body['data'] as Map)
        : Map<String, dynamic>.from(body);

    enabled.value = data['enabled'] == true;
    final tabsRaw = data['tabs'];
    tabs.value = tabsRaw is Map
        ? LoungeTabs.fromJson(Map<String, dynamic>.from(tabsRaw))
        : const LoungeTabs();
    _loadedOnce = true;

    // Prefer a tab that actually has tables.
    if (tabs.value.forKind(selectedKind.value).isEmpty) {
      for (final kind in LoungeTableKind.values) {
        if (tabs.value.forKind(kind).isNotEmpty) {
          selectedKind.value = kind;
          break;
        }
      }
    }
  }

  Future<void> joinTable(LoungeTable table, {int? seat}) async {
    if (joiningId.value.isNotEmpty) return;
    if (table.full) {
      ToastMsg.showErrorMessage('This table is full. Try another one.');
      return;
    }

    final cam = await Permission.camera.request();
    final mic = await Permission.microphone.request();
    if (!cam.isGranted && !mic.isGranted) {
      ToastMsg.showErrorMessage(
        'Camera or microphone permission is required to join a table.',
      );
      return;
    }

    joiningId.value = table.id;
    try {
      final response = await _service.joinTable(
        tableId: table.id,
        seat: seat,
        avatarUrl: _avatarUrl,
      );
      final body = response.data;
      if (body is! Map) throw Exception('Unexpected join response');
      final data = body['data'] is Map
          ? Map<String, dynamic>.from(body['data'] as Map)
          : Map<String, dynamic>.from(body);

      final config = LoungeJoinConfig.fromJson(data);
      if (config.url.isEmpty || config.token.isEmpty) {
        throw Exception('Join did not return a media token');
      }

      activeTableId.value = table.id;
      final joinedAt = DateTime.now();
      EngagementService.instance.track(
        actionType: 'lounge.joined',
        objectType: 'lounge',
        objectUuid: table.id,
        metadata: {
          'table_name': table.name,
          if (seat != null) 'seat': seat,
        },
      );
      await Get.to(
        () => LoungeRoomView(config: config),
        transition: Transition.fadeIn,
      );
      EngagementService.instance.track(
        actionType: 'lounge.left',
        objectType: 'lounge',
        objectUuid: table.id,
        durationMs: DateTime.now().difference(joinedAt).inMilliseconds,
      );
      activeTableId.value = '';
      await fetchTables(silent: true);
    } on DioException catch (e) {
      _showJoinError(e);
    } catch (e) {
      ToastMsg.showErrorMessage(e.toString());
    } finally {
      joiningId.value = '';
    }
  }

  void _showJoinError(DioException e) {
    final data = e.response?.data;
    if (data is Map) {
      final errors = data['errors'];
      if (errors is Map) {
        for (final key in ['seat', 'table']) {
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
