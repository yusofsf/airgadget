const TEHRAN_TIME_ZONE = 'Asia/Tehran';

const dateTimeFormatters = {
    medium: new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
        calendar: 'persian',
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: TEHRAN_TIME_ZONE,
    }),
    full: new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
        calendar: 'persian',
        dateStyle: 'full',
        timeStyle: 'short',
        timeZone: TEHRAN_TIME_ZONE,
    }),
};

export function formatPersianDateTime(
    value: string | number | Date,
    style: keyof typeof dateTimeFormatters = 'medium',
): string {
    const date = value instanceof Date ? value : new Date(value);

    return Number.isNaN(date.getTime()) ? '—' : dateTimeFormatters[style].format(date);
}
