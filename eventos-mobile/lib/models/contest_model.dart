class ContestAttachment {
  final String kind; // image | video
  final String url;
  final String? name;

  const ContestAttachment({
    required this.kind,
    required this.url,
    this.name,
  });

  factory ContestAttachment.fromJson(Map<String, dynamic> json) {
    return ContestAttachment(
      kind: (json['kind'] ?? 'image').toString(),
      url: (json['url'] ?? '').toString(),
      name: json['name']?.toString(),
    );
  }

  Map<String, dynamic> toJson() => {
        'kind': kind,
        'url': url,
        if (name != null) 'name': name,
      };

  bool get isVideo => kind == 'video';
}

class ContestEntry {
  final String id;
  final String kind; // entry | comment
  final String? body;
  final List<ContestAttachment> attachments;
  final String status; // pending | approved | rejected
  final bool isWinner;
  final int? rank;
  final int awardedPoints;
  final int likeCount;
  final int commentCount;
  final String author;
  final String? authorAvatar;
  final String? authorHeadline;
  final bool isMine;
  bool liked;
  final String? createdAt;

  ContestEntry({
    required this.id,
    required this.kind,
    this.body,
    this.attachments = const [],
    required this.status,
    this.isWinner = false,
    this.rank,
    this.awardedPoints = 0,
    this.likeCount = 0,
    this.commentCount = 0,
    required this.author,
    this.authorAvatar,
    this.authorHeadline,
    this.isMine = false,
    this.liked = false,
    this.createdAt,
  });

  factory ContestEntry.fromJson(Map<String, dynamic> json) {
    final rawAttachments = json['attachments'];
    return ContestEntry(
      id: (json['id'] ?? '').toString(),
      kind: (json['kind'] ?? 'entry').toString(),
      body: json['body']?.toString(),
      attachments: rawAttachments is List
          ? rawAttachments
              .whereType<Map>()
              .map((e) =>
                  ContestAttachment.fromJson(Map<String, dynamic>.from(e)))
              .toList()
          : const [],
      status: (json['status'] ?? 'approved').toString(),
      isWinner: json['is_winner'] == true,
      rank: json['rank'] is int
          ? json['rank'] as int
          : int.tryParse('${json['rank'] ?? ''}'),
      awardedPoints: _asInt(json['awarded_points']),
      likeCount: _asInt(json['like_count']),
      commentCount: _asInt(json['comment_count']),
      author: (json['author'] ?? 'Attendee').toString(),
      authorAvatar: json['author_avatar']?.toString(),
      authorHeadline: json['author_headline']?.toString(),
      isMine: json['is_mine'] == true,
      liked: json['liked'] == true,
      createdAt: json['created_at']?.toString(),
    );
  }

  ContestEntry copyWith({
    int? likeCount,
    int? commentCount,
    bool? liked,
  }) {
    return ContestEntry(
      id: id,
      kind: kind,
      body: body,
      attachments: attachments,
      status: status,
      isWinner: isWinner,
      rank: rank,
      awardedPoints: awardedPoints,
      likeCount: likeCount ?? this.likeCount,
      commentCount: commentCount ?? this.commentCount,
      author: author,
      authorAvatar: authorAvatar,
      authorHeadline: authorHeadline,
      isMine: isMine,
      liked: liked ?? this.liked,
      createdAt: createdAt,
    );
  }
}

class Contest {
  final String id;
  final String title;
  final String contestType; // entry | response
  final String phase; // upcoming | ongoing | ended
  final String? description;
  final String? descriptionFileUrl;
  final String? descriptionFileName;
  final String? startsAt;
  final String? endsAt;
  final String? bannerUrl;
  final String? caption;
  final int characterLimit;
  final int points;
  final bool allowPhotos;
  final bool allowVideos;
  final bool allowSelfie;
  final bool attachMandatory;
  final bool allowMultipleEntries;
  final bool moderated;
  final bool canSeeOthersEntries;
  final bool canSeeOtherComments;
  final String winnerChooser; // admin | most_likes
  final int winnerNumber;
  final int winningPoints;
  final int entryCount;
  final int myEntryCount;
  final bool canEnter;
  final List<ContestEntry> winners;

  const Contest({
    required this.id,
    required this.title,
    required this.contestType,
    required this.phase,
    this.description,
    this.descriptionFileUrl,
    this.descriptionFileName,
    this.startsAt,
    this.endsAt,
    this.bannerUrl,
    this.caption,
    this.characterLimit = 500,
    this.points = 0,
    this.allowPhotos = false,
    this.allowVideos = false,
    this.allowSelfie = false,
    this.attachMandatory = false,
    this.allowMultipleEntries = false,
    this.moderated = false,
    this.canSeeOthersEntries = true,
    this.canSeeOtherComments = true,
    this.winnerChooser = 'admin',
    this.winnerNumber = 1,
    this.winningPoints = 0,
    this.entryCount = 0,
    this.myEntryCount = 0,
    this.canEnter = false,
    this.winners = const [],
  });

