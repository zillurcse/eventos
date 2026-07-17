/** The viewer's own IANA timezone (e.g. "Asia/Dhaka"), for showing session
 *  and other event times in local time rather than the event's timezone. */
export function deviceTimezone(): string {
  try {
    return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'
  } catch {
    return 'UTC'
  }
}
