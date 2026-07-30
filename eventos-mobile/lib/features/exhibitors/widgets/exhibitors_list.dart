import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../exhibitor_controller.dart';
import 'exhibitor_item.dart';

class ExhibitorsList extends StatelessWidget {
  const ExhibitorsList({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<ExhibitorController>();
    return Obx(() {
      final exhibitors = controller.exhibitors;

      return SliverList(
        delegate: SliverChildBuilderDelegate(
          (context, index) {
            if (index >= exhibitors.length) return null;
            return ExhibitorItem(exhibitor: exhibitors[index]);
          },
          childCount: exhibitors.length,
        ),
      );
    });
  }
}
