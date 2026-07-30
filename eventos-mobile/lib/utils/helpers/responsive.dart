import 'package:expouse/utils/enum/enums.dart';

class Responsive {
  Responsive._();
  static final obj = Responsive._();
  factory Responsive() => obj;

  ResponsiveState getResponsiveState(double width) => switch (width) {
    >= 1100 => ResponsiveState.desktop,
    >= 850 => ResponsiveState.tablet,
    _ => ResponsiveState.mobile,
  };
}
