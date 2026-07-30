import 'package:expouse/features/auth/auth_controller.dart';
import 'package:expouse/features/auth/widgets/permission_widget.dart';
import 'package:expouse/features/auth/widgets/social_login_items.dart';
import 'package:expouse/utils/extension/string_ext.dart';
import 'package:flutter/material.dart';
import 'package:flutter_screenutil/flutter_screenutil.dart';
import 'package:get/get_core/src/get_main.dart';
import 'package:get/get_instance/src/extension_instance.dart';
import 'package:get/get_navigation/src/extension_navigation.dart';
import 'package:get/get_state_manager/src/rx_flutter/rx_obx_widget.dart';

import '../../../utils/extension/size_ext.dart';
import '../../../utils/extension/theme_ext.dart';
import '../../../utils/helpers/toast_msg.dart';
import '../../../widgets/custom_button.dart';
import '../../../widgets/custom_image.dart';
import '../../../widgets/state_handler/api_state_handler.dart';
import '../pages/login_view.dart';
import '../pages/sign_up_view.dart';

class FooterWidget extends StatelessWidget {
  final TextEditingController email;
  const FooterWidget({super.key, required this.email});

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<AuthController>();

    Future<void> onContinue() async {
      if (!controller.agreeWithTcEmail.value) {
        ToastMsg.showErrorMessage(
          "You must agree terms and conditions to proceed!",
        );
        return;
      }
      if (email.text.isEmpty || (!email.text.isValidEmail())) {
        ToastMsg.showErrorMessage("Please Enter a valid email");
        return;
      }
      if (FocusScope.of(context).hasFocus) {
        FocusScope.of(context).unfocus();
      }
      bool? validEmail = await controller.onEmailValidation(email: email.text);
      if (validEmail == null) return;

      if (validEmail) {
        Get.off(() => LoginView(email: email.text));
      } else {
        Get.off(() => SignUpView(email: email.text));
      }
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(height: 16.h),

        PermissionWidget(agreeWithTc: controller.agreeWithTcEmail),

        SizedBox(height: 16.h),

        Obx(() {
          final element = Button.roundedText(
            text: "Continue",
            onTap: onContinue,
          );
          return ApiStateHandler(
            state: controller.emailValidationStatus.value,
            initElement: element,
            loadedElement: element,
            errorElement: element,
            onRetry: () {},
          );
        }),

        SizedBox(height: 20.h),

        Center(
          child: Button.roundedText(
            text: "Login with OTP",
            width: context.width / 2,
            height: 36,

            onTap: () {},
            backgroundColor: context.primaryFocused,
            onBackgroundColor: context.primaryTheme,
            style: context.titleRegular?.copyWith(color: context.primaryTheme),
          ),
        ),

        SocialLoginItems(),

        Spacer(),

        Center(child: CustomImage("assets/png/logo-primary.png", height: 24.h)),

        SizedBox(height: context.width / 8),
      ],
    );
  }
}
