import '../../utils/config/app_config.dart';
import '../../utils/helpers/type_helper.dart';
import '../delegate_detail_model.dart';
import '../delegate_item_model.dart';
import '../delegate_page_model.dart';

/// Maps EventOS `GET /events/{uuid}/delegates` into existing delegate UI models.
class DelegateMapper {
  DelegateMapper._();

  static DelegatePageModel pageFromV1(
    List<dynamic> rows, {
    int currentPage = 1,
    String? nextPageUrl,
    Set<String> favoritedIds = const {},
  }) {
    final delegates = rows
        .whereType<Map>()
        .map((e) => itemFromV1(
              Map<String, dynamic>.from(e),
              favoritedIds: favoritedIds,
            ))
        .toList();

    return DelegatePageModel(
      currentPage: currentPage,
      delegates: delegates,
      nextPageUrl: nextPageUrl,
    );
  }

  static DelegateItemModel itemFromV1(
    Map<String, dynamic> json, {
    Set<String> favoritedIds = const {},
  }) {
    final uuid = (json['id'] ?? '').toString();
    return DelegateItemModel(
      id: TypeHelper.toInt(uuid),
      name: json['name']?.toString() ?? '',
      image: AppConfig.resolveMediaUrl(
        (json['avatar_url'] ?? json['image'] ?? '').toString(),
      ),
      designation: (json['job_title'] ?? json['designation'] ?? '').toString(),
      company: json['company']?.toString() ?? '',
      country: json['country']?.toString() ?? '',
      isFavorite: favoritedIds.contains(uuid),
    );
  }

  static DelegateDetailModel detailFromV1(
    Map<String, dynamic> json, {
    bool isFavorite = false,
  }) {
    final social = json['social'] is Map
        ? Map<String, dynamic>.from(json['social'] as Map)
        : <String, dynamic>{};

    final website = social['website']?.toString() ??
        social['web']?.toString() ??
        json['website']?.toString();

    return DelegateDetailModel(
      id: TypeHelper.toInt(json['id']),
      name: json['name']?.toString() ?? '',
      image: AppConfig.resolveMediaUrl(
        (json['avatar_url'] ?? json['image'] ?? '').toString(),
      ),
      designation: (json['job_title'] ?? json['designation'] ?? '').toString(),
      company: json['company']?.toString() ?? '',
      country: json['country']?.toString() ?? '',
      about: (json['bio'] ?? json['about'])?.toString(),
      website: website,
      isFavorite: isFavorite,
    );
  }

  /// UI sort keys → API `sort` query (`az` | `za`).
  static String? apiSort(String? sortType) {
    switch (sortType) {
      case 'name_desc':
      case 'za':
        return 'za';
      case 'name_asc':
      case 'az':
        return 'az';
      default:
        return sortType;
    }
  }
}
