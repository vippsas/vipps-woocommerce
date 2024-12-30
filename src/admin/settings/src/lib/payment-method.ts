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
