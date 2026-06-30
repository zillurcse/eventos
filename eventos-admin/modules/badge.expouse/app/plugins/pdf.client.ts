// plugins/pdf.client.ts
import html2canvas from "html2canvas";
import jsPDF from "jspdf";

export default defineNuxtPlugin((nuxtApp) => {
  console.log("PDF plugin loaded"); // Debug log to confirm loading
  return {
    provide: {
      html2canvas,
      jsPDF,
    },
  };
});
