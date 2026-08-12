import 'package:expouse/utils/config/app_config.dart';
import 'package:expouse/utils/helpers/type_helper.dart';
import 'speaker_note_model.dart';
import 'session_model.dart';

class SpeakerDetailModel {
  final int id;
  final String name;
  final String? image;
  final String? email;
  final String designation;
  final String? category;
  final String? presentationTitle;
  final String? presentationFile;
  final String? facebook;
  final String? linkedin;
  final String? twitter;
  final String? instagram;
  final String? whatsapp;
  final String? bio;
  final List<String> tags;
  final bool allowRating;
  final bool isFeatured;
  final bool isLoved;
  final bool haveNotes;
  final List<SpeakerNoteModel> notes;
  final List<SessionModel> sessions;

  const SpeakerDetailModel({
    this.id = 0,
    this.name = '',
    this.image,
    this.email,
    this.designation = '',
    this.category,
    this.presentationTitle,
    this.presentationFile,
    this.facebook,
    this.linkedin,
    this.twitter,
    this.instagram,
    this.whatsapp,
    this.bio,
    this.tags = const [],
    this.allowRating = false,
    this.isFeatured = false,
    this.isLoved = false,
    this.haveNotes = false,
    this.notes = const [],
    this.sessions = const [],
  });

  factory SpeakerDetailModel.fromJson(Map<String, dynamic> json) {
    final String rawImage = json['image'] as String? ?? '';
    final String rawImageUrl = json['image_url'] as String? ?? '';
    final String finalRaw = rawImageUrl.isNotEmpty ? rawImageUrl : rawImage;
    final String? imageUrl =
        finalRaw.isEmpty ? null : AppConfig.resolveAssetUrl(finalRaw);

    return SpeakerDetailModel(
      id: TypeHelper.toInt(json['id']),
      name: json['name'] as String? ?? '',
      image: imageUrl,
      email: json['email'] as String?,
      designation: json['designation'] as String? ?? '',
      category: json['category'] as String?,
      presentationTitle: json['presentation_title'] as String?,
      presentationFile: json['presentation_file'] as String?,
      facebook: json['facebook'] as String?,
      linkedin: json['linkedin'] as String?,
      twitter: json['twitter'] as String?,
      instagram: json['instagram'] as String?,
      whatsapp: json['whatsapp'] as String?,
      bio: json['bio'] as String?,
      tags: (json['tags'] as List? ?? []).map((e) => e.toString()).toList(),
      allowRating: TypeHelper.toBool(json['allow_reating']),
      isFeatured: TypeHelper.toBool(json['is_featured']),
      isLoved: TypeHelper.toBool(json['is_loved']),
      haveNotes: TypeHelper.toBool(json['haveNotes']),
      notes: (json['notes'] as List? ?? [])
          .map((e) => SpeakerNoteModel.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
      sessions: (json['sessions'] as List? ?? json['schedules'] as List? ?? [])
          .map((e) => SessionModel.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
    );
  }
}
