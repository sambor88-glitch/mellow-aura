import { formatMoney } from './money';

/*
 * The quote under the batch form, counted the same way as Firing\Support\FiringQuote on the server: a price per
 * kilogram or per piece times the amount, rounded to the grosz, or a row's flat price. Prices come in grosze.
 */
const hundredths = (quantity) => {
    const match = String(quantity).trim().match(/^(\d{1,3})(?:[.,](\d{1,2}))?$/);

    return match ? Number(match[1]) * 100 + Number((match[2] ?? '').padEnd(2, '0')) : null;
};

export default (services, service = '', quantity = '') => ({
    service,
    quantity,

    get chosen() {
        return services.find((row) => row.code === this.service) ?? null;
    },

    get unit() {
        return this.chosen?.unit ?? null;
    },

    get byAmount() {
        return this.unit === 'kg' || this.unit === 'piece';
    },

    get quoteLabel() {
        const row = this.chosen;

        if (!row || row.unit === 'other') {
            return 'po obejrzeniu prac';
        }

        if (row.unit === null) {
            return formatMoney(row.price);
        }

        const amount = hundredths(this.quantity);
        const valid = amount !== null && amount > 0 && (row.unit === 'kg' || amount % 100 === 0);

        if (!valid) {
            return row.unit === 'kg' ? 'wpisz wagę' : 'wpisz liczbę prac';
        }

        return formatMoney(Math.floor((row.price * amount + 50) / 100));
    },
});
