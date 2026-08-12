import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import 'package:livekit_client/livekit_client.dart';

import '../../../models/lounge_model.dart';
import '../../../utils/config/app_config.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../utils/theme/app_colors.dart';

class LoungeRoomView extends StatefulWidget {
  final LoungeJoinConfig config;

  const LoungeRoomView({super.key, required this.config});

  @override
  State<LoungeRoomView> createState() => _LoungeRoomViewState();
}

class _LoungeRoomViewState extends State<LoungeRoomView> {
  late final Room _room;
  late final EventsListener<RoomEvent> _listener;

  var _status = _RoomStatus.connecting;
  String _error = '';
  var _micOn = false;
  var _camOn = false;
  var _sharing = false;
  var _shareBusy = false;
  var _canPublish = false;
  var _leaving = false;
  /// Camera state to restore after screen share ends (dual encode freezes phones).
  var _camBeforeShare = false;
  final _tiles = <_Tile>[];
  final _videoTracks = <String, VideoTrack>{};
  final _screenTracks = <String, VideoTrack>{};
  _Presenter? _presenter;

  @override
  void initState() {
    super.initState();
    _room = Room(
      roomOptions: RoomOptions(
        adaptiveStream: true,
        dynacast: true,
        // Lighter than default 1080p - phones choke encoding camera + screen.
        defaultScreenShareCaptureOptions: ScreenShareCaptureOptions(
          maxFrameRate: 15,
          params: VideoParametersPresets.screenShareH720FPS15,
        ),
      ),
    );
    _listener = _room.createListener();
    _bindEvents();
    _connect();
  }

  bool _isScreenShare(TrackPublication pub) =>
      pub.source == TrackSource.screenShareVideo || pub.isScreenShare;

  bool _isCamera(TrackPublication pub) =>
      pub.source == TrackSource.camera ||
      (pub.kind == TrackType.VIDEO && !_isScreenShare(pub));

  void _bindEvents() {
    _listener
      ..on<ParticipantConnectedEvent>((e) => _upsertTile(e.participant))
      ..on<ParticipantDisconnectedEvent>((e) {
        setState(() {
          _tiles.removeWhere((t) => t.id == e.participant.identity);
          _videoTracks.remove(e.participant.identity);
        });
        _clearScreenShare(e.participant.identity);
      })
      ..on<ParticipantMetadataUpdatedEvent>((e) => _upsertTile(e.participant))
      ..on<TrackSubscribedEvent>((e) {
        _ingestPublication(e.publication, e.participant, e.track);
      })
      ..on<TrackUnsubscribedEvent>((e) {
        final id = e.participant.identity;
        if (_isScreenShare(e.publication)) {
          _clearScreenShare(id);
        } else if (e.track is VideoTrack) {
          _videoTracks.remove(id);
          _setCamFlag(id, false);
        } else if (e.track is AudioTrack &&
            e.publication.source != TrackSource.screenShareAudio) {
          _setMicFlag(id, false);
        }
      })
      ..on<TrackMutedEvent>((e) {
        final id = e.participant.identity;
        if (e.publication.kind == TrackType.VIDEO && _isCamera(e.publication)) {
          _setCamFlag(id, false);
          if (identical(e.participant, _room.localParticipant) && mounted) {
            setState(() => _camOn = false);
          }
        } else if (e.publication.kind == TrackType.AUDIO &&
            e.publication.source != TrackSource.screenShareAudio) {
          _setMicFlag(id, false);
          if (identical(e.participant, _room.localParticipant) && mounted) {
            setState(() => _micOn = false);
          }
        }
      })
      ..on<TrackUnmutedEvent>((e) {
        final id = e.participant.identity;
        if (e.publication.kind == TrackType.VIDEO && _isCamera(e.publication)) {
          _setCamFlag(id, true);
          if (identical(e.participant, _room.localParticipant) && mounted) {
            setState(() => _camOn = true);
          }
        } else if (e.publication.kind == TrackType.AUDIO &&
            e.publication.source != TrackSource.screenShareAudio) {
          _setMicFlag(id, true);
          if (identical(e.participant, _room.localParticipant) && mounted) {
            setState(() => _micOn = true);
          }
        }
      })
      ..on<LocalTrackPublishedEvent>((e) {
        final track = e.publication.track;
        final local = _room.localParticipant;
        if (local == null || track == null) return;
        _ingestPublication(e.publication, local, track);
      })
      ..on<LocalTrackUnpublishedEvent>((e) {
        final id = _room.localParticipant?.identity;
        if (id == null) return;
        if (_isScreenShare(e.publication)) {
          _clearScreenShare(id);
        } else if (e.publication.kind == TrackType.VIDEO) {
          _videoTracks.remove(id);
          _setCamFlag(id, false);
        } else if (e.publication.kind == TrackType.AUDIO &&
            e.publication.source != TrackSource.screenShareAudio) {
          _setMicFlag(id, false);
        }
      })
      ..on<RoomDisconnectedEvent>((_) {
        if (_leaving || !mounted) return;
        Get.back();
      });
  }

