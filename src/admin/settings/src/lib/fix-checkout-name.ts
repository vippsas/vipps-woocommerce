const fixCheckoutName = (str: string, paymentMethod: string) =>
  str.replace(
    "Vipps MobilePay Checkout",
    paymentMethod === "Vipps" ? "Vipps Checkout" : "MobilePay Checkout"
  );

export default fixCheckoutName;