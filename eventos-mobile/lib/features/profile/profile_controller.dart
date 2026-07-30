import 'dart:io';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../models/profile_model.dart';
import '../../utils/enum/enums.dart';
import '../../utils/helpers/country_state_data.dart';
import '../../utils/helpers/helper_functions.dart';
import 'profile_service.dart';

class ProfileController extends GetxController {
  final _service = ProfileService();

  // ── Profile state ─────────────────────────────────────────────────────────
  final dataStatus = ApiState.initial.obs;
  final Rxn<ProfileModel> profileData = Rxn<ProfileModel>();

  // ── Form Controllers ──────────────────────────────────────────────────────
  final firstNameCtrl = TextEditingController();
  final lastNameCtrl = TextEditingController();
  final designationCtrl = TextEditingController();
  final companyCtrl = TextEditingController();
  final aboutCtrl = TextEditingController();
  final mobileNumberCtrl = TextEditingController();
  final addressCtrl = TextEditingController();
  final cityTownCtrl = TextEditingController();
  final websiteCtrl = TextEditingController();
  final purposeOfVisitCtrl = TextEditingController();
  final purchasingDecisionCtrl = TextEditingController();
  final selectedGender = RxnString();
  final selectedCountry = RxnString();
  final selectedState = RxnString();
  final selectedInterests = <String>[].obs;

  List<String> get availableCountries => countryStateMap.keys.toList();
  List<String> get availableStates {
    if (selectedCountry.value == null || !countryStateMap.containsKey(selectedCountry.value)) {
      return [];
    }
    return countryStateMap[selectedCountry.value]!;
  }

  void updateCountry(String? country) {
    selectedCountry.value = country;
    selectedState.value = null; // Reset state when country changes
  }

  void updateState(String? state) {
    selectedState.value = state;
  }

  @override
  void onInit() {
    super.onInit();
    fetchProfile();
  }

  // ── API: fetch profile ────────────────────────────────────────────────────
  Future<void> fetchProfile() async {
    await handleApiClient(
      onStateChanged: (state) => dataStatus(state),
      handleApiCall: () async {
        final response = await _service.getProfile();
        if (response.data is Map) {
          final data = Map<String, dynamic>.from(response.data as Map);
          if (data['status'] == 'success' && data['data'] != null) {
            final model = ProfileModel.fromJson(
              Map<String, dynamic>.from(data['data'] as Map),
            );
            profileData.value = model;
            _populateForm(model);
          }
        }
      },
    );
  }

  void _populateForm(ProfileModel model) {
    firstNameCtrl.text = model.profileData?.firstName ?? model.firstName ?? '';
    lastNameCtrl.text = model.profileData?.lastName ?? model.lastName ?? '';
    designationCtrl.text = model.profileData?.designation ?? model.designation ?? '';
    companyCtrl.text = model.profileData?.organisation ?? model.company ?? '';
    aboutCtrl.text = model.profileData?.about ?? model.about ?? '';
    mobileNumberCtrl.text = model.mobileNumber ?? '';
    addressCtrl.text = model.address ?? '';
    cityTownCtrl.text = model.profileData?.cityTown ?? model.cityTown ?? '';
    websiteCtrl.text = model.profileData?.website ?? model.website ?? '';
    purposeOfVisitCtrl.text = model.profileData?.purposeOfVisit ?? '';
    purchasingDecisionCtrl.text = model.profileData?.purchasingDecision ?? '';
    
    selectedGender.value = model.profileData?.gender ?? model.gender;
    selectedCountry.value = model.profileData?.country ?? model.country;
    selectedState.value = model.profileData?.state ?? model.state;
    
    if (model.interests != null && model.interests!.isNotEmpty) {
      selectedInterests.value = model.interests!.split(',').map((e) => e.trim()).where((e) => e.isNotEmpty).toList();
    }
  }

  // ── API: update profile ───────────────────────────────────────────────────
  Future<void> updateProfileData() async {
    final Map<String, dynamic> payload = {
      "first_name": firstNameCtrl.text.trim(),
      "last_name": lastNameCtrl.text.trim(),
      "designation": designationCtrl.text.trim(),
      "company": companyCtrl.text.trim(),
      "about": aboutCtrl.text.trim(),
      "mobile_number": mobileNumberCtrl.text.trim(),
      "address": addressCtrl.text.trim(),
      "city_town": cityTownCtrl.text.trim(),
      "website": websiteCtrl.text.trim(),
      "interests": selectedInterests.join(", "),
    };

    if (selectedCountry.value != null) payload["country"] = selectedCountry.value;
    if (selectedState.value != null) payload["state"] = selectedState.value;
    if (selectedGender.value != null) payload["gender"] = selectedGender.value;
    
    // Additional fields mapped for profile data based on standard requirements if needed
    // The payload asks for specific keys matching the model

    await handleApiClient(
      onStateChanged: (state) {
        if (state == ApiState.loading) {
          // optionally show a loader dialogue
        }
      },
      handleApiCall: () async {
        final response = await _service.updateProfile(payload);
        if (response.statusCode == 200 || response.statusCode == 201) {
          Get.snackbar('Success', 'Profile updated successfully',
              snackPosition: SnackPosition.BOTTOM, backgroundColor: Colors.green, colorText: Colors.white);
          fetchProfile(); // refresh after update
        }
      },
    );
  }

  // ── API: update profile photo ─────────────────────────────────────────────
  Future<void> updateProfilePhoto(String filePath) async {
    await handleApiClient(
      onStateChanged: (state) {
        if (state == ApiState.loading) {
          // optionally show loader
        }
      },
      handleApiCall: () async {
        final response = await _service.updateProfilePhoto(filePath);
        if (response.statusCode == 200 || response.statusCode == 201) {
          Get.snackbar('Success', 'Profile photo updated successfully',
              snackPosition: SnackPosition.BOTTOM, backgroundColor: Colors.green, colorText: Colors.white);
          fetchProfile(); // refresh after update
        }
      },
    );
  }
}
