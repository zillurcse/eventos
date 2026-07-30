# EventOS Mobile

Flutter attendee app for the EventOS platform. Talks to the existing
`eventos-api` Laravel backend.

## Prerequisites

1. **Flutter SDK** (stable). If `flutter` is not on your PATH, this repo was
   bootstrapped with a user-local SDK at:

   ```
   %LOCALAPPDATA%\flutter
   ```

   Add it permanently (PowerShell, current user):

   ```powershell
   [Environment]::SetEnvironmentVariable(
     'Path',
     "$env:LOCALAPPDATA\flutter\bin;" + [Environment]::GetEnvironmentVariable('Path', 'User'),
     'User'
   )
   ```

   Restart the terminal, then run `flutter doctor`.

2. **Android Studio** (Android SDK + emulator) and/or **Xcode** (macOS, for iOS).

3. **EventOS API** running locally — see the repo root `README.md`
   (`docker compose up`, API at `http://localhost:8080`).

## Project layout

```
lib/
├── main.dart
├── app.dart
├── core/
│   ├── api/          # Dio client + errors
│   ├── config/       # API base URL
│   ├── router/       # go_router
│   ├── storage/      # Secure token + subdomain
│   └── theme/
└── features/
    ├── auth/
    ├── bootstrap/    # Event picker, splash, onboarding
    └── reception/    # Post-login home (matches mobile design)
```

## Run locally

```bash
cd eventos-mobile
flutter pub get
flutter run
```

### API URL

Default for Android emulator: `http://10.0.2.2:8080` (maps to host `localhost`).

Override for a physical device or iOS simulator:

```bash
flutter run --dart-define=API_BASE_URL=http://192.168.1.10:8080
```

## Current flow

1. **Splash** — purple Expouse branded screen (~1.8s)
2. **Onboarding** — 3-page carousel from design assets (Skip / Previous / Next)
3. **Event picker** — enter event subdomain → `GET /public/site`
4. **Sign in** — email + terms → Continue
   - Existing user → password step
   - New user → Sign Up screen
   - OTP / social buttons when enabled for the event
5. **Sign up** — email, name, password → register + auto login
6. **Reception / Home** — greeting, banners, about, live/featured sessions,
   speakers, exhibitors/sponsors, leaderboard preview, ads + bottom CTA
   (`GET /public/reception`)

Auth token, subdomain, and onboarding completion are stored with
`flutter_secure_storage`. Every API request sends `Authorization: Bearer …`
and `X-Event-Subdomain`.

## Useful commands

```bash
flutter analyze
flutter test
flutter build apk --debug
```

## Next steps

- OTP login flow
- Dynamic bottom tabs from admin `manage_tabs`
- Push notifications (FCM)
- Agenda, delegates, chat screens
