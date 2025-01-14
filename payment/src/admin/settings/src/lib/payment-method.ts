export function detectPaymentMethodName(country: string): string {
  switch (country) {
    case 'NO':
      return 'Vipps';
    case 'SE':
      return 'Vipps';
    case 'FI':
      return 'MobilePay';
    case 'DK':
      return 'MobilePay';
  }
  return 'MobilePay';
}

export function getPaymentMethodSupportedCurrencies(paymentMethod: string): string[] {
  switch (paymentMethod) {
    case 'Vipps':
      return ['NOK', 'SEK'];
    case 'MobilePay':
      return ['DKK', 'EUR'];
    default:
      return [];
  }
}

export function isPaymentMethodCurrencySupported(paymentMethod: string, currency: string | null): boolean {
  if (!currency) return false;
  return getPaymentMethodSupportedCurrencies(paymentMethod).includes(currency);
}
