(function (window) {
    'use strict';

    function value(data, key, fallback) {
        const current = data && data[key];
        return current === undefined || current === null || current === '' ? fallback : current;
    }

    function generateQueryNumber() {
        return `UV-${Math.floor(100 + Math.random() * 99900)}`;
    }

    function ordinal(value) {
        const number = Number(value);
        if (!Number.isFinite(number)) return String(value || '-');
        const suffix = number % 100 >= 11 && number % 100 <= 13 ? 'th' : ({ 1: 'st', 2: 'nd', 3: 'rd' }[number % 10] || 'th');
        return `${number}${suffix}`;
    }

    function formatDate(input) {
        if (!input) return '-';
        const parts = String(input).split('-').map(Number);
        if (parts.length !== 3 || parts.some((part) => !Number.isFinite(part))) return String(input);
        const date = new Date(parts[0], parts[1] - 1, parts[2]);
        return `${ordinal(date.getDate())} ${date.toLocaleString('en-US', { month: 'long' })} ${date.getFullYear()}`;
    }

    function formatPrice(input) {
        const number = Number(input || 0);
        return Number.isFinite(number) ? String(Math.round(number)) : '0';
    }

    function formatMealPlans(input, prices) {
        const availablePrices = prices && typeof prices === 'object' ? prices : {};
        const labels = { EP: 'EP', CP: 'CP', MAP: 'MAP', AP: 'AP', AI: 'AI' };
        const selectedPlan = decodeHtml(value(input, 'mealPlan', ''));
        const plans = Object.entries(availablePrices)
            .filter(([, price]) => Number(price) > 0)
            .map(([code, price]) => `${labels[code] || code} - ${formatPrice(price)}/- per room per night`);
        if (plans.length) return plans.join(', ');
        const fallbackPlanCodes = ['EP', 'CP', 'MAP', 'AP', 'AI'];
        const selectedCode = selectedPlan.split(/\s|\(/, 1)[0].toUpperCase();
        return fallbackPlanCodes.map((plan) => plan === selectedCode && selectedPlan ? selectedPlan : plan).join(', ');
    }

    function decodeHtml(value) {
        const text = String(value ?? '')
            .replace(/<br\s*\/?>/gi, '\n')
            .replace(/<\/p>\s*<p[^>]*>/gi, '\n\n')
            .replace(/<\/div>\s*<div[^>]*>/gi, '\n')
            .replace(/<[^>]*>/g, '');
        if (typeof document === 'undefined') {
            return text.replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&#039;|&#39;/g, "'");
        }
        const decoder = document.createElement('textarea');
        decoder.innerHTML = text;
        return decoder.value;
    }

    function firstRoom(data) {
        const hotels = Array.isArray(data?.matchedHotels) ? data.matchedHotels : [];
        const hotel = hotels[0] || {};
        const room = Array.isArray(hotel.rooms) ? (hotel.rooms[0] || {}) : hotel;
        const prices = room.prices || hotel.prices || {};
        return { hotel, room, prices };
    }

    function normalizeQueryNumber(input) {
        const storedText = String(input?.queryText || input?.query_text || '');
        const embeddedNumber = storedText.match(/\bUV-\d{3,5}\b/i)?.[0] || '';
        const queryNumber = String(input?.queryNumber || embeddedNumber).trim().toUpperCase();
        return /^UV-\d{3,5}$/.test(queryNumber) ? queryNumber : generateQueryNumber();
    }

    function getOption(data, optionNumber) {
        const input = data || {};
        const selected = firstRoom(input);
        const hotel = selected.hotel;
        const room = selected.room;
        const prices = selected.prices;
        const hotelName = decodeHtml(value(input, 'hotelName', value(hotel, 'name', '-')));
        const location = decodeHtml(value(input, 'hotelLocation', value(hotel, 'location', value(hotel, 'city', '-'))));
        const roomCategory = decodeHtml(value(input, 'roomCategory', value(room, 'room_name', value(room, 'name', '-'))));
        const mealPlan = formatMealPlans(input, prices);
        const adults = Number(value(input, 'adults', 1));
        const children = Number(value(input, 'children', 0));
        const rooms = Number(value(input, 'rooms', 1));
        const occupancy = value(input, 'occupancy', adults === 2 ? 'Double' : adults === 1 ? 'Single' : `${adults} Persons`);
        const roomPrice = value(input, 'roomPrice', value(room, 'selected_price', prices.EP || prices.MAP || prices.MAPAI || input.budget || 0));
        const extraBedAllowed = Boolean(value(input, 'extraBedAllowed', value(room, 'extra_bed_allowed', false)));
        const extraBedPrice = value(input, 'extraBedPrice', value(room, 'extra_bed_price', 0));
        const maxExtraBeds = value(input, 'maxExtraBeds', value(room, 'max_extra_beds', 0));
        return {
            optionNumber,
            hotelName,
            location,
            checkIn: formatDate(input.checkIn),
            checkOut: formatDate(input.checkOut),
            people: adults + (children > 0 ? children : 0),
            rooms,
            occupancy: decodeHtml(occupancy),
            roomCategory,
            mealPlan,
            roomPrice: formatPrice(roomPrice),
            extraBedAllowed,
            extraBedPrice: formatPrice(extraBedPrice),
            maxExtraBeds: Number(maxExtraBeds) || 0,
        };
    }

    function formatMany(items) {
        const quotations = Array.isArray(items) ? items.filter(Boolean) : [];
        if (!quotations.length) return '';
        const queryNumber = normalizeQueryNumber(quotations[0]);
        const first = quotations[0] || {};
        const cancellation = decodeHtml(value(first, 'cancellationPolicy', 'Free cancellation 1 day prior to arrival hotel local time (12:00 Hours). Thereafter, any cancellation/no show leads to 100% retention charges.'));
        const quotationContact = window.AirwaysQuotationContact || {};
        const contactPerson = decodeHtml(value(first, 'contactPerson', value(first, 'createdByName', quotationContact.name || 'Manish Bhatia')));
        const contactPhone = decodeHtml(value(first, 'contactPhone', value(first, 'createdByPhone', quotationContact.name ? quotationContact.phone : '919999831144')));
        const contactEmail = decodeHtml(value(first, 'contactEmail', value(first, 'createdByEmail', quotationContact.name ? quotationContact.email : 'manish@airwaystravels.com')));
        const seen = new Set();
        const options = [];
        quotations.forEach((item) => {
            const option = getOption(item, options.length + 1);
            const optionKey = `${option.hotelName}|${option.roomCategory}|${option.mealPlan}|${option.roomPrice}`;
            if (seen.has(optionKey)) return;
            seen.add(optionKey);
            option.optionNumber = options.length + 1;
            options.push(option);
        });

        const optionLines = options.flatMap((option, index) => [
            `*Option ${option.optionNumber}: ${option.hotelName}*`,
            `*Location*: ${option.location}`,
            `*Check-In*: ${option.checkIn} | *Check-Out*: ${option.checkOut}`,
            `*No. of Person*: ${option.people} | *No. of Rooms*: ${option.rooms} Room | *Occupancy*: ${option.occupancy}`,
            `*Room Category*: ${option.roomCategory}`,
            `*Meal Plan*: ${option.mealPlan}`,
            `*Room Price*: ${option.roomPrice}/- per room per night`,
            ...(option.extraBedAllowed ? [`*Extra Bed*: ${option.extraBedPrice}/- per extra bed${option.maxExtraBeds > 0 ? ` | Max ${option.maxExtraBeds}` : ''}`] : []),
            ...(index < options.length - 1 ? ['', '---', ''] : [])
        ]);
        return [
            '*Airways Travels | Quotation*', '',
            `Greetings from Airways Travels. Further with reference to query number *${queryNumber}*, find below the quotation as desired:`, '',
            '---', ...optionLines, '', '---',
            '*Above rates are inclusive of taxes.*', '',
            `*Cancellation Policy*: ${cancellation}`, '',
            '*Rooms and Rates are Subject to Availability, please confirm the same at the earliest to proceed with the booking.*', '',
            'Thank you for contacting Airways Travels.', 'In case of any support please contact us:',
            contactPerson, `☎️ Mobile : ${contactPhone}`, `✉️ Email : ${contactEmail}`, '', '_Powered by Airways Travels_'
        ].join('\n');
    }

    function format(data) { return formatMany([data]); }

    window.AirwaysQuotation = { format, formatMany, generateQueryNumber, plainText: decodeHtml };
})(window);