  void _ingestPublication(
    TrackPublication pub,
    Participant participant,
    Track track,
  ) {
    final id = participant.identity;
    if (track is VideoTrack) {
      if (_isScreenShare(pub)) {
        _screenTracks[id] = track;
        final isLocal = identical(participant, _room.localParticipant);
        if (isLocal && mounted) setState(() => _sharing = true);
        _promotePresenter(participant);
      } else if (_isCamera(pub)) {
        _videoTracks[id] = track;
        _setCamFlag(id, !pub.muted);
      }
    } else if (track is AudioTrack &&
        pub.source != TrackSource.screenShareAudio) {
      _setMicFlag(id, !pub.muted);
    }
  }

  void _promotePresenter(Participant participant) {
    final id = participant.identity;
    final isLocal = identical(participant, _room.localParticipant);
    final name = participant.name.isNotEmpty
        ? participant.name
        : (isLocal ? 'You' : id);
    // Prefer the local share while we are presenting.
    if (_presenter?.isLocal == true && _sharing && !isLocal) return;
    if (!mounted) {
      _presenter = _Presenter(id: id, name: name, isLocal: isLocal);
      return;
    }
    setState(() {
      _presenter = _Presenter(id: id, name: name, isLocal: isLocal);
    });
  }

  void _clearScreenShare(String id) {
    _screenTracks.remove(id);
    final localId = _room.localParticipant?.identity;
    if (id == localId) _sharing = false;
    if (_presenter?.id != id) {
      if (mounted) setState(() {});
      return;
    }
    final nextId = _screenTracks.isEmpty ? null : _screenTracks.keys.first;
    _Presenter? next;
    if (nextId != null) {
      _Tile? tile;
      for (final t in _tiles) {
        if (t.id == nextId) {
          tile = t;
          break;
        }
      }
      next = _Presenter(
        id: nextId,
        name: tile?.name ?? nextId,
        isLocal: tile?.isLocal ?? false,
      );
    }
    if (!mounted) {
      _presenter = next;
      return;
    }
    setState(() => _presenter = next);
  }

  Future<void> _connect() async {
    try {
      final url = AppConfig.resolveLiveKitUrl(widget.config.url);
      await _room.connect(url, widget.config.token);
      final local = _room.localParticipant;
      if (local != null) {
        _upsertTile(local, isLocal: true);
        _canPublish = local.permissions.canPublish;
        if (_canPublish) {
          try {
            await local.setMicrophoneEnabled(true);
            _micOn = true;
          } catch (_) {}
          try {
            await local.setCameraEnabled(true);
            _camOn = true;
          } catch (_) {}
        }
        for (final pub in local.trackPublications.values) {
          final track = pub.track;
          if (track != null) _ingestPublication(pub, local, track);
        }
      }

      for (final p in _room.remoteParticipants.values) {
        _upsertTile(p);
        for (final pub in p.trackPublications.values) {
          final track = pub.track;
          if (track == null || !pub.subscribed) continue;
          _ingestPublication(pub, p, track);
        }
      }

      if (mounted) setState(() => _status = _RoomStatus.connected);
    } catch (e) {
      if (mounted) {
        setState(() {
          _status = _RoomStatus.error;
          _error = e.toString();
        });
      }
    }
  }

