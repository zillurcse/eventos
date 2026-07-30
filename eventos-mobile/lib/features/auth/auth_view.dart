import 'package:expouse/features/auth/widgets/footer_widget.dart';
import 'package:expouse/features/auth/widgets/header_widget.dart';
import 'package:flutter/services.dart';

import 'package:expouse/widgets/headers/auth_header.dart';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';

import '../../widgets/custom_input.dart';
import '../../widgets/dialogs/exit_dialog.dart';

class AuthView extends StatelessWidget {
  const AuthView({super.key});

  @override
  Widget build(BuildContext context) {
    final email = TextEditingController();

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, result) async {
        if (didPop) return;
        final navigator = Navigator.of(context);
        if (navigator.canPop()) {
          navigator.pop();
        } else {
          final shouldExit = await showExitDialog(context);
          if (shouldExit == true) {
            SystemNavigator.pop();
          }
        }
      },
      child: Scaffold(
        resizeToAvoidBottomInset: false,
        body: Column(
          children: [
            AuthHeader(),
            Expanded(
              child: Padding(
                padding: EdgeInsets.all(16.sp),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    HeaderWidget(),
            
                    CustomInput(controller: email, hint: "Enter Email"),
            
                    Expanded(child: FooterWidget(email: email,)),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
