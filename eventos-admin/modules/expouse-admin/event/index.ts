import { defineFeatureModule } from '../kit/featureModule'

// Event: the per-event management console (content hub, engagement,
// communication, onsite, services, showcase, analytics, users, mail…) + event
// layout. The mail email-builder lives under runtime/{components/mail,
// composables/useEmailBlocks, pages/.../mail}.
export default defineFeatureModule(import.meta.url, { name: 'expouse-event' })
