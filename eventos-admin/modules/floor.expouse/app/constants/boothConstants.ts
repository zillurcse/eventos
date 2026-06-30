export const BOOTH_STATUS_COLORS = {
  AVAILABLE: "#E7F9ED", // White
  BOOKED: "#FEE2E2", // Light Red
  ON_HOLD: "#DBEAFE", // Light Blue/Gray
} as const;

export const BOOTH_STATUS_LABELS = {
  AVAILABLE: "Available",
  BOOKED: "Booked",
  ON_HOLD: "On Hold",
} as const;

export const BOOTH_DISPLAY_OPTIONS = {
  BOOTH_NAME: "booth_name",
  COMPANY_LOGO: "companyLogo",
} as const;
export type BoothStatus = keyof typeof BOOTH_STATUS_LABELS;
export type BoothDisplayOption = "boothNumber" | "booth_name";
// export type BoothDisplayOption = keyof typeof BOOTH_DISPLAY_OPTIONS;
