(function(){
  let liveTimeTimerStarted = false;

  function getFormatterLocale(){
    return (YRWP_FRONT && YRWP_FRONT.locale) || document.documentElement.lang || undefined;
  }

  function getZonedParts(date, timezone, locale) {
    const base = new Intl.DateTimeFormat(locale, {
      timeZone: timezone,
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      weekday: 'long',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hourCycle: 'h23',
    }).formatToParts(date);

    const monthLong = new Intl.DateTimeFormat(locale, {
      timeZone: timezone,
      month: 'long',
    }).format(date);

    const monthShort = new Intl.DateTimeFormat(locale, {
      timeZone: timezone,
      month: 'short',
    }).format(date);

    const weekdayLong = new Intl.DateTimeFormat(locale, {
      timeZone: timezone,
      weekday: 'long',
    }).format(date);

    const weekdayShort = new Intl.DateTimeFormat(locale, {
      timeZone: timezone,
      weekday: 'short',
    }).format(date);

    const twelveHour = new Intl.DateTimeFormat(locale, {
      timeZone: timezone,
      hour: 'numeric',
      minute: '2-digit',
      hourCycle: 'h12',
    }).formatToParts(date);

    const map = {};
    base.forEach((part) => {
      map[part.type] = part.value;
    });

    const twelveHourMap = {};
    twelveHour.forEach((part) => {
      twelveHourMap[part.type] = part.value;
    });

    return {
      year: map.year,
      month: map.month,
      day: map.day,
      hour24: map.hour,
      minute: map.minute,
      second: map.second,
      hour12: twelveHourMap.hour,
      dayPeriod: twelveHourMap.dayPeriod || '',
      monthLong,
      monthShort,
      weekdayLong,
      weekdayShort,
    };
  }

  function pad(value){
    return String(value).padStart(2, '0');
  }

  function formatLiveTime(date, format, timezone) {
    const locale = getFormatterLocale();
    const parts = getZonedParts(date, timezone, locale);
    let out = '';

    for (let i = 0; i < format.length; i += 1) {
      const char = format[i];
      if (char === '\\') {
        i += 1;
        out += format[i] || '';
        continue;
      }

      switch (char) {
        case 'Y':
          out += parts.year;
          break;
        case 'y':
          out += String(parts.year).slice(-2);
          break;
        case 'm':
          out += parts.month;
          break;
        case 'n':
          out += String(parseInt(parts.month, 10));
          break;
        case 'd':
          out += parts.day;
          break;
        case 'j':
          out += String(parseInt(parts.day, 10));
          break;
        case 'H':
          out += parts.hour24;
          break;
        case 'G':
          out += String(parseInt(parts.hour24, 10));
          break;
        case 'h':
          out += pad(parts.hour12);
          break;
        case 'g':
          out += String(parseInt(parts.hour12, 10));
          break;
        case 'i':
          out += parts.minute;
          break;
        case 's':
          out += parts.second;
          break;
        case 'A':
          out += String(parts.dayPeriod).toUpperCase();
          break;
        case 'a':
          out += String(parts.dayPeriod).toLowerCase();
          break;
        case 'F':
          out += parts.monthLong;
          break;
        case 'M':
          out += parts.monthShort;
          break;
        case 'l':
          out += parts.weekdayLong;
          break;
        case 'D':
          out += parts.weekdayShort;
          break;
        default:
          out += char;
          break;
      }
    }

    return out;
  }

  function updateLiveTimes(root){
    const scope = root || document;
    scope.querySelectorAll('.yrwp-live-time').forEach((el) => {
      const timezone = el.getAttribute('data-yrwp-timezone');
      const format = el.getAttribute('data-yrwp-format') || 'H:i';
      if (!timezone) return;

      try {
        el.textContent = formatLiveTime(new Date(), format, timezone);
      } catch (err) {
      }
    });
  }

  function startLiveTimeUpdates(){
    if (liveTimeTimerStarted) return;
    liveTimeTimerStarted = true;
    updateLiveTimes(document);
    window.setInterval(function(){
      updateLiveTimes(document);
    }, 60000);
  }

  function initWidget(el){
    const id = el.getAttribute('data-yrwp-id');
    const mode = el.getAttribute('data-yrwp-mode');
    if (mode !== 'visitor_geo') return;

    const body = el.querySelector('.yrwp-body');
    if (!navigator.geolocation) {
      body.textContent = YRWP_FRONT.strings.unsupported;
      return;
    }

    navigator.geolocation.getCurrentPosition(async (pos) => {
      const lat = pos.coords.latitude;
      const lon = pos.coords.longitude;

      const url = new URL(YRWP_FRONT.rest);
      url.searchParams.set('id', id);
      url.searchParams.set('lat', lat);
      url.searchParams.set('lon', lon);

      const res = await fetch(url.toString(), { headers: { 'X-WP-Nonce': YRWP_FRONT.nonce }});
      const data = await res.json();
      if (!data.ok) {
        body.textContent = YRWP_FRONT.strings.unavailable;
        return;
      }
      body.classList.remove('yrwp-loading');
      body.innerHTML = data.html;
      updateLiveTimes(body);
    }, () => {
      body.textContent = YRWP_FRONT.strings.denied;
    });
  }

  document.addEventListener('DOMContentLoaded', function(){
    startLiveTimeUpdates();
    document.querySelectorAll('.yrwp-widget').forEach(initWidget);
  });
})();
