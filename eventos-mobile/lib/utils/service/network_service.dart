import 'package:connectivity_plus/connectivity_plus.dart';

class NetworkService {
  static Future<bool> hasInternet() async {
    Connectivity connectivity = Connectivity();
    final checkConnectivity = await connectivity.checkConnectivity();
    return !checkConnectivity.contains(ConnectivityResult.none);
  }
}
