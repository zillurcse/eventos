import '../utils/helpers/type_helper.dart';

class User {
  final int id;
  final String name;
  final String email;
  final String userName;
  final int eventId;
  final String uid;
  final String emailVerifiedAt;
  final String accessCode;
  final int isApkAuth;
  final String timezone;
  final int currentTeamId;
  final String profilePhotoPath;
  final String createdAt;
  final String updatedAt;
  final String about;
  final String designation;
  final String company;
  final String address;
  final String country;
  final String industry;
  final String interests;
  final String firstName;
  final String lastName;
  final String gender;
  final String state;
  final String cityTown;
  final String website;
  final String registerAs;
  final String instituteOfWorkStudy;
  final String planYearOfAddmission;
  final String specialtiesOrProgramsMostInterestedIn;
  final String mobileNumber;
  final int isOnborded;
  final int meetingLimits;
  final int hasPermissionForMeeting;
  final int hasPermissionForChat;
  final int isFirstVisit;
  final String deletedAt;
  final String notificationSeen;
  final String hasNewMessages;
  final int hasSpecialAccess;
  final String graduationYear;
  final String governorate;
  final String programme;
  final String studentId;
  final String googleId;
  final String facebookId;
  final String linkedinId;
  final String socialMediaData;
  final String socialMediaAvatar;
  final String profileData;
  final String authToken;
  final int eventLimit;
  final String expireDate;
  final String packageInfo;
  final String profilePhotoUrl;

  User({
    this.id = 0,
    this.name = '',
    this.email = '',
    this.userName = '',
    this.eventId = 0,
    this.uid = '',
    this.emailVerifiedAt = '',
    this.accessCode = '',
    this.isApkAuth = 0,
    this.timezone = '',
    this.currentTeamId = 0,
    this.profilePhotoPath = '',
    this.createdAt = '',
    this.updatedAt = '',
    this.about = '',
    this.designation = '',
    this.company = '',
    this.address = '',
    this.country = '',
    this.industry = '',
    this.interests = '',
    this.firstName = '',
    this.lastName = '',
    this.gender = '',
    this.state = '',
    this.cityTown = '',
    this.website = '',
    this.registerAs = '',
    this.instituteOfWorkStudy = '',
    this.planYearOfAddmission = '',
    this.specialtiesOrProgramsMostInterestedIn = '',
    this.mobileNumber = '',
    this.isOnborded = 0,
    this.meetingLimits = 0,
    this.hasPermissionForMeeting = 0,
    this.hasPermissionForChat = 0,
    this.isFirstVisit = 0,
    this.deletedAt = '',
    this.notificationSeen = '',
    this.hasNewMessages = '',
    this.hasSpecialAccess = 0,
    this.graduationYear = '',
    this.governorate = '',
    this.programme = '',
    this.studentId = '',
    this.googleId = '',
    this.facebookId = '',
    this.linkedinId = '',
    this.socialMediaData = '',
    this.socialMediaAvatar = '',
    this.profileData = '',
    this.authToken = '',
    this.eventLimit = 0,
    this.expireDate = '',
    this.packageInfo = '',
    this.profilePhotoUrl = '',
  });

