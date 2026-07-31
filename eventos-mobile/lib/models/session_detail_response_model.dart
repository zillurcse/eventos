import '../utils/helpers/type_helper.dart';
import 'session_day_model.dart';
import 'session_document_model.dart';
import 'reception_speaker_model.dart';
import 'exhibitor_model.dart';

class SessionDetailModel {
  final int id;
  final String uuid;
  final String startTime;
  final String endTime;
  final String? startsAt;
  final String? endsAt;
  final String? status;
  final String? timezone;
  final String title;
  final String sessionPlace;
  final String description;
  final String logo;
  final String? file;
  final List<SessionDocumentModel> documents;
  final List<String> tags;
  final bool isFeatured;
  final bool isAllowedToRate;
  final bool isAllowToRegister;
  final bool isStream;
  final String? streamLink;
  final String? whoWillHost;
  final String? vimeoLiveId;
  final String? onDemandRecordingLink;
  final bool canLiveChat;
  final bool canQa;
  final bool canLivePolls;
  final bool canAttendeeList;
  final List<ReceptionSpeakerModel> speakers;
  final List<ExhibitorModel> sponsors;
  final SessionDayModel day;
  final String track;

  const SessionDetailModel({
    this.id = 0,
    this.uuid = '',
    this.startTime = '',
    this.endTime = '',
    this.startsAt,
    this.endsAt,
    this.status,
    this.timezone,
    this.title = '',
    this.sessionPlace = '',
    this.description = '',
    this.logo = '',
    this.file,
    this.documents = const [],
    this.tags = const [],
    this.isFeatured = false,
    this.isAllowedToRate = false,
    this.isAllowToRegister = false,
    this.isStream = false,
    this.streamLink,
    this.whoWillHost,
    this.vimeoLiveId,
    this.onDemandRecordingLink,
    this.canLiveChat = false,
    this.canQa = false,
    this.canLivePolls = false,
    this.canAttendeeList = false,
    this.speakers = const [],
    this.sponsors = const [],
    this.day = const SessionDayModel(),
    this.track = '',
  });

  factory SessionDetailModel.fromJson(Map<String, dynamic> json) {
    return SessionDetailModel(
      id: TypeHelper.toInt(json['id']),
      uuid: (json['uuid'] ?? json['id'] ?? '').toString(),
      startTime: json['start_time'] as String? ?? '',
      endTime: json['end_time'] as String? ?? '',
      startsAt: json['starts_at']?.toString(),
      endsAt: json['ends_at']?.toString(),
      status: json['status']?.toString(),
      timezone: json['timezone']?.toString(),
      title: json['title'] as String? ?? '',
      sessionPlace: json['session_place'] as String? ?? '',
      description: json['description'] as String? ?? '',
      logo: json['logo'] as String? ?? '',
      file: json['file'] as String?,
      documents: (json['documents'] as List? ?? [])
          .whereType<Map>()
          .map((e) => SessionDocumentModel.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
      tags: (json['tags'] as List? ?? []).map((e) => e.toString()).toList(),
      isFeatured: TypeHelper.toBool(json['is_featured']),
      isAllowedToRate: TypeHelper.toBool(json['is_allowed_to_rate']),
      isAllowToRegister: TypeHelper.toBool(json['is_allow_to_register']),
      isStream: TypeHelper.toBool(json['is_stream']),
      streamLink: json['stream_link'] as String?,
      whoWillHost: json['who_will_host']?.toString(),
      vimeoLiveId: json['vimeo_live_id']?.toString(),
      onDemandRecordingLink: json['on_demand_recording_link']?.toString(),
      canLiveChat: TypeHelper.toBool(json['can_live_chat']),
      canQa: TypeHelper.toBool(json['can_qa']),
      canLivePolls: TypeHelper.toBool(json['can_live_polls']),
      canAttendeeList: TypeHelper.toBool(json['can_attendee_list']),
      speakers: (json['speakers'] as List? ?? [])
          .map((e) => ReceptionSpeakerModel.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
      sponsors: (json['sponsors'] as List? ?? [])
          .map((e) => ExhibitorModel.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
      day: json['day'] is Map
          ? SessionDayModel.fromJson(Map<String, dynamic>.from(json['day']))
          : const SessionDayModel(),
      track: json['track'] as String? ?? '',
    );
  }
}
