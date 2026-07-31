/// Shared input validators for auth and forms (OWASP M4 / MASVS-STORAGE/INPUT).
class InputValidators {
  InputValidators._();

  static final RegExp _email = RegExp(
    r"^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+$",
  );

  static final RegExp _otp = RegExp(r'^\d{4,8}$');

  static String? email(String? value) {
    final v = value?.trim() ?? '';
    if (v.isEmpty) return 'Email is required';
    if (v.length > 254) return 'Email is too long';
    if (!_email.hasMatch(v)) return 'Enter a valid email address';
    return null;
  }

  /// Minimum complexity for registration passwords.
  static String? password(String? value, {int minLength = 8}) {
    final v = value ?? '';
    if (v.isEmpty) return 'Password is required';
    if (v.length < minLength) {
      return 'Password must be at least $minLength characters';
    }
    if (v.length > 128) return 'Password is too long';
    if (!RegExp(r'[A-Za-z]').hasMatch(v) || !RegExp(r'\d').hasMatch(v)) {
      return 'Password must include letters and numbers';
    }
    return null;
  }

  static String? confirmPassword(String? value, String? password) {
    final err = InputValidators.password(value);
    if (err != null) return err;
    if ((value ?? '') != (password ?? '')) {
      return 'Passwords do not match';
    }
    return null;
  }

  static String? otp(String? value) {
    final v = value?.trim() ?? '';
    if (v.isEmpty) return 'Code is required';
    if (!_otp.hasMatch(v)) return 'Enter a valid code';
    return null;
  }

  static String? requiredField(String? value, {String label = 'Field'}) {
    if (value == null || value.trim().isEmpty) {
      return '$label should not be empty';
    }
    if (value.length > 2000) return '$label is too long';
    return null;
  }

  static bool isHttpsUrl(String? url) {
    if (url == null || url.isEmpty) return false;
    final uri = Uri.tryParse(url);
    return uri != null && uri.hasScheme && uri.scheme == 'https';
  }
}
