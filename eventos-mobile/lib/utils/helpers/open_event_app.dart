import 'package:get/get.dart';

import '../../features/root/root_controller.dart';
import '../../features/root/root_view.dart';
import '../bindings/route_bindings.dart';

/// Enter the main shell for the currently selected event (subdomain + uuid).
Future<void> openEventApp() async {
  if (Get.isRegistered<RootController>()) {
    // Fire reload so site/reception use the chosen event; don't await before
    // navigation so the shell can show its own loading state.
    final root = Get.find<RootController>();
    Get.offAll(() => const RootView(), binding: RootBinding());
    await root.reloadForCurrentEvent();
  } else {
    Get.offAll(() => const RootView(), binding: RootBinding());
  }
}
