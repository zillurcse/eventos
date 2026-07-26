/**
 * Badge designs can use any font from the organizer's editor picker. Those
 * families are NOT loaded globally (they wreck first paint) — inject them once
 * the first badge canvas mounts.
 */
const BADGE_FONTS_HREF =
  'https://fonts.googleapis.com/css2?family=Assistant:wght@400;600;700&family=Fira+Sans:wght@400;600;700&family=Inconsolata:wght@400;700&family=Lato:wght@400;700&family=Merriweather:wght@400;700&family=Montserrat:wght@400;600;700&family=Mukta:wght@400;600;700&family=Noto+Sans:wght@400;600;700&family=Nunito:wght@400;600;700&family=Open+Sans:wght@400;600;700&family=Oswald:wght@400;600;700&family=PT+Sans:wght@400;700&family=Playfair+Display:wght@400;700&family=Poppins:wght@400;600;700&family=Quicksand:wght@400;600;700&family=Raleway:wght@400;600;700&family=Roboto:wght@400;500;700&family=Roboto+Condensed:wght@400;700&family=Source+Sans+3:wght@400;600;700&family=Ubuntu:wght@400;500;700&display=swap'

const LINK_ID = 'eventos-badge-fonts'

export function ensureBadgeFonts(): void {
  if (!import.meta.client) return
  if (document.getElementById(LINK_ID)) return

  const link = document.createElement('link')
  link.id = LINK_ID
  link.rel = 'stylesheet'
  link.href = BADGE_FONTS_HREF
  document.head.appendChild(link)
}
