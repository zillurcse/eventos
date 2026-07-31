/// Live / upcoming / ended phase for a session, matching eventos-event watch page.
enum SessionPhase { live, upcoming, ended }

class SessionPhaseHelper {
  SessionPhaseHelper._();

  static const preRoll = Duration(minutes: 15);
  static const postRoll = Duration(minutes: 30);
  static const assumedLength = Duration(hours: 2);

  static SessionPhase resolve({
    required String? status,
    required String? startsAt,
    required String? endsAt,
    DateTime? now,
  }) {
    final clock = now ?? DateTime.now();
    final s = (status ?? '').toLowerCase();
    if (s == 'live') return SessionPhase.live;
    if (s == 'ended' || s == 'canceled' || s == 'cancelled') {
      return SessionPhase.ended;
    }

    final start = DateTime.tryParse(startsAt ?? '');
    if (start == null) return SessionPhase.ended;

    final end = DateTime.tryParse(endsAt ?? '') ?? start.add(assumedLength);
    if (clock.isBefore(start.subtract(preRoll))) return SessionPhase.upcoming;
    if (clock.isAfter(end.add(postRoll))) return SessionPhase.ended;
    return SessionPhase.live;
  }

  static bool isLiveNow({
    required String? status,
    required String? startsAt,
    required String? endsAt,
    DateTime? now,
  }) =>
      resolve(
        status: status,
        startsAt: startsAt,
        endsAt: endsAt,
        now: now,
      ) ==
      SessionPhase.live;

  /// Elapsed fraction of the session window `[startsAt, endsAt]`, clamped to 0–1.
  static double progress({
    required String? startsAt,
    required String? endsAt,
    DateTime? now,
  }) {
    final clock = now ?? DateTime.now();
    final start = DateTime.tryParse(startsAt ?? '');
    if (start == null) return 0;

    final end = DateTime.tryParse(endsAt ?? '') ?? start.add(assumedLength);
    final totalMs = end.difference(start).inMilliseconds;
    if (totalMs <= 0) return 1;

    final elapsedMs = clock.difference(start).inMilliseconds;
    final fraction = elapsedMs / totalMs;
    if (!fraction.isFinite) return 0;
    return fraction.clamp(0.0, 1.0);
  }
}