  factory User.fromJson(Map<String, dynamic> json) {
    final firstNameRaw = json['first_name'] as String? ?? '';
    final lastNameRaw = json['last_name'] as String? ?? '';
    var name = json['name'] as String? ?? '';
    if (name.trim().isEmpty) {
      name = '$firstNameRaw $lastNameRaw'.trim();
    }
    final parts = name.trim().split(RegExp(r'\s+'));
    final firstFromName = parts.isNotEmpty ? parts.first : '';
    final lastFromName = parts.length > 1 ? parts.sublist(1).join(' ') : '';

    return User(
      id: TypeHelper.toInt(json['id']),
      name: name,
      email: json['email'] as String? ?? '',
      userName: json['user_name'] as String? ?? '',
      eventId: TypeHelper.toInt(json['event_id']),
      uid: json['uid']?.toString() ?? json['id']?.toString() ?? '',
      emailVerifiedAt: json['email_verified_at'] as String? ?? '',
      accessCode: json['access_code'] as String? ?? '',
      isApkAuth: json['is_apk_auth'] as int? ?? 0,
      timezone: json['timezone'] as String? ?? '',
      currentTeamId: json['current_team_id'] as int? ?? 0,
      profilePhotoPath: json['profile_photo_path'] as String? ?? '',
      createdAt: json['created_at'] as String? ?? '',
      updatedAt: json['updated_at'] as String? ?? '',
      about: json['about'] as String? ?? '',
      designation: json['designation'] as String? ?? '',
      company: json['company'] as String? ?? '',
      address: json['address'] as String? ?? '',
      country: json['country'] as String? ?? '',
      industry: json['industry'] as String? ?? '',
      interests: json['interests'] as String? ?? '',
      firstName: firstNameRaw.isNotEmpty ? firstNameRaw : firstFromName,
      lastName: lastNameRaw.isNotEmpty ? lastNameRaw : lastFromName,
      gender: json['gender'] as String? ?? '',
      state: json['state'] as String? ?? '',
      cityTown: json['city_town'] as String? ?? '',
      website: json['website'] as String? ?? '',
      registerAs: json['register_as'] as String? ?? '',
      instituteOfWorkStudy: json['institute_of_work_study'] as String? ?? '',
      planYearOfAddmission: json['plan_year_of_addmission'] as String? ?? '',
      specialtiesOrProgramsMostInterestedIn: json['specialties_or_programs_most_interested_in'] as String? ?? '',
      mobileNumber: json['mobile_number'] as String? ?? '',
      isOnborded: json['is_onborded'] as int? ?? 0,
      meetingLimits: json['meeting_limits'] as int? ?? 0,
      hasPermissionForMeeting: json['has_permission_for_meeting'] as int? ?? 0,
      hasPermissionForChat: json['has_permission_for_chat'] as int? ?? 0,
      isFirstVisit: json['is_first_visit'] as int? ?? 0,
      deletedAt: json['deleted_at'] as String? ?? '',
      notificationSeen: json['notification_seen'] as String? ?? '',
      hasNewMessages: json['has_new_messages'] as String? ?? '',
      hasSpecialAccess: json['has_special_access'] as int? ?? 0,
      graduationYear: json['graduation_year'] as String? ?? '',
      governorate: json['governorate'] as String? ?? '',
      programme: json['programme'] as String? ?? '',
      studentId: json['student_id'] as String? ?? '',
      googleId: json['google_id'] as String? ?? '',
      facebookId: json['facebook_id'] as String? ?? '',
      linkedinId: json['linkedin_id'] as String? ?? '',
      socialMediaData: json['social_media_data'] as String? ?? '',
      socialMediaAvatar: json['social_media_avatar'] as String? ?? '',
      profileData: json['profile_data'] as String? ?? '',
      authToken: json['auth_token'] as String? ?? '',
      eventLimit: json['event_limit'] as int? ?? 0,
      expireDate: json['expire_date'] as String? ?? '',
      packageInfo: json['package_info'] as String? ?? '',
      profilePhotoUrl: _photoUrl(json),
    );
  }

  /// V1 APIs use `avatar_url`; older mobile payloads used `profile_photo_url`.
  static String _photoUrl(Map<String, dynamic> json) {
    final legacy = json['profile_photo_url']?.toString() ?? '';
    if (legacy.isNotEmpty) return legacy;
    return json['avatar_url']?.toString() ?? '';
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'email': email,
      'user_name': userName,
      'event_id': eventId,
      'uid': uid,
      'email_verified_at': emailVerifiedAt,
      'access_code': accessCode,
      'is_apk_auth': isApkAuth,
      'timezone': timezone,
      'current_team_id': currentTeamId,
      'profile_photo_path': profilePhotoPath,
      'created_at': createdAt,
      'updated_at': updatedAt,
      'about': about,
      'designation': designation,
      'company': company,
      'address': address,
      'country': country,
      'industry': industry,
      'interests': interests,
      'first_name': firstName,
      'last_name': lastName,
      'gender': gender,
      'state': state,
      'city_town': cityTown,
      'website': website,
      'register_as': registerAs,
      'institute_of_work_study': instituteOfWorkStudy,
      'plan_year_of_addmission': planYearOfAddmission,
      'specialties_or_programs_most_interested_in': specialtiesOrProgramsMostInterestedIn,
      'mobile_number': mobileNumber,
      'is_onborded': isOnborded,
      'meeting_limits': meetingLimits,
      'has_permission_for_meeting': hasPermissionForMeeting,
      'has_permission_for_chat': hasPermissionForChat,
      'is_first_visit': isFirstVisit,
      'deleted_at': deletedAt,
      'notification_seen': notificationSeen,
      'has_new_messages': hasNewMessages,
      'has_special_access': hasSpecialAccess,
      'graduation_year': graduationYear,
      'governorate': governorate,
      'programme': programme,
      'student_id': studentId,
      'google_id': googleId,
      'facebook_id': facebookId,
      'linkedin_id': linkedinId,
      'social_media_data': socialMediaData,
      'social_media_avatar': socialMediaAvatar,
      'profile_data': profileData,
      'auth_token': authToken,
      'event_limit': eventLimit,
      'expire_date': expireDate,
      'package_info': packageInfo,
      'profile_photo_url': profilePhotoUrl,
    };
  }
}