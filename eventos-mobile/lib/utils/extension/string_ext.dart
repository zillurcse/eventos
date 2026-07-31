import 'package:html/parser.dart';

import '../helpers/input_validators.dart';

extension StringExt on String {
  String htmlToPlainText() {
    final document = parse(this);
    return document.body?.text.trim() ?? '';
  }

  bool isValidEmail() {
    return InputValidators.email(this) == null;
  }

  bool isNumeric() {
    return RegExp(r'^\d+$').hasMatch(this);
  }
}
