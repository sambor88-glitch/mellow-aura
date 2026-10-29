/*
 * The same as Money::format in PHP: grosze and cents only when there are some (69 zł, 79,90 zł), euro the English
 * way on an English page (€39) and the Polish way elsewhere (39 €). Amounts come in grosze or cents.
 */
export const formatMoney = (minor, currency = 'PLN', lang = 'pl') => {
    const english = minor % 100 === 0 ? String(Math.trunc(minor / 100)) : (minor / 100).toFixed(2);
    const polish = english.replace('.', ',');

    if (currency === 'EUR') {
        return lang === 'en' ? '€' + english : polish + ' €';
    }

    return currency === 'PLN' ? polish + ' zł' : polish + ' ' + currency;
};
