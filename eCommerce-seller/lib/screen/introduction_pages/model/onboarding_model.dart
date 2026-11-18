class OnboardingModel {
  bool? success;
  String? message;
  List<OnboardingData>? data;

  OnboardingModel({this.success, this.message, this.data});

  OnboardingModel.fromJson(Map<String, dynamic> json) {
    success = json['success'];
    message = json['message'];
    if (json['data'] != null) {
      data = <OnboardingData>[];
      json['data'].forEach((v) {
        data!.add(OnboardingData.fromJson(v));
      });
    }
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['success'] = success;
    data['message'] = message;
    if (this.data != null) {
      data['data'] = this.data!.map((v) => v.toJson()).toList();
    }
    return data;
  }
}

class OnboardingData {
  int? id;
  String? surface;
  String? title;
  String? subtitle;
  String? image;
  String? imageDisplayMode;
  String? fontColor;
  String? nextButtonBackgroundColor;
  String? nextButtonTextColor;
  String? backgroundType;
  String? backgroundColor;
  String? backgroundImage;
  bool? showSkip;

  OnboardingData({
    this.id,
    this.surface,
    this.title,
    this.subtitle,
    this.image,
    this.imageDisplayMode,
    this.fontColor,
    this.nextButtonBackgroundColor,
    this.nextButtonTextColor,
    this.backgroundType,
    this.backgroundColor,
    this.backgroundImage,
    this.showSkip,
  });

  OnboardingData.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    surface = json['surface'];
    title = json['title'];
    subtitle = json['subtitle'];
    image = json['image'];
    imageDisplayMode = json['image_display_mode'];
    fontColor = json['font_color'];
    nextButtonBackgroundColor = json['next_button_background_color'];
    nextButtonTextColor = json['next_button_text_color'];
    backgroundType = json['background_type'];
    backgroundColor = json['background_color'];
    backgroundImage = json['background_image'];
    showSkip = json['show_skip'] is bool
        ? json['show_skip']
        : json['show_skip']?.toString() == 'true';
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = <String, dynamic>{};
    data['id'] = id;
    data['surface'] = surface;
    data['title'] = title;
    data['subtitle'] = subtitle;
    data['image'] = image;
    data['image_display_mode'] = imageDisplayMode;
    data['font_color'] = fontColor;
    data['next_button_background_color'] = nextButtonBackgroundColor;
    data['next_button_text_color'] = nextButtonTextColor;
    data['background_type'] = backgroundType;
    data['background_color'] = backgroundColor;
    data['background_image'] = backgroundImage;
    data['show_skip'] = showSkip;
    return data;
  }
}
