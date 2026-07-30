import 'package:html/parser.dart';

extension StringExt on String {
  String htmlToPlainText() {
    final document = parse(this);
    return document.body?.text.trim() ?? '';
  }

  bool isValidEmail() {
    final emailRegex = RegExp(
      r"^[a-zA-Z0-9.a-zA-Z0-9.!#$%&'*+-/=?^_`{|}~]+@[a-zA-Z0-9]+\.[a-zA-Z]+",
    );

    return emailRegex.hasMatch(this);
  }

  bool isNumeric() {
    return true;
  }
}
