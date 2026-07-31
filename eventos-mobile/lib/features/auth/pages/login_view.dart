import 'package:expouse/utils/helpers/toast_msg.dart';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get_core/src/get_main.dart';
import 'package:get/get_instance/src/extension_instance.dart';
import 'package:get/get_navigation/src/extension_navigation.dart';
import 'package:get/get_rx/src/rx_types/rx_types.dart';
import 'package:get/get_state_manager/src/rx_flutter/rx_obx_widget.dart';

import '../../../utils/extension/size_ext.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../utils/helpers/open_event_app.dart';
import '../../../widgets/custom_button.dart';
import '../../../widgets/custom_image.dart';
import '../../../widgets/custom_input.dart';
import '../../../widgets/headers/auth_header.dart';
import '../../../widgets/state_handler/api_state_handler.dart';
import '../auth_controller.dart';
import '../widgets/header_widget.dart';
import '../widgets/permission_widget.dart';
import 'select_event_view.dart';

class LoginView extends StatefulWidget {
  final String email;
  final bool loginWithCode;
  const LoginView({super.key, required this.email, this.loginWithCode = false});

  @override
  State<LoginView> createState() => LoginViewState();
}

class LoginViewState extends State<LoginView> {
  final controller = Get.find<AuthController>();
  final email = TextEditingController();
  final pass = TextEditingController();
  final code = TextEditingController();

  RxBool showPass = false.obs;

  @override
  void initState() {
    WidgetsBinding.instance.addPostFrameCallback((time) {
      email.text = widget.email;
    });
    super.initState();
  }

  @override
  void dispose() {
    email.dispose();
    pass.dispose();
    code.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
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
                  Text("Email", style: context.bodyRegular),

                  SizedBox(height: 6.h),
                  CustomInput(
                    controller: email,
                    hint: "Enter Email",
                    isEditable: false,
                  ),
                  SizedBox(height: 12.h),

                  Text(
                    widget.loginWithCode ? "Login Code" : "Password",
                    style: context.bodyRegular,
                  ),

                  SizedBox(height: 6.h),

                  Obx(
                    () => CustomInput(
                      controller: widget.loginWithCode ? code : pass,
                      hint: widget.loginWithCode
                          ? "Enter Login Code"
                          : "Enter Password",
                      suffix: widget.loginWithCode
                          ? null
                          : GestureDetector(
                              onTap: () {
                                showPass.toggle();
                              },
                              child: Icon(
                                !showPass.value
                                    ? Icons.visibility_outlined
                                    : Icons.visibility_off_outlined,
                                size: 18.sp,
                              ),
                            ),
                      obscureText: widget.loginWithCode
                          ? false
                          : !showPass.value,
                    ),
                  ),

                  SizedBox(height: 16.h),

                  widget.loginWithCode
                      ? SizedBox.shrink()
                      : Padding(
                          padding: EdgeInsets.only(bottom: 12.h),
                          child: Align(
                            alignment: Alignment.centerRight,
                            child: Text(
                              "Forgot Password?",
                              style: context.titleRegular,
                            ),
                          ),
                        ),

                  PermissionWidget(
                    agreeWithTc: widget.loginWithCode
                        ? controller.agreeWithTcCode
                        : controller.agreeWithTcPass,
                  ),

                  SizedBox(height: 16.h),

                  Obx(() {
                    final element = Button.roundedText(
                      text: "Login",
                      onTap: onLogin,
                    );
                    return ApiStateHandler(
                      state: controller.loginWithPassStatus.value,
                      initElement: element,
                      loadedElement: element,
                      errorElement: element,
                      onRetry: () {},
                    );
                  }),

                  SizedBox(height: 20.h),

                  Center(
                    child: Button.roundedText(
                      text: widget.loginWithCode
                          ? "Different User?"
                          : "Login with OTP",
                      width: context.width / 2,
                      height: 36,

                      onTap: () {},
                      backgroundColor: context.primaryFocused,
                      onBackgroundColor: context.primaryTheme,
                      style: context.titleRegular?.copyWith(
                        color: context.primaryTheme,
                      ),
                    ),
                  ),

                  Spacer(),

                  Center(
                    child: CustomImage(
                      "assets/png/logo-primary.png",
                      height: 24.h,
                    ),
                  ),

                  SizedBox(height: context.width / 8),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  void onLogin() async {
    if (widget.loginWithCode) {
      if (!controller.agreeWithTcCode.value) {
        ToastMsg.showErrorMessage(
          "You must agree terms and conditions to proceed!",
        );
        return;
      }
    } else {
      if (!controller.agreeWithTcPass.value) {
        ToastMsg.showErrorMessage(
          "You must agree terms and conditions to proceed!",
        );
        return;
      }
    }

    if (pass.text.isEmpty) {
      ToastMsg.showErrorMessage("Password can't be empty!");
      return;
    }
    await controller.loginWithPassword(
      email: email.text,
      password: pass.text,
      onSuccess: () {
        if (controller.needsEventSelection) {
          Get.offAll(
            () => SelectEventView(
              events: controller.availableEvents.toList(),
            ),
          );
        } else {
          openEventApp();
        }
      },
    );
  }
}
