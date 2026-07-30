import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get.dart';
import 'package:livekit_client/livekit_client.dart';

import '../../../models/lounge_model.dart';
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
  var _canPublish = false;
  var _leaving = false;
  final _tiles = <_Tile>[];
  final _videoTracks = <String, VideoTrack>{};

  @override
  void initState() {
    super.initState();
    _room = Room(
      roomOptions: const RoomOptions(
        adaptiveStream: true,
        dynacast: true,
      ),
    );
    _listener = _room.createListener();
    _bindEvents();
    _connect();
  }

  void _bindEvents() {
    _listener
      ..on<ParticipantConnectedEvent>((e) => _upsertTile(e.participant))
      ..on<ParticipantDisconnectedEvent>((e) {
        setState(() {
          _tiles.removeWhere((t) => t.id == e.participant.identity);
          _videoTracks.remove(e.participant.identity);
        });
      })
      ..on<TrackSubscribedEvent>((e) {
        final id = e.participant.identity;
        if (e.track is VideoTrack) {
          setState(() {
            _videoTracks[id] = e.track as VideoTrack;
            _setCamFlag(id, true);
          });
        } else if (e.track is AudioTrack) {
          _setMicFlag(id, true);
        }
      })
      ..on<TrackUnsubscribedEvent>((e) {
        final id = e.participant.identity;
        if (e.track is VideoTrack) {
          setState(() {
            _videoTracks.remove(id);
            _setCamFlag(id, false);
          });
        } else if (e.track is AudioTrack) {
          _setMicFlag(id, false);
        }
      })
      ..on<LocalTrackPublishedEvent>((e) {
        final id = _room.localParticipant?.identity;
        if (id == null) return;
        final track = e.publication.track;
        if (track is VideoTrack) {
          setState(() {
            _videoTracks[id] = track as VideoTrack;
            _setCamFlag(id, true);
          });
        } else if (track is AudioTrack) {
          _setMicFlag(id, true);
        }
      })
      ..on<LocalTrackUnpublishedEvent>((e) {
        final id = _room.localParticipant?.identity;
        if (id == null) return;
        if (e.publication.kind == TrackType.VIDEO) {
          setState(() {
            _videoTracks.remove(id);
            _setCamFlag(id, false);
          });
        } else if (e.publication.kind == TrackType.AUDIO) {
          _setMicFlag(id, false);
        }
      })
      ..on<RoomDisconnectedEvent>((_) {
        if (_leaving || !mounted) return;
        Get.back();
      });
  }

  Future<void> _connect() async {
    try {
      await _room.connect(widget.config.url, widget.config.token);
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
      }

      for (final p in _room.remoteParticipants.values) {
        _upsertTile(p);
        for (final pub in p.trackPublications.values) {
          final track = pub.track;
          if (track == null || !pub.subscribed) continue;
          if (track is VideoTrack) {
            _videoTracks[p.identity] = track as VideoTrack;
            _setCamFlag(p.identity, true);
          } else if (track is AudioTrack) {
            _setMicFlag(p.identity, true);
          }
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

  void _upsertTile(Participant p, {bool isLocal = false}) {
    final existing = _tiles.indexWhere((t) => t.id == p.identity);
    final name =
        p.name.isNotEmpty ? p.name : (isLocal ? 'You' : p.identity);
    void apply() {
      if (existing >= 0) {
        _tiles[existing] = _tiles[existing].copyWith(name: name);
      } else {
        _tiles.add(_Tile(id: p.identity, name: name, isLocal: isLocal));
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
    } catch (_) {}
  }

  Future<void> _leave() async {
    if (_leaving) return;
    _leaving = true;
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
            child: Text(
              widget.config.title,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: context.h2?.copyWith(color: Colors.white),
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

  Widget _buildTile(BuildContext context, _Tile tile) {
    final track = _videoTracks[tile.id];
    final initials = _initials(tile.name);

    return Container(
      decoration: BoxDecoration(
        color: const Color(0xFF1A1D24),
        borderRadius: BorderRadius.circular(12.r),
      ),
      clipBehavior: Clip.antiAlias,
      child: Stack(
        fit: StackFit.expand,
        children: [
          if (track != null && tile.camOn)
            VideoTrackRenderer(
              track,
              fit: VideoViewFit.cover,
            )
          else
            Center(
              child: CircleAvatar(
                radius: 28.r,
                backgroundColor: primaryTheme,
                child: Text(
                  initials,
                  style: context.h2?.copyWith(color: Colors.white),
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
            SizedBox(width: 12.w),
            _ctl(
              icon: _camOn ? Icons.videocam : Icons.videocam_off,
              label: _camOn ? 'Stop video' : 'Start video',
              on: _camOn,
              onTap: _toggleCam,
            ),
            SizedBox(width: 12.w),
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
    );
  }

  Widget _ctl({
    required IconData icon,
    required String label,
    required bool on,
    required VoidCallback onTap,
    bool danger = false,
  }) {
    final bg = danger
        ? redError
        : (on ? primaryTheme : const Color(0xFF23262E));
    return GestureDetector(
      onTap: onTap,
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

class _Tile {
  final String id;
  final String name;
  final bool isLocal;
  final bool camOn;
  final bool micOn;

  const _Tile({
    required this.id,
    required this.name,
    this.isLocal = false,
    this.camOn = false,
    this.micOn = false,
  });

  _Tile copyWith({
    String? name,
    bool? camOn,
    bool? micOn,
  }) {
    return _Tile(
      id: id,
      name: name ?? this.name,
      isLocal: isLocal,
      camOn: camOn ?? this.camOn,
      micOn: micOn ?? this.micOn,
    );
  }
}
