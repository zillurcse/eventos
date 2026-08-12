import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:webview_flutter/webview_flutter.dart';
import '../utils/extension/theme_ext.dart';

class AppWebviewPage extends StatefulWidget {
  final String title;
  final String url;

  const AppWebviewPage({super.key, required this.title, required this.url});

  @override
  State<AppWebviewPage> createState() => _AppWebviewPageState();
}

class _AppWebviewPageState extends State<AppWebviewPage> {
  late final WebViewController _controller;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageFinished: (String url) {
            if (mounted) {
              setState(() {
                _isLoading = false;
              });
            }
          },
        ),
      )
      ..loadRequest(Uri.parse(widget.url));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: context.backgroundColor,
      appBar: AppBar(
        title: Text(
          widget.title,
          style: context.titleLarge?.copyWith(color: context.tertiaryText),
        ),
        titleSpacing: 0,
        backgroundColor: context.primaryTheme,
        elevation: 1,
        shadowColor: Colors.black.withValues(alpha: 0.05),
        leading: BackButton(color: context.tertiaryText),
      ),
      body: Stack(
        children: [
          WebViewWidget(controller: _controller),
          if (_isLoading)
            Center(
              child: CircularProgressIndicator(color: context.primaryTheme),
            ),
        ],
      ),
    );
  }
}