  String? _avatarFromMetadata(Participant p) {
    final raw = p.metadata;
    if (raw == null || raw.isEmpty) return null;
    try {
      final decoded = jsonDecode(raw);
      if (decoded is! Map) return null;
      final url = decoded['avatar_url'];
      if (url is String && url.trim().isNotEmpty) return url.trim();
    } catch (_) {}
    return null;
  }

  void _upsertTile(Participant p, {bool isLocal = false}) {
    final existing = _tiles.indexWhere((t) => t.id == p.identity);
    final name =
        p.name.isNotEmpty ? p.name : (isLocal ? 'You' : p.identity);
    final avatarUrl = _avatarFromMetadata(p);
    void apply() {
      if (existing >= 0) {
        _tiles[existing] = _tiles[existing].copyWith(
          name: name,
          avatarUrl: avatarUrl ?? _tiles[existing].avatarUrl,
        );
      } else {
        _tiles.add(_Tile(
          id: p.identity,
          name: name,
          isLocal: isLocal,
          avatarUrl: avatarUrl,
        ));
      }
    }

    if (!mounted) {
      apply();
      return;
    }
    setState(apply);
  }

  void _setCamFlag(String id, bool on) {
    final i = _tiles.indexWhere((t) => t.id == id);
    if (i < 0) return;
    if (!mounted) {
      _tiles[i] = _tiles[i].copyWith(camOn: on);
      return;
    }
    setState(() => _tiles[i] = _tiles[i].copyWith(camOn: on));
  }

  void _setMicFlag(String id, bool on) {
    final i = _tiles.indexWhere((t) => t.id == id);
    if (i < 0) return;
    if (!mounted) {
      _tiles[i] = _tiles[i].copyWith(micOn: on);
      return;
    }
    setState(() => _tiles[i] = _tiles[i].copyWith(micOn: on));
  }

  Future<void> _toggleMic() async {
    final local = _room.localParticipant;
    if (local == null || !_canPublish) return;
    final next = !_micOn;
    try {
      await local.setMicrophoneEnabled(next);
      setState(() => _micOn = next);
    } catch (_) {}
  }

  Future<void> _toggleCam() async {
    final local = _room.localParticipant;
    if (local == null || !_canPublish) return;
    final next = !_camOn;
    try {
      await local.setCameraEnabled(next);
      setState(() => _camOn = next);
      _setCamFlag(local.identity, next);
    } catch (_) {}
  }

  Future<void> _toggleShare() async {
    final local = _room.localParticipant;
    if (local == null || !_canPublish || _shareBusy) return;
    setState(() => _shareBusy = true);
    final next = !_sharing;
    try {
      if (next) {
        // Pause camera while presenting - encoding cam + screen freezes many devices.
        _camBeforeShare = _camOn;
        if (_camOn) {
          try {
            await local.setCameraEnabled(false);
            if (mounted) {
              setState(() => _camOn = false);
              _setCamFlag(local.identity, false);
            }
          } catch (_) {}
        }
        await local.setScreenShareEnabled(
          true,
          captureScreenAudio: false,
          screenShareCaptureOptions: ScreenShareCaptureOptions(
            maxFrameRate: 15,
            params: VideoParametersPresets.screenShareH720FPS15,
          ),
        );
        if (mounted) setState(() => _sharing = true);
      } else {
        await local.setScreenShareEnabled(false);
        if (!mounted) return;
        setState(() => _sharing = false);
        _clearScreenShare(local.identity);
        if (_camBeforeShare) {
          try {
            await local.setCameraEnabled(true);
            if (mounted) {
              setState(() => _camOn = true);
              _setCamFlag(local.identity, true);
            }
          } catch (_) {}
        }
        _camBeforeShare = false;
      }
    } catch (_) {
      if (mounted) setState(() => _sharing = false);
      // Best-effort restore camera if share failed to start.
      if (next && _camBeforeShare) {
        try {
          await local.setCameraEnabled(true);
          if (mounted) {
            setState(() => _camOn = true);
            _setCamFlag(local.identity, true);
          }
        } catch (_) {}
        _camBeforeShare = false;
      }
    } finally {
      if (mounted) setState(() => _shareBusy = false);
    }
  }

