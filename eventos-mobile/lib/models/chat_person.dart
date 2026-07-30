/// Participant card returned by V1 chat APIs (`with` / `profile`).
class ChatPerson {
  final String id;
  final String name;
  final String role;
  final String company;
  final String jobTitle;
  final String? avatarUrl;

  const ChatPerson({
    this.id = '',
    this.name = '',
    this.role = 'attendee',
    this.company = '',
    this.jobTitle = '',
    this.avatarUrl,
  });

  factory ChatPerson.fromJson(Map<String, dynamic> json) {
    return ChatPerson(
      id: json['id']?.toString() ?? '',
      name: json['name']?.toString() ?? 'Attendee',
      role: json['role']?.toString() ?? 'attendee',
      company: json['company']?.toString() ?? '',
      jobTitle: json['job_title']?.toString() ?? '',
      avatarUrl: json['avatar_url']?.toString(),
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'role': role,
        'company': company,
        'job_title': jobTitle,
        'avatar_url': avatarUrl,
      };
}
