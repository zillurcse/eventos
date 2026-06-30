import { defineFeatureModule } from '../kit/featureModule'

// Platform: super-admin control plane (dashboard, organizations, plans, staff,
// organizers).
export default defineFeatureModule(import.meta.url, { name: 'expouse-platform' })