  Future<void> _leave() async {
    if (_leaving) return;
    _leaving = true;
    try {
      await _room.localParticipant?.setScreenShareEnabled(false);
    } catch (_) {}
    await _room.disconnect();
    if (mounted) Get.back();
  }

  @override
  void dispose() {
    _leaving = true;
    _listener.dispose();
    _room.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF0F1115),
      body: SafeArea(
        child: Column(
          children: [
            _buildHeader(context),
            Expanded(child: _buildBody(context)),
            if (_status == _RoomStatus.connected) _buildControls(context),
          ],
        ),
      ),
    );
  }

  Widget _buildHeader(BuildContext context) {
    final presenting = _presenter;
    return Padding(
      padding: EdgeInsets.fromLTRB(16.w, 12.h, 16.w, 12.h),
      child: Row(
        children: [
          Container(
            padding: EdgeInsets.symmetric(horizontal: 8.w, vertical: 4.h),
            decoration: BoxDecoration(
              color: redError.withValues(alpha: 0.15),
              borderRadius: BorderRadius.circular(6.r),
            ),
            child: Row(
              children: [
                Container(
                  width: 6.sp,
                  height: 6.sp,
                  decoration: const BoxDecoration(
                    color: redError,
                    shape: BoxShape.circle,
                  ),
                ),
                SizedBox(width: 6.w),
                Text(
                  'LIVE',
                  style: context.specialLabelCapital?.copyWith(
                    color: redError,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
          SizedBox(width: 12.w),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  widget.config.title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: context.h2?.copyWith(color: Colors.white),
                ),
                if (presenting != null)
                  Text(
                    presenting.isLocal
                        ? 'You are presenting'
                        : '${presenting.name} is presenting',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: context.specialCaption2?.copyWith(
                      color: primaryTheme,
                    ),
                  ),
              ],
            ),
          ),
          Text(
            '${_tiles.length} in room',
            style: context.bodyRegular?.copyWith(color: ghost),
          ),
          SizedBox(width: 12.w),
          TextButton(
            onPressed: _leave,
            style: TextButton.styleFrom(foregroundColor: redError),
            child: const Text('Leave'),
          ),
        ],
      ),
    );
  }

  Widget _buildBody(BuildContext context) {
    if (_status == _RoomStatus.connecting) {
      return Center(
        child: Text(
          'Connecting to the room…',
          style: context.bodyRegular?.copyWith(color: ghost),
        ),
      );
    }
    if (_status == _RoomStatus.error) {
      return Center(
        child: Padding(
          padding: EdgeInsets.all(24.w),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                _error.isEmpty ? 'Could not connect to the room.' : _error,
                textAlign: TextAlign.center,
                style: context.bodyRegular?.copyWith(color: redError),
              ),
              SizedBox(height: 16.h),
              ElevatedButton(onPressed: _leave, child: const Text('Close')),
            ],
          ),
        ),
      );
    }

    final presenter = _presenter;
    final screenTrack =
        presenter == null ? null : _screenTracks[presenter.id];

    // Presenter layout as soon as someone is sharing. Never render *local*
    // screen video back into the UI - that capture→preview loop freezes phones.
    if (presenter != null) {
      return Column(
        children: [
          Expanded(
            child: Padding(
              padding: EdgeInsets.fromLTRB(12.w, 0, 12.w, 8.h),
              child: Container(
                decoration: BoxDecoration(
                  color: const Color(0xFF0A0C10),
                  borderRadius: BorderRadius.circular(14.r),
                  border: Border.all(color: const Color(0xFF23262E)),
                ),
                clipBehavior: Clip.antiAlias,
                child: presenter.isLocal
                    ? _buildLocalPresenting(context)
                    : (screenTrack == null
                        ? const SizedBox.expand()
                        : Stack(
                            fit: StackFit.expand,
                            children: [
                              VideoTrackRenderer(
                                screenTrack,
                                fit: VideoViewFit.contain,
                              ),
                              Positioned(
                                left: 10.w,
                                bottom: 10.h,
                                child: Container(
                                  padding: EdgeInsets.symmetric(
                                    horizontal: 10.w,
                                    vertical: 4.h,
                                  ),
                                  decoration: BoxDecoration(
                                    color: Colors.black.withValues(alpha: 0.6),
                                    borderRadius: BorderRadius.circular(999),
                                  ),
                                  child: Text(
                                    "${presenter.name}'s screen",
                                    style: context.specialCaption2?.copyWith(
                                      color: Colors.white,
                                    ),
                                  ),
                                ),
                              ),
                            ],
                          )),
              ),
            ),
          ),
          SizedBox(
            height: 112.h,
            child: ListView.separated(
              padding: EdgeInsets.fromLTRB(12.w, 0, 12.w, 8.h),
              scrollDirection: Axis.horizontal,
              itemCount: _tiles.length,
              separatorBuilder: (_, __) => SizedBox(width: 8.w),
              itemBuilder: (_, i) => SizedBox(
                width: 140.w,
                child: _buildTile(context, _tiles[i], compact: true),
              ),
            ),
          ),
        ],
      );
    }

    return GridView.builder(
      padding: EdgeInsets.all(12.w),
      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: _tiles.length <= 1 ? 1 : (_tiles.length <= 4 ? 2 : 3),
        crossAxisSpacing: 8.w,
        mainAxisSpacing: 8.h,
        childAspectRatio: 3 / 4,
      ),
      itemCount: _tiles.length,
      itemBuilder: (_, i) => _buildTile(context, _tiles[i]),
    );
  }

  Widget _buildLocalPresenting(BuildContext context) {
    return Center(
      child: Padding(
        padding: EdgeInsets.all(24.w),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.present_to_all, size: 40.sp, color: Colors.white70),
            SizedBox(height: 12.h),
            Text(
              "You're presenting",
              style: context.h2?.copyWith(color: Colors.white),
              textAlign: TextAlign.center,
            ),
            SizedBox(height: 6.h),
            Text(
              'Others in the room can see your screen',
              style: context.bodyRegular?.copyWith(color: ghost),
              textAlign: TextAlign.center,
            ),
            SizedBox(height: 16.h),
            ElevatedButton(
              onPressed: _shareBusy ? null : _toggleShare,
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFFEA580C),
                foregroundColor: Colors.white,
              ),
              child: const Text('Stop sharing'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildTile(BuildContext context, _Tile tile, {bool compact = false}) {
    final track = _videoTracks[tile.id];
    final initials = _initials(tile.name);
    final showVideo = track != null && tile.camOn;
    final photo = tile.avatarUrl;
    final avatarSize = compact ? 40.r : 72.r;

    return Container(
      decoration: BoxDecoration(
        color: const Color(0xFF1A1D24),
        borderRadius: BorderRadius.circular(12.r),
        gradient: showVideo
            ? null
            : const LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [Color(0xFF232833), Color(0xFF151820)],
              ),
      ),
      clipBehavior: Clip.antiAlias,
      child: Stack(
        fit: StackFit.expand,
        children: [
          if (showVideo)
            VideoTrackRenderer(
              track,
              fit: VideoViewFit.cover,
            )
          else
            Center(
              child: Container(
                width: avatarSize,
                height: avatarSize,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: primaryTheme,
                  border: Border.all(
                    color: Colors.white.withValues(alpha: 0.12),
                    width: 2,
                  ),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.35),
                      blurRadius: 18,
                      offset: const Offset(0, 6),
                    ),
                  ],
                  image: (photo != null && photo.isNotEmpty)
                      ? DecorationImage(
                          image: NetworkImage(photo),
                          fit: BoxFit.cover,
                        )
                      : null,
                ),
                alignment: Alignment.center,
                child: (photo != null && photo.isNotEmpty)
                    ? null
                    : Text(
                        initials,
                        style: (compact ? context.specialCaption2 : context.h2)
                            ?.copyWith(color: Colors.white),
                      ),
              ),
            ),
          Positioned(
            left: 8.w,
            right: 8.w,
            bottom: 8.h,
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    tile.isLocal ? '${tile.name} (you)' : tile.name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: context.specialCaption2?.copyWith(
                      color: Colors.white,
                    ),
                  ),
                ),
                if (!tile.micOn)
                  Icon(Icons.mic_off, size: 14.sp, color: Colors.white70),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildControls(BuildContext context) {
    return Container(
      padding: EdgeInsets.fromLTRB(16.w, 12.h, 16.w, 16.h),
      decoration: const BoxDecoration(
        border: Border(top: BorderSide(color: Color(0xFF23262E))),
      ),
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            if (_canPublish) ...[
              _ctl(
                icon: _micOn ? Icons.mic : Icons.mic_off,
                label: _micOn ? 'Mute' : 'Unmute',
                on: _micOn,
                onTap: _toggleMic,
              ),
              SizedBox(width: 10.w),
              _ctl(
                icon: _camOn ? Icons.videocam : Icons.videocam_off,
                label: _camOn ? 'Stop video' : 'Start video',
                on: _camOn,
                onTap: _toggleCam,
              ),
              SizedBox(width: 10.w),
              _ctl(
                icon: Icons.screen_share_outlined,
                label: _sharing ? 'Stop share' : 'Share',
                on: _sharing,
                stop: _sharing,
                busy: _shareBusy,
                onTap: _toggleShare,
              ),
              SizedBox(width: 10.w),
            ] else
              Padding(
                padding: EdgeInsets.only(right: 12.w),
                child: Text(
                  'Viewer mode',
                  style: context.bodyRegular?.copyWith(color: ghost),
                ),
              ),
            _ctl(
              icon: Icons.call_end,
              label: 'Leave',
              on: false,
              danger: true,
              onTap: _leave,
            ),
          ],
        ),
      ),
    );
  }

  Widget _ctl({
    required IconData icon,
    required String label,
    required bool on,
    required VoidCallback onTap,
    bool danger = false,
    bool stop = false,
    bool busy = false,
  }) {
    final bg = danger
        ? redError
        : stop
            ? const Color(0xFFEA580C)
            : (on ? primaryTheme : const Color(0xFF23262E));
    return Opacity(
      opacity: busy ? 0.55 : 1,
      child: GestureDetector(
        onTap: busy ? null : onTap,
        child: Container(
          padding: EdgeInsets.symmetric(horizontal: 14.w, vertical: 10.h),
          decoration: BoxDecoration(
            color: bg,
            borderRadius: BorderRadius.circular(10.r),
          ),
          child: Row(
            children: [
              Icon(icon, size: 18.sp, color: Colors.white),
              SizedBox(width: 6.w),
              Text(
                label,
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 12.sp,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  String _initials(String name) {
    final parts = name.trim().split(RegExp(r'\s+'));
    if (parts.isEmpty) return '?';
    final a = parts.first.isNotEmpty ? parts.first[0] : '';
    final b = parts.length > 1 && parts[1].isNotEmpty ? parts[1][0] : '';
    final out = '$a$b'.toUpperCase();
    return out.isEmpty ? '?' : out;
  }
}

enum _RoomStatus { connecting, connected, error }

class _Presenter {
  final String id;
  final String name;
  final bool isLocal;

  const _Presenter({
    required this.id,
    required this.name,
    required this.isLocal,
  });
}

class _Tile {
  final String id;
  final String name;
  final bool isLocal;
  final bool camOn;
  final bool micOn;
  final String? avatarUrl;

  const _Tile({
    required this.id,
    required this.name,
    this.isLocal = false,
    this.camOn = false,
    this.micOn = false,
    this.avatarUrl,
  });

  _Tile copyWith({
    String? name,
    bool? camOn,
    bool? micOn,
    String? avatarUrl,
  }) {
    return _Tile(
      id: id,
      name: name ?? this.name,
      isLocal: isLocal,
      camOn: camOn ?? this.camOn,
      micOn: micOn ?? this.micOn,
      avatarUrl: avatarUrl ?? this.avatarUrl,
    );
  }
}
