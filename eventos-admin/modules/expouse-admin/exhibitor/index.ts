import { defineFeatureModule } from '../kit/featureModule'

// Exhibitor: exhibitor manager (organizer side) + exhibitor-admin self-service
// area, plus the shared exhibitor domain helpers.
export default defineFeatureModule(import.meta.url, { name: 'expouse-exhibitor' })
