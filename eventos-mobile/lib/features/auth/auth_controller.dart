import 'package:expouse/utils/enum/enums.dart';
import 'package:expouse/utils/helpers/toast_msg.dart';
import 'package:flutter/cupertino.dart';
import 'package:get/get_core/src/get_main.dart';
import 'package:get/get_navigation/src/extension_navigation.dart';
import 'package:get/get_rx/src/rx_types/rx_types.dart';
import 'package:get/get_state_manager/get_state_manager.dart';
import 'package:get_storage/get_storage.dart';

import '../../models/registration_item.dart';
import '../../utils/bindings/auth_binding.dart';
import '../../utils/helpers/helper_functions.dart';
import '../../utils/helpers/local_key.dart';
import '../../widgets/custom_checkbox.dart';
import '../../widgets/custom_date.dart';
import '../../widgets/custom_dropdown.dart';
import '../../widgets/custom_input.dart';
import '../../widgets/custom_radio.dart';
import 'auth_service.dart';
import 'auth_view.dart';
import 'widgets/sign_up_item.dart';

class AuthController extends GetxController {
  final authService = AuthService();
  final localDb = GetStorage();

  final emailValidationStatus = ApiState.initial.obs;
  final signUpStatus = ApiState.initial.obs;
  final loginWithPassStatus = ApiState.initial.obs;
  final loginWithCodeStatus = ApiState.initial.obs;
  final getComponentStatus = ApiState.initial.obs;
  final registerUserStatus = ApiState.initial.obs;

  final RxBool agreeWithTcEmail = false.obs;
  final RxBool agreeWithTcPass = false.obs;
  final RxBool agreeWithTcCode = false.obs;

  RxBool isAgreeWithTC = false.obs;
  Rx<AuthFlow> authFlow = AuthFlow.registerWithForm.obs;

  RxList<SignUpItem> signUpItems = <SignUpItem>[].obs;
  RxList<RegistrationItem> regItems = <RegistrationItem>[].obs;
  Map<String, TextEditingController> givenInputs = {};
  Map<String, String> customInputs = {};
  final formKey = GlobalKey<FormState>();

  AuthFlow? routeFromString(String value) {
    switch (value) {
      case "sign-up":
        return AuthFlow.registerWithForm;
      case "sign-in-pass":
        return AuthFlow.loginWithPass;
      case "sign-up-otp":
        return AuthFlow.registerWithOtp;
      case "login-with-code":
        return AuthFlow.loginWithCode;
      default:
        return null;
    }
  }

  /// Returns null if the API failed, true if email is valid, false if invalid.
  Future<bool?> onEmailValidation({required String email}) async {
    String? status;
    await handleApiClient(
      onStateChanged: emailValidationStatus,
      handleApiCall: () async {
        final response = await authService.emailValidationCheck(
          agreedTc: isAgreeWithTC.value,
          email: email,
        );
        status = response.data["status"];
      },
    );
    if (status == null) return null;
    return status != "error";
  }

  Future<void> getRegisterComponents() async {
    await handleApiClient(
      onStateChanged: getComponentStatus,
      handleApiCall: () async {
        final response = await authService.getRegisterComponents();
        loadAllRegItems(response.data);
      },
    );
  }

