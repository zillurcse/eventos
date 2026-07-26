/** Shared shape for Mail › Emails journeys (stored in event_settings.mail_emails). */
export interface MailEmail {
  id: string
  name: string
  description: string
  mode: 'automated' | 'manual'
  subject: string
  event_state: string
  sent_to: string
  type: string
  active: boolean
  status: 'active' | 'draft'
  date_label: string
  from_name: string
  from_email: string
  cc: string
  bcc: string
  template_id: string | null
}
