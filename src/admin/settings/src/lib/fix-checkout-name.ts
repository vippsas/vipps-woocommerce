/**
 * Replaces the substring 'Vipps MobilePay Checkout' name with either 'Vipps Checkout' or 'MobilePay Checkout' depending on the given paymentmethod. LP 08.01.2025
 * @param str - The string to replace/fix the checkout name on
 * @param paymentMethod - The payment method to use
 * @returns 
 */
const fixCheckoutName = (str: string, paymentMethod: string) =>
  str.replace(
    /Vipps MobilePay Checkout/g,
    paymentMethod === "Vipps" ? "Vipps Checkout" : "MobilePay Checkout"
  );

export default fixCheckoutName;