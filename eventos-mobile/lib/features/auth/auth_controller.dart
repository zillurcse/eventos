import 'package:expouse/utils/enum/enums.dart';
import 'package:expouse/utils/helpers/toast_msg.dart';
import 'package:flutter/cupertino.dart';
import 'package:get/get_core/src/get_main.dart';
import 'package:get/get_navigation/src/extension_navigation.dart';
import 'package:get/get_rx/src/rx_types/rx_types.dart';
import 'package:get/get_state_manager/get_state_manager.dart';
import 'package:get_storage/get_storage.dart';

import '../../models/my_event.dart';
import '../../models/registration_item.dart';
import '../../utils/bindings/auth_binding.dart';
import '../../utils/helpers/app_data_provider.dart';
import '../../utils/helpers/helper_functions.dart';
import '../../utils/helpers/input_validators.dart';
import '../../utils/helpers/local_key.dart';
import '../../utils/helpers/secure_auth_storage.dart';
import '../../widgets/custom_checkbox.dart';
import '../../widgets/custom_date.dart';
import '../../widgets/custom_dropdown.dart';
import '../../widgets/custom_input.dart';
import '../../widgets/custom_radio.dart';
import 'auth_service.dart';
import 'auth_view.dart';
import 'widgets/sign_up_item.dart';
import '../notifications/push_notification_service.dart';

class AuthController extends GetxController {
  final authService = AuthService();
  final localDb = GetStorage();
  final _secureAuth = SecureAuthStorage.instance;

  final emailValidationStatus = ApiState.initial.obs;
  final signUpStatus = ApiState.initial.obs;
  final loginWithPassStatus = ApiState.initial.obs;
  final loginWithCodeStatus = ApiState.initial.obs;
  final getComponentStatus = ApiState.initial.obs;
  final registerUserStatus = ApiState.initial.obs;
  final myEventsStatus = ApiState.initial.obs;

  /// Events available after login / for switch-event. Cleared on apply.
  final RxList<MyEvent> availableEvents = <MyEvent>[].obs;

  /// True when login succeeded but the user must pick among multiple events.
  bool needsEventSelection = false;

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

  /// Returns null if the API failed, true if the email can sign in (exists + password),
  /// false if they should go to sign-up.
  Future<bool?> onEmailValidation({required String email}) async {
    bool? canLogin;
    await handleApiClient(
      onStateChanged: emailValidationStatus,
      handleApiCall: () async {
        final response = await authService.emailValidationCheck(email: email);
        final data = response.data;
        if (data is! Map) {
          canLogin = false;
          return;
        }
        final exists = data['exists'] == true;
        final hasPassword = data['has_password'] == true;
        canLogin = exists && hasPassword;
      },
    );
    return canLogin;
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
                validator: (value) => InputValidators.requiredField(
                  value,
                  label: item.fieldLabel,
                ),
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
        final data = response.data;
        if (data is! Map) {
          throw Exception('Unexpected login response');
        }

        final token = data['token'];
        final user = data['user'];
        if (token is! String || token.isEmpty) {
          throw Exception('Login did not return a token');
        }

        await _secureAuth.saveToken(token);
        if (user is Map) {
          await localDb.write(
            LocalKeyHelper.userInfo,
            Map<String, dynamic>.from(user),
          );
        }

        await _resolveEventContext();
        await PushNotificationService.instance.registerCurrentToken();
        ToastMsg.showSuccessMessage("Logged in successfully!");
        onSuccess.call();
      },
    );
  }

  /// After login: 1 event → auto-select; several → set [needsEventSelection].
  Future<void> _resolveEventContext() async {
    needsEventSelection = false;
    availableEvents.clear();
    try {
      final events = await _fetchMyEvents();
      if (events.isEmpty) return;

      if (events.length == 1) {
        applySelectedEvent(events.first);
        return;
      }

      availableEvents.assignAll(events);
      needsEventSelection = true;
    } catch (_) {
      // Keep default subdomain from AppConfig; reception still works via header.
    }
  }

  /// Loads events for the Switch Event screen (drawer).
  Future<List<MyEvent>> loadMyEvents() async {
    List<MyEvent> events = [];
    await handleApiClient(
      onStateChanged: myEventsStatus.call,
      handleApiCall: () async {
        events = await _fetchMyEvents();
        availableEvents.assignAll(events);
      },
    );
    return events;
  }

  Future<List<MyEvent>> _fetchMyEvents() async {
    final response = await authService.myEvents();
    final payload = response.data;
    final list = payload is Map ? payload['data'] : null;
    if (list is! List) return [];

    return list
        .whereType<Map>()
        .map((e) => MyEvent.fromJson(Map<String, dynamic>.from(e)))
        .where((e) => e.subdomain.isNotEmpty)
        .toList();
  }

  void applySelectedEvent(MyEvent event) {
    if (event.subdomain.isNotEmpty) {
      AppDataProvider.obj.setSubDomain = event.subdomain;
    }
    if (event.uuid.isNotEmpty) {
      AppDataProvider.obj.eventUuid = event.uuid;
    }
    needsEventSelection = false;
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

  @override
  void onClose() {
    for (final c in givenInputs.values) {
      c.dispose();
    }
    givenInputs.clear();
    super.onClose();
  }
}
