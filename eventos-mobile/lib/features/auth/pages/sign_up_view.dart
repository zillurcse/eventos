import 'package:expouse/features/auth/auth_controller.dart';
import 'package:expouse/utils/extension/string_ext.dart';
import 'package:expouse/widgets/headers/auth_header.dart';
import 'package:expouse/widgets/state_handler/api_state_handler.dart';
import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get_core/src/get_main.dart';
import 'package:get/get_instance/src/extension_instance.dart';
import 'package:get/get_navigation/src/extension_navigation.dart';
import 'package:get/get_state_manager/src/rx_flutter/rx_obx_widget.dart';

import 'package:expouse/utils/bindings/auth_binding.dart';
import '../../../utils/extension/size_ext.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../widgets/custom_button.dart';
import '../../../widgets/custom_image.dart';
import '../../../widgets/custom_input.dart';
import '../auth_view.dart';
import '../widgets/sign_up_item.dart';

class SignUpView extends StatefulWidget {
  final String email;
  const SignUpView({super.key, required this.email});

  @override
  State<SignUpView> createState() => SignUpViewState();
}

class SignUpViewState extends State<SignUpView> {
  final controller = Get.find<AuthController>();

  @override
  void initState() {
    WidgetsBinding.instance.addPostFrameCallback((time) {
      initSignUp();
    });
    super.initState();
  }

  Future<void> initSignUp() async {
    controller.signUpItems.clear();
    controller.givenInputs.clear();
    controller.customInputs.clear();

    controller.givenInputs["email"] = TextEditingController(text: widget.email);
    controller.givenInputs["password"] = TextEditingController();
    controller.givenInputs["confirm_password"] = TextEditingController();
    controller.signUpItems.addAll([
      SignUpItem(
        key: const ValueKey("email_field"),
        label: 'Email',
        widget: CustomInput(
          controller: controller.givenInputs["email"],
          hint: widget.email,
          isEditable: false,
          validator: (value) {
            if (value == null || value.isEmpty) {
              return "Field Should not be empty";
            } else {
              if (!value.isValidEmail()) {
                return "Email is not valid";
              }
            }
            return null;
          },
        ),
      ),
      SignUpItem(
        key: const ValueKey("password_field"),
        label: 'Password',
        widget: CustomInput(
          controller: controller.givenInputs["password"],
          hint: "Enter Password",
          obscureText: true,
          validator: (value) {
            if (value == null || value.isEmpty) {
              return "Field Should not be empty";
            }
            return null;
          },
        ),
      ),
      SignUpItem(
        key: const ValueKey("confirm_password_field"),
        label: 'Confirm Password',
        widget: CustomInput(
          controller: controller.givenInputs["confirm_password"],
          hint: "Enter Confirm Password",
          obscureText: true,
          validator: (value) {
            final password = controller.givenInputs["password"]?.text;
            if (value == null || value.isEmpty) {
              return "Field Should not be empty";
            }
            if (value.trim() != password?.trim()) {
              return "Passwords do not match";
            }
            return null;
          },
        ),
      ),
    ]);
    await controller.getRegisterComponents();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      resizeToAvoidBottomInset: true,
      body: Column(
        children: [
          AuthHeader(),
          Expanded(
            child: SingleChildScrollView(
              padding: EdgeInsets.all(16.sp),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  SizedBox(height: 8.h),

                  Text("Sign Up", style: context.h5),

                  SizedBox(height: 6.h),

                  Text(
                    "You can create your account here.",
                    style: context.titleLarge?.copyWith(color: context.caption),
                  ),

                  SizedBox(height: 20.h),
                  Form(
                    key: controller.formKey,
                    child: Obx(
                      () => ApiStateHandler(
                        state: controller.getComponentStatus.value,
                        loadedElement: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [...controller.signUpItems],
                        ),
                        onRetry: () {
                          controller.getRegisterComponents();
                        },
                      ),
                    ),
                  ),

                  SizedBox(height: 16.h),

                  Obx(() {
                    final element = Button.roundedText(
                      text: "Sign Up",
                      onTap: onSignUp,
                    );
                    return ApiStateHandler(
                      state: controller.registerUserStatus.value,
                      initElement: element,
                      loadedElement: element,
                      errorElement: element,
                      onRetry: () {},
                    );
                  }),

                  SizedBox(height: 16.h),

                  Center(
                    child: RichText(
                      text: TextSpan(
                        children: [
                          TextSpan(
                            text: "Already have an account? ",
                            style: context.titleRegular?.copyWith(
                              color: context.caption,
                            ),
                          ),
                          TextSpan(
                            text: "Login Now",
                            style: context.titleRegular?.copyWith(
                              color: context.primaryTheme,
                            ),
                            recognizer: TapGestureRecognizer()
                              ..onTap = () {
                                Get.offAll(
                                  () => AuthView(),
                                  binding: AuthBinding(),
                                );
                              },
                          ),
                        ],
                      ),
                    ),
                  ),

                  SizedBox(height: context.width / 10),

                  Center(
                    child: CustomImage(
                      "assets/png/logo-primary.png",
                      height: 24.h,
                    ),
                  ),

                  SizedBox(
                    height:
                        context.width / 20 +
                        MediaQuery.of(context).viewPadding.bottom,
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  void onSignUp() {
    if (controller.formKey.currentState?.validate() ?? false) {
      if (controller.validateCustomInputs()) {
        final signUpData = controller.getSignUpData();
        controller.registerUser(formData: signUpData);
      }
    }
  }
}
