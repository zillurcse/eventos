class SessionPanelMessage {
  final int id;
  final String body;
  final String author;
  final String? authorImage;
  final String? authorId;
  final String authorRole;
  final bool isOfficial;
  final bool isMine;
  final int upvotes;
  final bool voted;
  final bool isAnswered;
  final bool isHidden;
  final bool isPinned;
  final String status;
  final bool canDelete;
  final String? createdAt;
  final List<SessionPanelMessage> replies;

  const SessionPanelMessage({
    this.id = 0,
    this.body = '',
    this.author = '',
    this.authorImage,
    this.authorId,
    this.authorRole = 'attendee',
    this.isOfficial = false,
    this.isMine = false,
    this.upvotes = 0,
    this.voted = false,
    this.isAnswered = false,
    this.isHidden = false,
    this.isPinned = false,
    this.status = 'published',
    this.canDelete = false,
    this.createdAt,
    this.replies = const [],
  });

  factory SessionPanelMessage.fromJson(Map<String, dynamic> json) {
    return SessionPanelMessage(
      id: _toInt(json['id']),
      body: (json['body'] ?? '').toString(),
      author: (json['author'] ?? '').toString(),
      authorImage: json['author_image']?.toString(),
      authorId: json['author_id']?.toString(),
      authorRole: (json['author_role'] ?? 'attendee').toString(),
      isOfficial: json['is_official'] == true,
      isMine: json['is_mine'] == true,
      upvotes: _toInt(json['upvotes']),
      voted: json['voted'] == true,
      isAnswered: json['is_answered'] == true,
      isHidden: json['is_hidden'] == true,
      isPinned: json['is_pinned'] == true,
      status: (json['status'] ?? 'published').toString(),
      canDelete: json['can_delete'] == true,
      createdAt: json['created_at']?.toString(),
      replies: (json['replies'] as List? ?? [])
          .whereType<Map>()
          .map((e) => SessionPanelMessage.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
    );
  }

  static int _toInt(dynamic v) {
    if (v is int) return v;
    if (v is num) return v.toInt();
    return int.tryParse(v?.toString() ?? '') ?? 0;
  }
}

class SessionPollOption {
  final String id;
  final String text;
  final int votes;

  const SessionPollOption({
    this.id = '',
    this.text = '',
    this.votes = 0,
  });

  factory SessionPollOption.fromJson(Map<String, dynamic> json) {
    return SessionPollOption(
      id: (json['id'] ?? '').toString(),
      text: (json['text'] ?? '').toString(),
      votes: SessionPanelMessage._toInt(json['votes']),
    );
  }
}

class SessionPoll {
  final int id;
  final String question;
  final List<SessionPollOption> options;
  final int totalVotes;
  final String status;
  final bool isActive;
  final bool showResults;
  final bool resultsVisible;
  final String? myVote;

  const SessionPoll({
    this.id = 0,
    this.question = '',
    this.options = const [],
    this.totalVotes = 0,
    this.status = 'draft',
    this.isActive = false,
    this.showResults = false,
    this.resultsVisible = false,
    this.myVote,
  });

  factory SessionPoll.fromJson(Map<String, dynamic> json) {
    return SessionPoll(
      id: SessionPanelMessage._toInt(json['id']),
      question: (json['question'] ?? '').toString(),
      options: (json['options'] as List? ?? [])
          .whereType<Map>()
          .map((e) => SessionPollOption.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
      totalVotes: SessionPanelMessage._toInt(json['total_votes']),
      status: (json['status'] ?? 'draft').toString(),
      isActive: json['is_active'] == true,
      showResults: json['show_results'] == true,
      resultsVisible: json['results_visible'] == true,
      myVote: json['my_vote']?.toString(),
    );
  }
}

class SessionPanelAttendee {
  final String id;
  final String name;
  final String? imageUrl;
  final String? headline;
  final bool isSpeaker;
  final bool isMuted;
  final bool online;

  const SessionPanelAttendee({
    this.id = '',
    this.name = '',
    this.imageUrl,
    this.headline,
    this.isSpeaker = false,
    this.isMuted = false,
    this.online = false,
  });

  factory SessionPanelAttendee.fromJson(Map<String, dynamic> json) {
    return SessionPanelAttendee(
      id: (json['id'] ?? '').toString(),
      name: (json['name'] ?? '').toString(),
      imageUrl: json['image_url']?.toString(),
      headline: json['headline']?.toString(),
      isSpeaker: json['is_speaker'] == true,
      isMuted: json['is_muted'] == true,
      online: json['online'] == true,
    );
  }
}