  factory Contest.fromJson(Map<String, dynamic> json) {
    final rawWinners = json['winners'];
    return Contest(
      id: (json['id'] ?? '').toString(),
      title: (json['title'] ?? '').toString(),
      contestType: (json['contest_type'] ?? 'entry').toString(),
      phase: (json['phase'] ?? 'upcoming').toString(),
      description: json['description']?.toString(),
      descriptionFileUrl: json['description_file_url']?.toString(),
      descriptionFileName: json['description_file_name']?.toString(),
      startsAt: json['starts_at']?.toString(),
      endsAt: json['ends_at']?.toString(),
      bannerUrl: json['banner_url']?.toString(),
      caption: json['caption']?.toString(),
      characterLimit: _asInt(json['character_limit'], fallback: 500),
      points: _asInt(json['points']),
      allowPhotos: json['allow_photos'] == true,
      allowVideos: json['allow_videos'] == true,
      allowSelfie: json['allow_selfie'] == true,
      attachMandatory: json['attach_mandatory'] == true,
      allowMultipleEntries: json['allow_multiple_entries'] == true,
      moderated: json['moderated'] == true,
      canSeeOthersEntries: json['can_see_others_entries'] != false,
      canSeeOtherComments: json['can_see_other_comments'] != false,
      winnerChooser: (json['winner_chooser'] ?? 'admin').toString(),
      winnerNumber: _asInt(json['winner_number'], fallback: 1),
      winningPoints: _asInt(json['winning_points']),
      entryCount: _asInt(json['entry_count']),
      myEntryCount: _asInt(json['my_entry_count']),
      canEnter: json['can_enter'] == true,
      winners: rawWinners is List
          ? rawWinners
              .whereType<Map>()
              .map((e) => ContestEntry.fromJson(Map<String, dynamic>.from(e)))
              .toList()
          : const [],
    );
  }

  bool get isEnded => phase == 'ended';
  bool get isUpcoming => phase == 'upcoming';
  bool get isOngoing => phase == 'ongoing';
  bool get isEntryType => contestType == 'entry';

  String get countdownTarget => isUpcoming ? (startsAt ?? '') : (endsAt ?? '');

  String get statusLabel {
    if (isEnded) return 'Contest ended';
    if (isUpcoming) return 'Contest starts in';
    return 'Contest ends in';
  }

  Contest copyWith({
    int? entryCount,
    int? myEntryCount,
    bool? canEnter,
    List<ContestEntry>? winners,
  }) {
    return Contest(
      id: id,
      title: title,
      contestType: contestType,
      phase: phase,
      description: description,
      descriptionFileUrl: descriptionFileUrl,
      descriptionFileName: descriptionFileName,
      startsAt: startsAt,
      endsAt: endsAt,
      bannerUrl: bannerUrl,
      caption: caption,
      characterLimit: characterLimit,
      points: points,
      allowPhotos: allowPhotos,
      allowVideos: allowVideos,
      allowSelfie: allowSelfie,
      attachMandatory: attachMandatory,
      allowMultipleEntries: allowMultipleEntries,
      moderated: moderated,
      canSeeOthersEntries: canSeeOthersEntries,
      canSeeOtherComments: canSeeOtherComments,
      winnerChooser: winnerChooser,
      winnerNumber: winnerNumber,
      winningPoints: winningPoints,
      entryCount: entryCount ?? this.entryCount,
      myEntryCount: myEntryCount ?? this.myEntryCount,
      canEnter: canEnter ?? this.canEnter,
      winners: winners ?? this.winners,
    );
  }
}

class ContestCountdown {
  final int days;
  final int hours;
  final int mins;

  const ContestCountdown({
    required this.days,
    required this.hours,
    required this.mins,
  });

  static ContestCountdown? fromIso(String? iso, [DateTime? now]) {
    if (iso == null || iso.isEmpty) return null;
    final target = DateTime.tryParse(iso);
    if (target == null) return null;
    final ms = target.millisecondsSinceEpoch -
        (now ?? DateTime.now()).millisecondsSinceEpoch;
    if (ms <= 0) return null;
    final totalMins = ms ~/ 60000;
    return ContestCountdown(
      days: totalMins ~/ 1440,
      hours: (totalMins ~/ 60) % 24,
      mins: totalMins % 60,
    );
  }
}

int _asInt(dynamic value, {int fallback = 0}) {
  if (value is int) return value;
  return int.tryParse('$value') ?? fallback;
}
