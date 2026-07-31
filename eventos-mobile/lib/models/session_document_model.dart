class SessionDocumentModel {
  final String name;
  final String url;

  const SessionDocumentModel({
    this.name = '',
    this.url = '',
  });

  factory SessionDocumentModel.fromJson(Map<String, dynamic> json) {
    return SessionDocumentModel(
      name: (json['name'] ?? '').toString(),
      url: (json['url'] ?? '').toString(),
    );
  }
}
