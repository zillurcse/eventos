// utils/numberFormat.ts
export const formatNumberWithDecimals = (value: number | string): string => {
  if (value === null || value === undefined || value === "") return "0.00";

  const num = typeof value === "string" ? parseFloat(value) : value;

  if (isNaN(num)) return "0.00";

  // Check if it's a whole number
  if (Number.isInteger(num)) {
    return num.toFixed(0);
  }

  // For decimal numbers, format to 2 decimal places
  return num.toFixed(2);
};

export const parseFormattedNumber = (value: string): number => {
  if (!value) return 0;
  return parseFloat(value) || 0;
};
