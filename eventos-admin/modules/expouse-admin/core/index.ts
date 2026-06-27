import { defineFeatureModule } from '../kit/featureModule'

// Core: shared UI kit, auth store, the global stylesheet, the default layout,
// the four route middleware (auth/platform/organizer/exhibitor) and login.
export default defineFeatureModule(import.meta.url, { name: 'expouse-core' })
