# Keep Flutter app symbols for crash deobfuscation — do NOT commit these.
-keepattributes SourceFile,LineNumberTable
-renamesourcefileattribute SourceFile

# Flutter / Play Core
-keep class io.flutter.** { *; }
-keep class com.google.firebase.** { *; }
