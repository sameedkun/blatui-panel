import { registerBlatUI } from './blatui-core.js';
import { registerCharts } from './blatui-charts.js';

document.addEventListener('alpine:init', () => {
    registerBlatUI(window.Alpine, { darkMode: 'system' });
    registerCharts(window.Alpine);

    window.Alpine.data('localTime', () => ({
        formatted: '',
        init() {
            this.updateTime();
            const formatStr = this.$el.dataset.format || 'default';
            const showDiff = this.$el.dataset.diff === 'true';
            if (formatStr === 'smart' || showDiff) {
                // Update relative times periodically
                setInterval(() => this.updateTime(), 60000);
            }
        },
        updateTime() {
            const datetime = this.$el.getAttribute('datetime');
            if (!datetime || datetime === '—') {
                this.formatted = '—';
                return;
            }

            const date = new Date(datetime);
            if (isNaN(date.getTime())) {
                this.formatted = datetime;
                return;
            }

            const formatStr = this.$el.dataset.format || 'default';
            const showDiff = this.$el.dataset.diff === 'true';

            // Convert to local text
            let localText = this.formatDate(date, formatStr);

            if (showDiff) {
                const diffText = this.timeAgo(date);
                this.formatted = `${localText} (${diffText})`;
            } else {
                this.formatted = localText;
            }

            // Set native browser tooltip
            const fullFormatOptions = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            };
            try {
                this.$el.setAttribute('title', new Intl.DateTimeFormat(undefined, fullFormatOptions).format(date));
            } catch (e) {}
        },
        formatDate(date, formatStr) {
            const pad = (n) => n.toString().padStart(2, '0');

            if (formatStr === 'Y-m-d H:i:s') {
                return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
            }

            if (formatStr === 'MMM D, YYYY') {
                const showYear = date.getFullYear() !== new Date().getFullYear();
                return new Intl.DateTimeFormat(undefined, {
                    year: showYear ? 'numeric' : undefined,
                    month: 'short',
                    day: 'numeric'
                }).format(date);
            }

            if (formatStr === 'smart') {
                const now = new Date();
                const isToday = date.getDate() === now.getDate() &&
                                date.getMonth() === now.getMonth() &&
                                date.getFullYear() === now.getFullYear();

                const yesterday = new Date(now);
                yesterday.setDate(now.getDate() - 1);
                const isYesterday = date.getDate() === yesterday.getDate() &&
                                    date.getMonth() === yesterday.getMonth() &&
                                    date.getFullYear() === yesterday.getFullYear();

                if (isToday) {
                    const timeStr = new Intl.DateTimeFormat(undefined, {
                        hour: 'numeric',
                        minute: '2-digit',
                        hour12: true
                    }).format(date);
                    return `Today ${timeStr}`;
                }

                if (isYesterday) {
                    return 'Yesterday';
                }

                return this.timeAgo(date);
            }

            // Default: 'MMM D, YYYY h:mm A'
            const now = new Date();
            const isToday = date.getDate() === now.getDate() &&
                            date.getMonth() === now.getMonth() &&
                            date.getFullYear() === now.getFullYear();

            const showYear = date.getFullYear() !== now.getFullYear();
            const options = {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            };
            if (!isToday) {
                options.month = 'short';
                options.day = 'numeric';
                if (showYear) {
                    options.year = 'numeric';
                }
            }
            return new Intl.DateTimeFormat(undefined, options).format(date);
        },
        timeAgo(date) {
            const now = new Date();
            const seconds = Math.floor((now - date) / 1000);

            if (seconds < 5) return 'just now';

            const intervals = {
                year: 31536000,
                month: 2592000,
                week: 604800,
                day: 86400,
                hour: 3600,
                minute: 60,
                second: 1
            };

            for (const [unit, value] of Object.entries(intervals)) {
                const count = Math.floor(seconds / value);
                if (count >= 1) {
                    return `${count} ${unit}${count > 1 ? 's' : ''} ago`;
                }
            }

            return 'just now';
        }
    }));
});
