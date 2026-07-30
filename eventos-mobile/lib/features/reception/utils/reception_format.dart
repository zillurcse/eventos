import '../models/reception_models.dart';

enum SessionPhase { live, upcoming, ended }

SessionPhase sessionPhase(ReceptionSession session, {DateTime? now}) {
  final current = now ?? DateTime.now();
  final start = session.startsAt != null ? DateTime.tryParse(session.startsAt!) : null;
  final end = session.endsAt != null ? DateTime.tryParse(session.endsAt!) : null;

  if (start == null) return SessionPhase.ended;
  if (current.isBefore(start)) return SessionPhase.upcoming;
  if (end != null && current.isAfter(end)) return SessionPhase.ended;
  return SessionPhase.live;
}

String formatTimeRange(String? startsAt, String? endsAt) {
  final start = startsAt != null ? DateTime.tryParse(startsAt)?.toLocal() : null;
  if (start == null) return 'Time TBA';
  final end = endsAt != null ? DateTime.tryParse(endsAt)?.toLocal() : null;
  final startLabel = _time(start);
  if (end == null) return startLabel;
  return '$startLabel - ${_time(end)}';
}

String formatDateRange(String? startsAt, String? endsAt) {
  final start = startsAt != null ? DateTime.tryParse(startsAt)?.toLocal() : null;
  if (start == null) return '';
  final end = endsAt != null ? DateTime.tryParse(endsAt)?.toLocal() : null;
  final startLabel = _date(start);
  if (end == null || _sameDay(start, end)) return startLabel;
  return '$startLabel - ${_date(end)}';
}

String stripHtml(String? value) {
  if (value == null || value.isEmpty) return '';
  return value
      .replaceAll(RegExp(r'<[^>]*>'), ' ')
      .replaceAll(RegExp(r'\s+'), ' ')
      .trim();
}

String firstName(String? fullName) {
  if (fullName == null || fullName.trim().isEmpty) return 'there';
  return fullName.trim().split(RegExp(r'\s+')).first;
}

String _time(DateTime d) {
  final hour = d.hour % 12 == 0 ? 12 : d.hour % 12;
  final minute = d.minute.toString().padLeft(2, '0');
  final suffix = d.hour >= 12 ? 'PM' : 'AM';
  return '$hour:$minute $suffix';
}

String _date(DateTime d) {
  const months = [
    'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
    'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
  ];
  return '${d.day} ${months[d.month - 1]} ${d.year}';
}

bool _sameDay(DateTime a, DateTime b) =>
    a.year == b.year && a.month == b.month && a.day == b.day;
