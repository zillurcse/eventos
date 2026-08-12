import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:photo_view/photo_view.dart';

/// Full-screen zoomable image viewer.
/// Accepts either a remote [url] or a local [filePath] - pass exactly one.
class ImageViewerPage extends StatelessWidget {
  final String? url;
  final String? filePath;

  const ImageViewerPage({super.key, this.url, this.filePath})
      : assert(url != null || filePath != null,
            'Provide either url or filePath');

  @override
  Widget build(BuildContext context) {
    // Force dark status bar icons so the close button is visible
    SystemChrome.setSystemUIOverlayStyle(SystemUiOverlayStyle.light);

    final ImageProvider provider = filePath != null
        ? FileImage(File(filePath!))
        : NetworkImage(url!) as ImageProvider;

    return Scaffold(
      backgroundColor: Colors.black,
      body: Stack(
        children: [
          // ── Zoomable image ────────────────────────────────────────────
          PhotoView(
            imageProvider: provider,
            minScale: PhotoViewComputedScale.contained,
            maxScale: PhotoViewComputedScale.covered * 4,
            initialScale: PhotoViewComputedScale.contained,
            backgroundDecoration: const BoxDecoration(color: Colors.black),
            loadingBuilder: (context, event) => const Center(
              child: CircularProgressIndicator(color: Colors.white),
            ),
            errorBuilder: (context, error, stackTrace) => const Center(
              child: Icon(Icons.broken_image, color: Colors.white54, size: 64),
            ),
          ),

          // ── Back button ───────────────────────────────────────────────
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.all(8),
              child: GestureDetector(
                onTap: () => Navigator.pop(context),
                child: Container(
                  decoration: BoxDecoration(
                    color: Colors.black45,
                    shape: BoxShape.circle,
                  ),
                  padding: const EdgeInsets.all(8),
                  child: const Icon(Icons.close, color: Colors.white, size: 22),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
