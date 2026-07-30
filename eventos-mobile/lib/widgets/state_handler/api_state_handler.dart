import 'package:expouse/utils/enum/enums.dart';
import 'package:expouse/widgets/state_handler/widgets/failed_widget.dart';
import 'package:expouse/widgets/state_handler/widgets/loading_widget.dart';
import 'package:flutter/material.dart';

class ApiStateHandler extends StatelessWidget {
  final ApiState state;
  final Widget loadedElement;
  final Function() onRetry;
  final Widget? initElement;
  final Widget? errorElement;
  final Widget? skeleton;

  const ApiStateHandler({
    super.key,
    required this.state,
    required this.loadedElement,
    required this.onRetry,
    this.initElement,
    this.errorElement,
    this.skeleton,
  });

  @override
  Widget build(BuildContext context) {
    return switch (state) {
      ApiState.initial => initElement ?? const SizedBox.shrink(),
      ApiState.loading => skeleton ?? const LoadingWidget(),
      ApiState.loaded => loadedElement,
      ApiState.error => errorElement ?? FailedWidget(onRetry: onRetry),
    };
  }
}
