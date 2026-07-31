class MyEvent {
  final int? id;
  final String uuid;
  final String name;
  final String slug;
  final String subdomain;
  final String? organizationName;
  final String? logoUrl;
  final String? startsAt;
  final String? endsAt;
  final List<String> roles;

  const MyEvent({
    this.id,
    required this.uuid,
    required this.name,
    this.slug = '',
    required this.subdomain,
    this.organizationName,
    this.logoUrl,
    this.startsAt,
    this.endsAt,
    this.roles = const [],
  });

  factory MyEvent.fromJson(Map<String, dynamic> json) {
    final rolesRaw = json['roles'];
    return MyEvent(
      id: json['id'] is int
          ? json['id'] as int
          : int.tryParse(json['id']?.toString() ?? ''),
      uuid: json['uuid']?.toString() ?? json['id']?.toString() ?? '',
      name: json['name']?.toString() ?? '',
      slug: json['slug']?.toString() ?? '',
      subdomain: json['subdomain']?.toString() ?? '',
      organizationName: json['organization_name']?.toString(),
      logoUrl: json['logo_url']?.toString(),
      startsAt: json['starts_at']?.toString(),
      endsAt: json['ends_at']?.toString(),
      roles: rolesRaw is List
          ? rolesRaw.map((e) => e.toString()).toList()
          : const [],
    );
  }
}
