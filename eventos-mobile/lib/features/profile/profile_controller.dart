import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:get_storage/get_storage.dart';
import '../../models/profile_model.dart';
import '../../utils/enum/enums.dart';
import '../../utils/helpers/country_state_data.dart';
import '../../utils/helpers/helper_functions.dart';
import '../../utils/helpers/local_key.dart';
import '../root/root_controller.dart';
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
  final geoReady = false.obs;

  List<String> get availableCountries => CountryStateData.countries;
  List<String> get availableStates =>
      CountryStateData.statesFor(selectedCountry.value);

  void updateCountry(String? country) {
    selectedCountry.value = country;
    selectedState.value = null;
  }

  void updateState(String? state) {
    selectedState.value = state;
  }

  @override
  void onClose() {
    firstNameCtrl.dispose();
    lastNameCtrl.dispose();
    designationCtrl.dispose();
    companyCtrl.dispose();
    aboutCtrl.dispose();
    mobileNumberCtrl.dispose();
    addressCtrl.dispose();
    cityTownCtrl.dispose();
    websiteCtrl.dispose();
    purposeOfVisitCtrl.dispose();
    purchasingDecisionCtrl.dispose();
    super.onClose();
  }

  @override
  void onInit() {
    super.onInit();
    _loadGeo();
    fetchProfile();
  }

  Future<void> _loadGeo() async {
    await CountryStateData.ensureLoaded();
    if (!isClosed) geoReady.value = true;
  }

  Future<void> fetchProfile() async {
    await handleApiClient(
      onStateChanged: (state) => dataStatus(state),
      handleApiCall: () async {
        final response = await _service.getProfile();
        final body = response.data;
        if (body is! Map) return;

        final raw = body['data'] is Map
            ? Map<String, dynamic>.from(body['data'] as Map)
            : Map<String, dynamic>.from(body);
        final model = ProfileModel.fromJson(raw);
        profileData.value = model;
        _populateForm(model);
        await persistIdentity(model);
      },
    );
  }

  /// Keep local user storage + shell avatar/name in sync with the event profile.
  static Future<void> persistIdentity(ProfileModel model) async {
    final first = model.profileData?.firstName ?? model.firstName;
    final last = model.profileData?.lastName ?? model.lastName;

    if (Get.isRegistered<RootController>()) {
      await Get.find<RootController>().applyEventProfile(
        name: model.name,
        firstName: first,
        lastName: last,
        email: model.email,
        avatarUrl: model.profilePhotoUrl,
      );
      return;
    }

    await persistAvatar(model.profilePhotoUrl);
  }

  /// Keep local user storage + shell avatar in sync with the event profile photo.
  static Future<void> persistAvatar(String? avatarUrl) async {
    if (avatarUrl == null || avatarUrl.isEmpty) return;

    if (Get.isRegistered<RootController>()) {
      await Get.find<RootController>().applyProfilePhoto(avatarUrl);
      return;
    }

    final storage = GetStorage();
    final raw = storage.read(LocalKeyHelper.userInfo);
    final map = raw is Map
        ? Map<String, dynamic>.from(raw)
        : <String, dynamic>{};
    map['avatar_url'] = avatarUrl;
    map['profile_photo_url'] = avatarUrl;
    await storage.write(LocalKeyHelper.userInfo, map);
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

    selectedGender.value = _nullableField(model.profileData?.gender ?? model.gender);
    selectedCountry.value = _nullableField(model.profileData?.country ?? model.country);
    selectedState.value = _nullableField(model.profileData?.state ?? model.state);

    if (model.interests != null && model.interests!.isNotEmpty) {
      selectedInterests.value = model.interests!
          .split(',')
          .map((e) => e.trim())
          .where((e) => e.isNotEmpty)
          .toList();
    }
  }

  /// API often returns `""` for unset selects; DropdownButton needs null.
  static String? _nullableField(String? value) {
    if (value == null || value.trim().isEmpty) return null;
    return value;
  }

  Future<void> updateProfileData() async {
    final Map<String, dynamic> payload = {
      'first_name': firstNameCtrl.text.trim(),
      'last_name': lastNameCtrl.text.trim(),
      'job_title': designationCtrl.text.trim(),
      'company': companyCtrl.text.trim(),
      'bio': aboutCtrl.text.trim(),
      'phone': mobileNumberCtrl.text.trim(),
      'city': cityTownCtrl.text.trim(),
      'interests': selectedInterests.toList(),
    };

    if (selectedCountry.value != null) payload['country'] = selectedCountry.value;
    if (selectedState.value != null) payload['state'] = selectedState.value;
    if (selectedGender.value != null) payload['gender'] = selectedGender.value;
    if (websiteCtrl.text.trim().isNotEmpty) {
      payload['social'] = {'website': websiteCtrl.text.trim()};
    }

    await handleApiClient(
      onStateChanged: (_) {},
      handleApiCall: () async {
        final response = await _service.updateProfile(payload);
        if (response.statusCode == 200 || response.statusCode == 201) {
          Get.snackbar(
            'Success',
            'Profile updated successfully',
            snackPosition: SnackPosition.BOTTOM,
            backgroundColor: Colors.green,
            colorText: Colors.white,
          );
          fetchProfile();
        }
      },
    );
  }

  Future<void> updateProfilePhoto(String filePath) async {
    await handleApiClient(
      onStateChanged: (_) {},
      handleApiCall: () async {
        final response = await _service.updateProfilePhoto(filePath);
        if (response.statusCode == 200 || response.statusCode == 201) {
          Get.snackbar(
            'Success',
            'Profile photo updated successfully',
            snackPosition: SnackPosition.BOTTOM,
            backgroundColor: Colors.green,
            colorText: Colors.white,
          );
          fetchProfile();
        }
      },
    );
  }
}
