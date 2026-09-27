// Client-side checks that mirror the API's, so a form can point at the field before the round trip.
// The API still validates everything; keep both sides in step when a rule changes.

// Symfony's Email constraint in html5 mode, plus a domain that ends in a dot and letters.
const EMAIL = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+$/;
const EMAIL_TOP_LEVEL = /\.[a-z]{2,}$/i;

export function isValidEmail(value: string | null | undefined): boolean {
    const email = String(value ?? '').trim();
    return EMAIL.test(email) && EMAIL_TOP_LEVEL.test(email);
}

// A phone number: an optional "+", digits grouped with spaces, dashes, dots or parentheses, 7–15 digits.
const PHONE = /^\+?[0-9][0-9 ().-]*[0-9)]$/;

export function isValidPhone(value: string | null | undefined): boolean {
    const phone = String(value ?? '').trim();
    const digits = phone.replace(/\D/g, '').length;
    return PHONE.test(phone) && digits >= 7 && digits <= 15;
}
