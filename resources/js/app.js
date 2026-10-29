import './bootstrap';

import Alpine from 'alpinejs';

/* ── Money ─────────────────────────────────────────────────────────────
 * The browser's half of App\Support\DisplayCurrency (and the app's
 * src/lib/money.ts): charts draw their own labels and tooltips, so they
 * format here the same way. Stored amounts are hundredths of a rupee; the
 * layout puts the user's currency in window.APP_CURRENCY. */
const currency = () => window.APP_CURRENCY ?? { code: 'INR', symbol: '₹', decimals: 2, rate: 1 };

const toDisplayMinor = (stored) => {
    const { rate } = currency();
    return rate ? Math.round(stored / rate) : stored;
};

function groupInteger(digits) {
    if (currency().code === 'INR' && digits.length > 3) {
        return `${digits.slice(0, -3).replace(/\B(?=(\d{2})+(?!\d))/g, ',')},${digits.slice(-3)}`;
    }
    return digits.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function formatDisplay(display) {
    const { symbol, decimals } = currency();
    const units = Math.round(Math.abs(display) / 10 ** (2 - decimals));
    const factor = 10 ** decimals;
    let text = symbol + groupInteger(String(Math.floor(units / factor)));
    if (decimals > 0) text += `.${String(units % factor).padStart(decimals, '0')}`;
    return display < 0 ? `-${text}` : text;
}

function compactDisplay(display) {
    const { symbol, code } = currency();
    const major = Math.abs(display) / 100;
    const sign = display < 0 ? '-' : '';
    const trim = (value) => {
        const fixed = value.toFixed(1);
        return fixed.endsWith('.0') ? fixed.slice(0, -2) : fixed;
    };
    const steps = code === 'INR' ? [[1e7, 'Cr'], [1e5, 'L'], [1e3, 'k']] : [[1e9, 'B'], [1e6, 'M'], [1e3, 'k']];
    for (const [size, suffix] of steps) {
        if (major >= size) return `${sign}${symbol}${trim(major / size)}${suffix}`;
    }
    return `${sign}${symbol}${trim(major)}`;
}

window.formatMoney = (stored) => formatDisplay(toDisplayMinor(stored));

/* ── Charts (Chart.js, loaded by the layout) ───────────────────────────── */
const isDark = () => document.documentElement.classList.contains('dark');
const INCOME = '#10b981';
const EXPENSE = '#ef4444';
// The design system's tokens (tailwind.config.js): navy, or cyan in dark mode.
const accent = () => (isDark() ? '#00a7cb' : '#002e62');
const inkColor = () => (isDark() ? '#9ca3af' : '#4b5563');
const gridColor = () => (isDark() ? '#243047' : '#f2f4f7');
// Only My account loads Chart.js; the admin pages draw their charts in HTML.
if (window.Chart) window.Chart.defaults.font.family = 'Inter, system-ui, sans-serif';

/** The app's axis: three even steps from zero up to a round top above the highest bar. */
function axisTop(maxDisplay) {
    const raw = maxDisplay / 100 / 3;
    let step = 1;
    if (raw > 0) {
        const magnitude = 10 ** Math.floor(Math.log10(raw));
        step = [1, 2, 2.5, 5, 10].map((m) => m * magnitude).find((s) => s >= raw);
    }
    return Math.max(step, 1) * 100 * 3;
}

function moneyScale(values) {
    const top = axisTop(Math.max(0, ...values));
    return {
        min: 0,
        max: top,
        border: { display: false },
        grid: { color: gridColor() },
        ticks: { stepSize: top / 3, color: inkColor(), callback: (v) => compactDisplay(v) },
    };
}

/** Income and expense columns side by side. Zero draws nothing; anything else at least 3px. */
window.barChart = (canvas, { labels, income, expense, highlight = -1 }) => {
    const inc = income.map(toDisplayMinor);
    const exp = expense.map(toDisplayMinor);
    const bars = (label, data, color) => ({
        label,
        data: data.map((v) => (v === 0 ? null : v)),
        backgroundColor: color,
        borderRadius: 4,
        minBarLength: 3,
        maxBarThickness: 16,
        categoryPercentage: 0.6,
    });

    return new Chart(canvas, {
        type: 'bar',
        data: { labels, datasets: [bars('Income', inc, INCOME), bars('Expense', exp, EXPENSE)] },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, color: inkColor() } },
                tooltip: { callbacks: { label: (ctx) => ` ${ctx.dataset.label}: ${formatDisplay(ctx.raw ?? 0)}` } },
            },
            scales: {
                y: moneyScale([...inc, ...exp]),
                x: {
                    grid: { display: false },
                    ticks: {
                        color: (ctx) => (ctx.index === highlight ? accent() : inkColor()),
                        font: (ctx) => ({ weight: ctx.index === highlight ? '700' : '400' }),
                    },
                },
            },
        },
    });
};

