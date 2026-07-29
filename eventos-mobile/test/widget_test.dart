import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eventos_mobile/app.dart';
import 'package:eventos_mobile/core/theme/app_theme.dart';
import 'package:eventos_mobile/features/bootstrap/screens/splash_screen.dart';

void main() {
  testWidgets('App boots to branded splash screen', (WidgetTester tester) async {
    await tester.pumpWidget(const ProviderScope(child: EventosApp()));
    await tester.pump();

    expect(find.byType(SplashScreen), findsOneWidget);

    final scaffold = tester.widget<Scaffold>(find.byType(Scaffold).first);
    expect(scaffold.backgroundColor, AppColors.brandPurple);

    // Flush the branded splash hold timer.
    await tester.pump(const Duration(milliseconds: 1800));
  });
}
