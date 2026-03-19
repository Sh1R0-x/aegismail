/**
 * Format a date string or Date object to French format: 19/03/2026 - 09h00
 * Returns '—' for null/empty values.
 *
 * @param {string|Date|null} value - ISO 8601 string, Date object, or null
 * @param {object} options - Optional overrides
 * @param {boolean} options.dateOnly - Show only date (no time)
 * @param {boolean} options.timeOnly - Show only time
 * @returns {string}
 */
export function formatDateFR(value, { dateOnly = false, timeOnly = false } = {}) {
  if (!value) return '—';

  const date = value instanceof Date ? value : new Date(value);

  if (isNaN(date.getTime())) return '—';

  if (dateOnly) {
    return date.toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      timeZone: 'Europe/Paris',
    });
  }

  if (timeOnly) {
    const h = date.toLocaleString('fr-FR', { hour: '2-digit', minute: '2-digit', hour12: false, timeZone: 'Europe/Paris' });
    return h.replace(':', 'h');
  }

  const d = date.toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    timeZone: 'Europe/Paris',
  });

  const t = date.toLocaleString('fr-FR', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
    timeZone: 'Europe/Paris',
  });

  // Extract just the time part (toLocaleString for time includes the date)
  const timeParts = date.toLocaleTimeString('fr-FR', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
    timeZone: 'Europe/Paris',
  });

  return `${d} - ${timeParts.replace(':', 'h')}`;
}
