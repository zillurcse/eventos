import '../../models/meeting_model.dart';

const _defaultDurationMs = 30 * 60 * 1000;

(int, int)? _slotRange(String date, String slot) {
  final parts = slot.split('-');
  if (parts.length != 2) return null;
  final from = parts[0].trim();
  final to = parts[1].trim();
  final start = DateTime.tryParse('${date}T$from:00');
  final end = DateTime.tryParse('${date}T$to:00');
  if (start == null || end == null) return null;
  return (start.millisecondsSinceEpoch, end.millisecondsSinceEpoch);
}

int? meetingEndMs(Meeting m) {
  if (m.date != null &&
      m.date!.isNotEmpty &&
      m.slot != null &&
      m.slot!.isNotEmpty) {
    final range = _slotRange(m.date!, m.slot!);
    if (range != null) return range.$2;
  }
  if (m.startsAt == null || m.startsAt!.isEmpty) return null;
  final start = DateTime.tryParse(m.startsAt!);
  if (start == null) return null;
  if (m.endsAt != null && m.endsAt!.isNotEmpty) {
    final end = DateTime.tryParse(m.endsAt!);
    if (end != null) return end.millisecondsSinceEpoch;
  }
  return start.millisecondsSinceEpoch + _defaultDurationMs;
}

int? meetingStartMs(Meeting m) {
  if (m.date != null &&
      m.date!.isNotEmpty &&
      m.slot != null &&
      m.slot!.isNotEmpty) {
    final range = _slotRange(m.date!, m.slot!);
    if (range != null) return range.$1;
  }
  if (m.startsAt == null || m.startsAt!.isEmpty) return null;
  return DateTime.tryParse(m.startsAt!)?.millisecondsSinceEpoch;
}

String _fmtTime(DateTime dt) {
  final h = dt.hour;
  final m = dt.minute.toString().padLeft(2, '0');
  final period = h >= 12 ? 'PM' : 'AM';
  final hour12 = h % 12 == 0 ? 12 : h % 12;
  return '$hour12:$m $period';
}

String _fmtDay(DateTime dt) {
  const weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
  const months = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
  ];
  return '${weekdays[dt.weekday - 1]}, ${months[dt.month - 1]} ${dt.day}';
}

String meetingTimeLabel(Meeting m) {
  int? start;
  int? end;

  if (m.date != null &&
      m.date!.isNotEmpty &&
      m.slot != null &&
      m.slot!.isNotEmpty) {
    final range = _slotRange(m.date!, m.slot!);
    if (range != null) {
      start = range.$1;
      end = range.$2;
    }
  }

  if (start == null && m.startsAt != null && m.startsAt!.isNotEmpty) {
    start = DateTime.tryParse(m.startsAt!)?.millisecondsSinceEpoch;
    if (m.endsAt != null && m.endsAt!.isNotEmpty) {
      end = DateTime.tryParse(m.endsAt!)?.millisecondsSinceEpoch;
    }
  }

  if (start == null) return 'Time to be arranged';

  final startDt = DateTime.fromMillisecondsSinceEpoch(start);
  final endDt = end != null
      ? DateTime.fromMillisecondsSinceEpoch(end)
      : null;

  final range = endDt == null
      ? _fmtTime(startDt)
      : '${_fmtTime(startDt)} - ${_fmtTime(endDt)}';

  final now = DateTime.now();
  final isToday = startDt.year == now.year &&
      startDt.month == now.month &&
      startDt.day == now.day;

  return isToday ? range : '${_fmtDay(startDt)} · $range';
}

/// Whether Join is available (confirmed delegate meeting within join window).
bool isMeetingJoinable(Meeting m) {
  if (m.status != 'confirmed' || m.source != 'delegate') return false;
  final start = meetingStartMs(m);
  if (start == null) return true;

  final end = meetingEndMs(m) ?? (start + _defaultDurationMs);
  const joinLeadMs = 10 * 60 * 1000;
  const joinGraceMs = 15 * 60 * 1000;
  final now = DateTime.now().millisecondsSinceEpoch;
  return now >= start - joinLeadMs && now <= end + joinGraceMs;
}

({String label, String kind}) meetingBadge(Meeting m) {
  switch (m.status) {
    case 'confirmed':
      return (label: 'Accepted', kind: 'ok');
    case 'declined':
      return (label: 'Rejected', kind: 'no');
    case 'canceled':
      return (label: 'Canceled', kind: 'no');
    case 'completed':
      return (label: 'Completed', kind: 'muted');
    default:
      return m.direction == 'incoming'
          ? (label: 'Received - Pending', kind: 'wait')
          : (label: 'Sent - Pending', kind: 'wait');
  }
}

String formatDateTab(String iso) {
  final parts = iso.split('-');
  if (parts.length != 3) return iso;
  final y = int.tryParse(parts[0]) ?? 1970;
  final m = int.tryParse(parts[1]) ?? 1;
  final d = int.tryParse(parts[2]) ?? 1;
  final dt = DateTime(y, m, d);
  const weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
  const months = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
  ];
  return '${dt.day}\n${months[dt.month - 1]}\n${weekdays[dt.weekday - 1]}';
}

String formatDateTabCompact(String iso) {
  final parts = iso.split('-');
  if (parts.length != 3) return iso;
  final y = int.tryParse(parts[0]) ?? 1970;
  final m = int.tryParse(parts[1]) ?? 1;
  final d = int.tryParse(parts[2]) ?? 1;
  final dt = DateTime(y, m, d);
  const weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
  const months = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
  ];
  return '$d ${months[dt.month - 1]} ${weekdays[dt.weekday - 1]}';
}
