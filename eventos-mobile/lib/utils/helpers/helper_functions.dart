import 'package:expouse/utils/enum/enums.dart';
import 'package:expouse/utils/helpers/toast_msg.dart';

/// Wraps an async API call with loading/error state management.
/// Sets [onStateChanged] to [ApiState.loading] before the call, then
/// [ApiState.loaded] on success or [ApiState.error] on failure.
Future<void> handleApiClient({
  required Future<void> Function() handleApiCall,
  required Function(ApiState) onStateChanged,
}) async {
  onStateChanged(ApiState.loading);
  try {
    await handleApiCall();
    onStateChanged(ApiState.loaded);
  } catch (err) {
    onStateChanged(ApiState.error);
    ToastMsg.showApiErrorMessage(err);
  }
}