  void loadAllRegItems(dynamic responseData) {
    regItems.value = (responseData["data"] as List)
        .map((e) => RegistrationItem.fromJson(e))
        .toList();

    List<SignUpItem> dynamicFields = [];

    for (RegistrationItem item in regItems) {
      if (givenInputs.containsKey(item.databaseConstantColumn)) continue;

      switch (item.fieldType) {
        case "text_element":
        case "text_area_element":
          givenInputs[item.databaseConstantColumn] ??= TextEditingController();
          dynamicFields.add(
            SignUpItem(
              key: ValueKey(
                "text_${item.databaseConstantColumn}_${item.fieldLabel}",
              ),
              label: item.fieldLabel,
              widget: CustomInput(
                controller: givenInputs[item.databaseConstantColumn],
                hint: "Enter ${item.fieldLabel}",
                maxLines: item.fieldType == "text_area_element" ? 6 : 1,
                validator: (value) => (value == null || value.isEmpty)
                    ? "Field should not be empty"
                    : null,
              ),
            ),
          );

        case "dropdown_element":
          customInputs[item.databaseConstantColumn] = "";
          dynamicFields.add(
            SignUpItem(
              key: ValueKey(
                "dropdown_${item.databaseConstantColumn}_${item.fieldLabel}",
              ),
              label: item.fieldLabel,
              widget: CustomDropdown(
                item: item,
                onSelected: (value) =>
                    customInputs[item.databaseConstantColumn] = value,
              ),
            ),
          );

        case "radio_element":
          customInputs[item.databaseConstantColumn] = "";
          dynamicFields.add(
            SignUpItem(
              key: ValueKey(
                "radio_${item.databaseConstantColumn}_${item.fieldLabel}",
              ),
              label: item.fieldLabel,
              widget: CustomRadio(
                options: item.options,
                onSelected: (value) =>
                    customInputs[item.databaseConstantColumn] = value,
              ),
            ),
          );

        case "date_element":
          customInputs[item.databaseConstantColumn] = "";
          dynamicFields.add(
            SignUpItem(
              key: ValueKey(
                "date_${item.databaseConstantColumn}_${item.fieldLabel}",
              ),
              label: item.fieldLabel,
              widget: CustomDate(
                label: item.fieldLabel,
                onSelected: (date) =>
                    customInputs[item.databaseConstantColumn] =
                        "${date.year}-${date.month}-${date.day}",
              ),
            ),
          );

        case "checkbox_element":
          customInputs[item.databaseConstantColumn] = "";
          dynamicFields.add(
            SignUpItem(
              key: ValueKey(
                "checkbox_${item.databaseConstantColumn}_${item.fieldLabel}",
              ),
              label: item.fieldLabel,
              widget: CustomCheckbox(
                options: item.options,
                onSelected: (values) =>
                    customInputs[item.databaseConstantColumn] = values.join(
                      ",",
                    ),
              ),
            ),
          );
      }
    }
    signUpItems.insertAll(1, dynamicFields);
  }

  List<Map<String, dynamic>> getSignUpData() {
    return [
      {
        "field_label": "Email",
        "value": givenInputs["email"]?.text ?? "",
        "field_type": "text_element",
        "has_constant_in_database": 1,
        "database_constant_column": "email",
      },
      {
        "field_label": "Password",
        "value": givenInputs["password"]?.text ?? "",
        "field_type": "text_element",
        "has_constant_in_database": 1,
        "database_constant_column": "password",
      },
      for (final item in regItems)
        {
          "field_label": item.fieldLabel,
          "value":
              (item.fieldType == "text_element" ||
                  item.fieldType == "text_area_element")
              ? givenInputs[item.databaseConstantColumn]?.text ?? ""
              : customInputs[item.databaseConstantColumn] ?? "",
          "field_type": item.fieldType,
          "has_constant_in_database": item.hasConstantInDatabase,
          "database_constant_column": item.databaseConstantColumn,
        },
    ];
  }

  bool validateCustomInputs() {
    for (var item in regItems) {
      if (item.fieldType == "radio_element" ||
          item.fieldType == "date_element" ||
          item.fieldType == "dropdown_element") {
        final value = customInputs[item.databaseConstantColumn];
        if (value == null || value.isEmpty) {
          ToastMsg.showErrorMessage("Please select ${item.fieldLabel}");
          return false;
        }
      }
    }
    return true;
  }

  Future<void> loginWithPassword({
    required String email,
    required String password,
    required Function() onSuccess,
  }) async {
    await handleApiClient(
      onStateChanged: loginWithPassStatus.call,
      handleApiCall: () async {
        final response = await authService.loginWithPass(
          email: email,
          password: password,
        );
        await localDb.write(LocalKeyHelper.token, response.data["token"]);
        await localDb.write(LocalKeyHelper.userInfo, response.data["user"]);
        ToastMsg.showSuccessMessage("Logged in successfully!");
        onSuccess.call();
      },
    );
  }

  Future<void> registerUser({
    required List<Map<String, dynamic>> formData,
  }) async {
    await handleApiClient(
      onStateChanged: registerUserStatus.call,
      handleApiCall: () async {
        final response = await authService.registerUser(formData: formData);
        Get.offAll(() => AuthView(), binding: AuthBinding());
        ToastMsg.showSuccessMessage("${response.data["message"]}");
      },
    );
  }
}
