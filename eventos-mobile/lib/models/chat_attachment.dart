class ChatAttachment {
  final String kind;
  final String url;
  final String? name;

  const ChatAttachment({
    required this.kind,
    required this.url,
    this.name,
  });

  factory ChatAttachment.fromJson(Map<String, dynamic> json) {
    return ChatAttachment(
      kind: json['kind']?.toString() ?? 'file',
      url: json['url']?.toString() ?? '',
      name: json['name']?.toString(),
    );
  }

  Map<String, dynamic> toJson() => {
        'kind': kind,
        'url': url,
        if (name != null) 'name': name,
      };
}