/**
 * Two lines over time with a read-out above: the latest period at first,
 * any other one once it is clicked. `readout` holds elements marked
 * data-caption, data-income and data-expense.
 */
window.trendChart = (canvas, { labels, captions, income, expense }, readout) => {
    const inc = income.map(toDisplayMinor);
    const exp = expense.map(toDisplayMinor);
    const show = (i) => {
        readout.querySelector('[data-caption]').textContent = captions[i];
        readout.querySelector('[data-income]').textContent = formatDisplay(inc[i]);
        readout.querySelector('[data-expense]').textContent = formatDisplay(exp[i]);
    };
    show(labels.length - 1);

    const line = (label, data, color) => ({
        label, data, borderColor: color, backgroundColor: color, tension: 0.3, pointRadius: 3, pointHoverRadius: 5, borderWidth: 2,
    });

    return new Chart(canvas, {
        type: 'line',
        data: { labels, datasets: [line('Income', inc, INCOME), line('Expense', exp, EXPENSE)] },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            onClick: (_event, elements) => elements.length && show(elements[0].index),
            plugins: { legend: { display: false }, tooltip: { enabled: false } },
            scales: {
                y: moneyScale([...inc, ...exp]),
                x: {
                    grid: { display: false },
                    ticks: {
                        color: inkColor(),
                        autoSkip: false,
                        // Every other label once there are more than six.
                        callback: (_v, i) => (labels.length > 6 && (labels.length - 1 - i) % 2 ? '' : labels[i]),
                    },
                },
            },
        },
    });
};

/** A ring of category shares with a small gap between them; an empty month is just the track. */
window.donutChart = (canvas, slices) => {
    const empty = slices.length === 0;
    return new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: empty ? [''] : slices.map((s) => s.name),
            datasets: [{
                data: empty ? [1] : slices.map((s) => s.total),
                backgroundColor: empty ? [isDark() ? '#334155' : '#e2e8f0'] : slices.map((s) => s.color),
                borderWidth: 0,
                spacing: empty || slices.length === 1 ? 0 : 2,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            cutout: '72%',
            plugins: {
                legend: { display: false },
                tooltip: empty ? { enabled: false } : { callbacks: { label: (ctx) => ` ${window.formatMoney(ctx.raw)}` } },
            },
        },
    });
};

/* ── Confirm before a destructive submit ──────────────────────────────
 * <form data-confirm-title="Delete expense?" data-confirm-message="…"
 * data-confirm-button="Delete"> asks first, in the layout's dialog. */
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!form.dataset.confirmTitle || form.dataset.confirmed) return;
    event.preventDefault();
    window.dispatchEvent(new CustomEvent('confirm-submit', { detail: form }));
}, true);

Alpine.data('confirmDialog', () => ({
    open: false,
    title: '',
    message: '',
    button: '',
    form: null,
    ask(form) {
        this.form = form;
        this.title = form.dataset.confirmTitle;
        this.message = form.dataset.confirmMessage ?? '';
        this.button = form.dataset.confirmButton ?? 'Confirm';
        this.open = true;
    },
    confirm() {
        this.open = false;
        this.form.dataset.confirmed = '1';
        this.form.requestSubmit();
    },
}));

/* ── Profile photo ─────────────────────────────────────────────────────
 * Like the app: a square 256px JPEG sent as a data URI. The picture is
 * cropped to its centre square. */
window.photoToDataUri = (file) => new Promise((resolve, reject) => {
    const image = new Image();
    image.onload = () => {
        const size = Math.min(image.width, image.height);
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = 256;
        canvas.getContext('2d').drawImage(image, (image.width - size) / 2, (image.height - size) / 2, size, size, 0, 0, 256, 256);
        URL.revokeObjectURL(image.src);
        resolve(canvas.toDataURL('image/jpeg', 0.7));
    };
    image.onerror = reject;
    image.src = URL.createObjectURL(file);
});

window.Alpine = Alpine;

Alpine.start();
