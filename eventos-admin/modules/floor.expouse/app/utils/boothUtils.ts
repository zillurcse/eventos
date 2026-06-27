// utils/boothUtils.ts
export const generateUniqueBoothNumber = (
  baseNumber: string,
  existingBooths: any[]
): string => {
  let uniqueNumber = baseNumber;
  let counter = 1;

  while (
    existingBooths.some(
      (booth) => booth.boothNumber?.toLowerCase() === uniqueNumber.toLowerCase()
    )
  ) {
    uniqueNumber = `${baseNumber}-${counter}`;
    counter++;
  }

  return uniqueNumber;
};
