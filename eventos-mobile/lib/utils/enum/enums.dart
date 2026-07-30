enum ResponsiveState { mobile, tablet, desktop }

enum ImageSource { asset, network, svg }

enum ApiState { initial, loading, loaded, error }

enum AuthFlow {
  registerWithForm,
  loginWithPass,
  registerWithOtp,
  loginWithCode,
}

enum PostTypes {
  post,
  image,
  video,
  pdf,
  poll,
  offering,
  lookingFor;

  static PostTypes fromString(String? value) {
    if (value == null || value.isEmpty) {
      return PostTypes.post;
    }

    switch (value.toLowerCase()) {
      case 'poll':
        return PostTypes.poll;
      case 'looking-for':
        return PostTypes.lookingFor;
      case 'offering':
        return PostTypes.offering;
      case 'pdf':
        return PostTypes.pdf;
      case 'video':
        return PostTypes.video;
      case 'image':
      case 'photos':
        return PostTypes.image;
      case 'post':
      default:
        return PostTypes.post;
    }
  }
}
