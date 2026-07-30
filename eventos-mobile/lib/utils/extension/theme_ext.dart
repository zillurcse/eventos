import 'package:flutter/material.dart';

extension ThemeExt on BuildContext {
  ThemeData get theme => Theme.of(this);


  Color get backgroundColor => theme.colorScheme.onSurfaceVariant;

  Color get accentPrimary => theme.colorScheme.primary;
  Color get accentHover => theme.colorScheme.primary;

  Color get primaryTheme => theme.colorScheme.primary;
  Color get primaryHover => theme.colorScheme.onPrimary;
  Color get primaryFocused => theme.colorScheme.primaryContainer;

  Color get heading => theme.colorScheme.surfaceContainerLowest;
  Color get body => theme.colorScheme.surfaceContainerLow;
  Color get caption => theme.colorScheme.surfaceContainer;
  Color get ghost => theme.colorScheme.surfaceContainerHigh;

  Color get tertiaryText => theme.colorScheme.tertiary;
  Color get stroke => theme.colorScheme.onTertiary;
  Color get strokeLight => theme.colorScheme.onTertiaryContainer;

  Color get yellowWarning => theme.colorScheme.secondary;
  Color get yellowWarningLight => theme.colorScheme.onSecondary;
  Color get greenPositive => theme.colorScheme.surface;
  Color get greenPositiveLight => theme.colorScheme.onSurface;
  Color get redError => theme.colorScheme.error;
  Color get redErrorLight => theme.colorScheme.onError;
  Color get blueInfo => theme.colorScheme.outline;
  Color get blueInfoLight => theme.colorScheme.outlineVariant;


  TextStyle? get h5 => theme.textTheme.displayLarge;
  TextStyle? get h4 => theme.textTheme.displayMedium;
  TextStyle? get h3 => theme.textTheme.displaySmall;
  TextStyle? get h2 => theme.textTheme.headlineLarge;
  TextStyle? get h1 => theme.textTheme.headlineMedium;

  TextStyle? get titleLarge => theme.textTheme.headlineSmall;
  TextStyle? get titleRegular => theme.textTheme.titleLarge;

  TextStyle? get bodyLarge => theme.textTheme.titleMedium;
  TextStyle? get bodyRegular => theme.textTheme.titleSmall;

  TextStyle? get specialCaption1 => theme.textTheme.bodyLarge;
  TextStyle? get specialCaption2 => theme.textTheme.bodyMedium;
  TextStyle? get specialLabelCapital => theme.textTheme.bodySmall;

  TextStyle? get buttonLabelLarge => theme.textTheme.labelLarge;
  TextStyle? get buttonMediumBold => theme.textTheme.labelMedium;
  TextStyle? get buttonSmallBold => theme.textTheme.labelSmall;




}