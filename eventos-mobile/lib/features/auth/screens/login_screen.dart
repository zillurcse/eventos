import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/theme/app_theme.dart';
import '../../bootstrap/event_provider.dart';
import '../auth_provider.dart';
import '../widgets/auth_form_controls.dart';
import '../widgets/expouse_brand.dart';

enum _LoginStep { email, password, otp }

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _otpController = TextEditingController();
  final _formKey = GlobalKey<FormState>();

  _LoginStep _step = _LoginStep.email;
  bool _agreed = false;
  bool _submitting = false;
  String? _error;
  String? _otpInfo;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    _otpController.dispose();
    super.dispose();
  }

  Future<void> _onContinue() async {
    if (!_agreed) {
      setState(() => _error = 'Please agree to the Terms and Conditions and Privacy Policy.');
      return;
    }
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _submitting = true;
      _error = null;
    });

    final email = _emailController.text.trim();
    final event = ref.read(eventProvider);
    final auth = ref.read(authProvider.notifier);

    try {
      final result = await auth.checkEmail(email);

      if (result.exists && result.hasPassword && event.signupEnabled) {
        setState(() => _step = _LoginStep.password);
      } else if (event.otpEnabled) {
        await auth.requestOtp(email);
        setState(() {
          _otpInfo = 'We’ve emailed a 6-digit code to $email. It expires in 10 minutes.';
          _step = _LoginStep.otp;
        });
      } else if (!result.exists && event.signupEnabled) {
        if (mounted) {
          context.push('/signup', extra: email);
        }
      } else if (result.exists && result.hasPassword) {
        setState(() {
          _error =
              'Password sign-in is currently disabled for this event. Contact the organizer.';
        });
      } else if (!result.exists) {
        setState(() {
          _error = 'This event is invite-only — ask the organizer for access.';
        });
      } else {
        setState(() {
          _error =
              'This account can only sign in with a method that’s currently disabled.';
        });
      }
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _onPasswordLogin() async {
    if (_passwordController.text.isEmpty) {
      setState(() => _error = 'Enter your password.');
      return;
    }

    setState(() {
      _submitting = true;
      _error = null;
    });

    try {
      await ref.read(authProvider.notifier).login(
            _emailController.text.trim(),
            _passwordController.text,
          );
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _onOtpLogin() async {
    setState(() {
      _submitting = true;
      _error = null;
    });

    try {
      await ref.read(authProvider.notifier).requestOtp(_emailController.text.trim());
      setState(() {
        _otpInfo =
            'We’ve emailed a 6-digit code to ${_emailController.text.trim()}. It expires in 10 minutes.';
        _step = _LoginStep.otp;
      });
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _onVerifyOtp() async {
    final code = _otpController.text.trim();
    if (code.length < 6) {
      setState(() => _error = 'Enter the 6-digit code from your email.');
      return;
    }

    setState(() {
      _submitting = true;
      _error = null;
    });

    try {
      await ref.read(authProvider.notifier).verifyOtp(
            _emailController.text.trim(),
            code,
          );
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  void _backToEmail() {
    setState(() {
      _step = _LoginStep.email;
      _passwordController.clear();
      _otpController.clear();
      _otpInfo = null;
      _error = null;
    });
  }

  Future<void> _changeEvent() async {
    await ref.read(authProvider.notifier).logout();
    await ref.read(eventProvider.notifier).clearEvent();
    if (mounted) context.go('/event');
  }

  void _socialSoon(String provider) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('$provider sign-in will be available soon.')),
    );
  }

  @override
  Widget build(BuildContext context) {
    final event = ref.watch(eventProvider);
    final showSocial = event.socialChannels.values.any((v) => v);

    return Scaffold(
      backgroundColor: AppColors.screenBg,
      appBar: const AuthHeaderBar(),
      body: SafeArea(
        child: Column(
          children: [
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(24, 20, 24, 12),
                child: Form(
                  key: _formKey,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      if (_step == _LoginStep.email) ...[
                        _EventIdentity(event: event),
                        const SizedBox(height: 22),
                        AuthTextField(
                          controller: _emailController,
                          hint: 'Enter Email',
                          keyboardType: TextInputType.emailAddress,
                          textInputAction: TextInputAction.done,
                          autofillHints: const [AutofillHints.email],
                          validator: (value) {
                            final email = value?.trim() ?? '';
                            if (!email.contains('@')) return 'Enter a valid email';
                            return null;
                          },
                          onFieldSubmitted: (_) => _onContinue(),
                        ),
                        const SizedBox(height: 16),
                        _TermsCheckbox(
                          value: _agreed,
                          onChanged: (v) => setState(() => _agreed = v ?? false),
                        ),
                        if (_error != null) ...[
                          const SizedBox(height: 12),
                          Text(
                            _error!,
                            style: TextStyle(
                              color: Theme.of(context).colorScheme.error,
                              fontSize: 13,
                            ),
                          ),
                        ],
                        const SizedBox(height: 20),
                        AuthPrimaryButton(
                          label: 'Continue',
                          loading: _submitting,
                          onPressed: _onContinue,
                        ),
                        if (event.otpEnabled) ...[
                          const SizedBox(height: 14),
                          Center(
                            child: AuthSecondaryButton(
                              label: 'Login with OTP',
                              onPressed: _submitting ? null : _onOtpLogin,
                            ),
                          ),
                        ],
                        const SizedBox(height: 28),
                        const _OrDivider(),
                        const SizedBox(height: 20),
                        _SocialRow(
                          channels: showSocial
                              ? event.socialChannels
                              : const {
                                  'facebook': true,
                                  'google': true,
                                  'linkedin': true,
                                },
                          onTap: _socialSoon,
                        ),
                      ] else if (_step == _LoginStep.password) ...[
                        Text(
                          event.eventName ?? 'Sign in',
                          style: const TextStyle(
                            color: AppColors.headline,
                            fontSize: 22,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          _emailController.text.trim(),
                          style: const TextStyle(color: AppColors.body, fontSize: 14),
                        ),
                        const SizedBox(height: 22),
                        AuthTextField(
                          controller: _passwordController,
                          label: 'Password',
                          hint: 'Enter Password',
                          obscureText: true,
                          textInputAction: TextInputAction.done,
                          autofillHints: const [AutofillHints.password],
                          onFieldSubmitted: (_) => _onPasswordLogin(),
                        ),
                        if (_error != null) ...[
                          const SizedBox(height: 12),
                          Text(
                            _error!,
                            style: TextStyle(
                              color: Theme.of(context).colorScheme.error,
                              fontSize: 13,
                            ),
                          ),
                        ],
                        const SizedBox(height: 20),
                        AuthPrimaryButton(
                          label: 'Sign in',
                          loading: _submitting,
                          onPressed: _onPasswordLogin,
                        ),
                        const SizedBox(height: 12),
                        TextButton(
                          onPressed: _backToEmail,
                          child: const Text('Use a different email'),
                        ),
                      ] else ...[
                        const Text(
                          'Enter verification code',
                          style: TextStyle(
                            color: AppColors.headline,
                            fontSize: 22,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        if (_otpInfo != null) ...[
                          const SizedBox(height: 8),
                          Text(
                            _otpInfo!,
                            style: const TextStyle(color: AppColors.body, fontSize: 14),
                          ),
                        ],
                        const SizedBox(height: 22),
                        AuthTextField(
                          controller: _otpController,
                          hint: '6-digit code',
                          keyboardType: TextInputType.number,
                          textInputAction: TextInputAction.done,
                          onFieldSubmitted: (_) => _onVerifyOtp(),
                        ),
                        if (_error != null) ...[
                          const SizedBox(height: 12),
                          Text(
                            _error!,
                            style: TextStyle(
                              color: Theme.of(context).colorScheme.error,
                              fontSize: 13,
                            ),
                          ),
                        ],
                        const SizedBox(height: 20),
                        AuthPrimaryButton(
                          label: 'Verify',
                          loading: _submitting,
                          onPressed: _onVerifyOtp,
                        ),
                        const SizedBox(height: 12),
                        TextButton(
                          onPressed: _backToEmail,
                          child: const Text('Use a different email'),
                        ),
                      ],
                      Align(
                        alignment: Alignment.centerLeft,
                        child: TextButton(
                          onPressed: _changeEvent,
                          style: TextButton.styleFrom(
                            foregroundColor: AppColors.body,
                          ),
                          child: const Text('Change event'),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
            const AuthFooterBrand(),
          ],
        ),
      ),
    );
  }
}

class _EventIdentity extends StatelessWidget {
  const _EventIdentity({required this.event});

  final EventState event;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (event.logoUrl != null && event.logoUrl!.isNotEmpty)
          ClipRRect(
            borderRadius: BorderRadius.circular(6),
            child: CachedNetworkImage(
              imageUrl: event.logoUrl!,
              height: 48,
              fit: BoxFit.contain,
              errorWidget: (context, url, error) => const SizedBox.shrink(),
            ),
          )
        else
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
            decoration: BoxDecoration(
              color: const Color(0xFF1A1A2E),
              borderRadius: BorderRadius.circular(6),
            ),
            child: Text(
              (event.eventName ?? 'EVENT').toUpperCase(),
              style: const TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.w700,
                fontSize: 13,
              ),
            ),
          ),
        const SizedBox(height: 14),
        Text(
          event.eventName ?? 'Welcome',
          style: const TextStyle(
            color: AppColors.headline,
            fontSize: 22,
            fontWeight: FontWeight.w700,
          ),
        ),
        const SizedBox(height: 4),
        const Text(
          'Sign in to your account.',
          style: TextStyle(color: AppColors.body, fontSize: 14),
        ),
      ],
    );
  }
}

class _TermsCheckbox extends StatelessWidget {
  const _TermsCheckbox({required this.value, required this.onChanged});

  final bool value;
  final ValueChanged<bool?> onChanged;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 24,
          height: 24,
          child: Checkbox(
            value: value,
            onChanged: onChanged,
            activeColor: AppColors.brandPurple,
            materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
            visualDensity: VisualDensity.compact,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(4)),
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: Wrap(
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              const Text(
                'I agree to the ',
                style: TextStyle(color: AppColors.label, fontSize: 13, height: 1.4),
              ),
              GestureDetector(
                onTap: () {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Terms and Conditions')),
                  );
                },
                child: const Text(
                  'Terms and Conditions',
                  style: TextStyle(
                    color: AppColors.brandPurple,
                    fontWeight: FontWeight.w600,
                    fontSize: 13,
                    height: 1.4,
                  ),
                ),
              ),
              const Text(
                ' and ',
                style: TextStyle(color: AppColors.label, fontSize: 13, height: 1.4),
              ),
              GestureDetector(
                onTap: () {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Privacy Policy')),
                  );
                },
                child: const Text(
                  'Privacy Policy',
                  style: TextStyle(
                    color: AppColors.brandPurple,
                    fontWeight: FontWeight.w600,
                    fontSize: 13,
                    height: 1.4,
                  ),
                ),
              ),
              const Text(
                '.',
                style: TextStyle(color: AppColors.label, fontSize: 13, height: 1.4),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _OrDivider extends StatelessWidget {
  const _OrDivider();

  @override
  Widget build(BuildContext context) {
    return const Row(
      children: [
        Expanded(child: Divider(color: AppColors.divider)),
        Padding(
          padding: EdgeInsets.symmetric(horizontal: 12),
          child: Text(
            'Or continue with',
            style: TextStyle(color: AppColors.body, fontSize: 13),
          ),
        ),
        Expanded(child: Divider(color: AppColors.divider)),
      ],
    );
  }
}

class _SocialRow extends StatelessWidget {
  const _SocialRow({required this.channels, required this.onTap});

  final Map<String, bool> channels;
  final ValueChanged<String> onTap;

  @override
  Widget build(BuildContext context) {
    final items = <Widget>[];
    if (channels['facebook'] != false) {
      items.add(_SocialButton(
        onTap: () => onTap('Facebook'),
        child: const Icon(Icons.facebook, color: Color(0xFF1877F2), size: 28),
      ));
    }
    if (channels['google'] != false) {
      items.add(_SocialButton(
        onTap: () => onTap('Google'),
        child: const Text(
          'G',
          style: TextStyle(
            color: Color(0xFFEA4335),
            fontSize: 22,
            fontWeight: FontWeight.w700,
          ),
        ),
      ));
    }
    if (channels['linkedin'] != false) {
      items.add(_SocialButton(
        onTap: () => onTap('LinkedIn'),
        child: const Icon(Icons.business_center, color: Color(0xFF0A66C2), size: 24),
      ));
    }

    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        for (var i = 0; i < items.length; i++) ...[
          if (i > 0) const SizedBox(width: 18),
          items[i],
        ],
      ],
    );
  }
}

class _SocialButton extends StatelessWidget {
  const _SocialButton({required this.onTap, required this.child});

  final VoidCallback onTap;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      elevation: 2,
      shadowColor: Colors.black12,
      shape: const CircleBorder(),
      child: InkWell(
        customBorder: const CircleBorder(),
        onTap: onTap,
        child: SizedBox(
          width: 52,
          height: 52,
          child: Center(child: child),
        ),
      ),
    );
  }
}
