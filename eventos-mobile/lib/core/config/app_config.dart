/// Runtime configuration for the EventOS mobile client.
///
/// Override at build/run time with `--dart-define`:
///   flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8080
class AppConfig {
  const AppConfig({
    required this.apiBaseUrl,
  });

  /// Laravel API origin (no trailing slash, no `/api/v1` suffix).
  final String apiBaseUrl;

  String get apiV1 => '$apiBaseUrl/api/v1';

  static const AppConfig development = AppConfig(
    // Android emulator → host machine. Use your LAN IP on a physical device.
    apiBaseUrl: String.fromEnvironment(
      'API_BASE_URL',
      defaultValue: 'http://10.0.2.2:8080',
    ),
  );
}
