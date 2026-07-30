import 'dart:math' as math;

import '../../../models/lounge_model.dart';

/// Shared helpers for lounge seat layouts.
class LoungeSeatHelper {
  LoungeSeatHelper._();

  static List<LoungeOccupant?> occupantBySeat(
    LoungeTable table, {
    int? maxSeats,
  }) {
    final n = maxSeats == null
        ? table.capacity
        : table.capacity.clamp(1, maxSeats);
    final seats = List<LoungeOccupant?>.filled(n, null);
    final unseated = <LoungeOccupant>[];
    for (final o in table.occupants) {
      final s = o.seat;
      if (s != null && s >= 0 && s < n && seats[s] == null) {
        seats[s] = o;
      } else {
        unseated.add(o);
      }
    }
    for (final o in unseated) {
      final i = seats.indexWhere((e) => e == null);
      if (i < 0) break;
      seats[i] = o;
    }
    return seats;
  }

  static String kindLabel(LoungeTableKind kind) => switch (kind) {
        LoungeTableKind.attendee => 'Attendee networking',
        LoungeTableKind.exhibitor => 'Exhibitor lounge',
        LoungeTableKind.sponsor => 'Sponsor lounge',
      };

  /// Classic seats around a square stage (% coords).
  static List<({double x, double y})> classicPositions(int n) {
    if (n <= 0) return const [];
    if (n == 1) return const [(x: 50, y: 12)];
    if (n == 2) return const [(x: 50, y: 12), (x: 50, y: 88)];
    if (n == 3) {
      return const [(x: 50, y: 10), (x: 12, y: 72), (x: 88, y: 72)];
    }
    if (n == 4) {
      return const [
        (x: 18, y: 18),
        (x: 82, y: 18),
        (x: 18, y: 82),
        (x: 82, y: 82),
      ];
    }
    const r = 42.0;
    return List.generate(n, (i) {
      final ang = (-90 + (360 / n) * i) * math.pi / 180;
      return (x: 50 + r * math.cos(ang), y: 50 + r * math.sin(ang));
    });
  }
}
