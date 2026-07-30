import 'dart:async';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import '../utils/extension/theme_ext.dart';
import 'custom_button.dart';

class ConnectivityWrapper extends StatefulWidget {
  final Widget child;

  const ConnectivityWrapper({super.key, required this.child});

  @override
  State<ConnectivityWrapper> createState() => _ConnectivityWrapperState();
}

class _ConnectivityWrapperState extends State<ConnectivityWrapper> {
  bool _hasConnection = true;
  StreamSubscription<List<ConnectivityResult>>? _subscription;

  @override
  void initState() {
    super.initState();
    _checkInitialConnection();
    _subscription = Connectivity().onConnectivityChanged.listen((results) {
      final connected = !results.contains(ConnectivityResult.none);
      if (_hasConnection != connected) {
        setState(() {
          _hasConnection = connected;
        });
      }
    });
  }

  Future<void> _checkInitialConnection() async {
    final results = await Connectivity().checkConnectivity();
    final connected = !results.contains(ConnectivityResult.none);
    if (_hasConnection != connected) {
      setState(() {
        _hasConnection = connected;
      });
    }
  }

  @override
  void dispose() {
    _subscription?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (!_hasConnection) {
      return const NoInternetScreen();
    }
    return widget.child;
  }
}

class NoInternetScreen extends StatefulWidget {
  const NoInternetScreen({super.key});

  @override
  State<NoInternetScreen> createState() => _NoInternetScreenState();
}

class _NoInternetScreenState extends State<NoInternetScreen> {
  bool _checking = false;

  Future<void> _retryConnection() async {
    setState(() {
      _checking = true;
    });
    // Add brief artificial delay for premium loading feedback
    await Future.delayed(const Duration(milliseconds: 600));
    final results = await Connectivity().checkConnectivity();
    final hasConnection = !results.contains(ConnectivityResult.none);
    
    if (mounted) {
      setState(() {
        _checking = false;
      });
      if (hasConnection) {
        // Find state of ConnectivityWrapper and update it
        final wrapperState = context.findAncestorStateOfType<_ConnectivityWrapperState>();
        wrapperState?.setState(() {
          wrapperState._hasConnection = true;
        });
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text("Still no connection. Please check your settings."),
            duration: Duration(seconds: 2),
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xfff7f7fb), // standard backgroundColorLight
      body: Center(
        child: Padding(
          padding: EdgeInsets.symmetric(horizontal: 32.w),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              // WiFi Off Premium Icon
              Container(
                width: 90.sp,
                height: 90.sp,
                decoration: BoxDecoration(
                  color: context.primaryFocused,
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  Icons.wifi_off_rounded,
                  color: context.primaryTheme,
                  size: 44.sp,
                ),
              ),
              SizedBox(height: 24.h),

              // Title
              Text(
                "No Internet Connection",
                style: context.h2?.copyWith(
                  color: context.heading,
                  fontWeight: FontWeight.bold,
                ),
                textAlign: TextAlign.center,
              ),
              SizedBox(height: 12.h),

              // Description
              Text(
                "Please check your cellular or WiFi connection and try again.",
                style: context.bodyRegular?.copyWith(
                  color: context.caption,
                  height: 1.4,
                ),
                textAlign: TextAlign.center,
              ),
              SizedBox(height: 36.h),

              // Try Again Button
              if (_checking)
                CircularProgressIndicator(
                  valueColor: AlwaysStoppedAnimation<Color>(context.primaryTheme),
                )
              else
                Button.roundedText(
                  text: "Try Again",
                  width: 140.w,
                  style: context.buttonMediumBold?.copyWith(
                    color: Colors.white,
                  ),
                  onTap: _retryConnection,
                ),
            ],
          ),
        ),
      ),
    );
  }
}
