(function (window) {
    'use strict';

    function value(data, key, fallback) {
        const current = data && data[key];
        return current === undefined || current === null || current === '' ? fallback : current;
    }

    function ordinal(value) {
        const number = Number(value);
        if (!Number.isFinite(number)) return String(value || 'N/A');
        const suffix = number % 100 >= 11 && number % 100 <= 13 ? 'th' : ({ 1: 'st', 2: 'nd', 3: 'rd' }[number % 10] || 'th');
        return `${number}${suffix}`;
    }

    function formatDate(input) {
        if (!input) return 'N/A';
        const parts = String(input).split('-').map(Number);
        if (parts.length !== 3 || parts.some((part) => !Number.isFinite(part))) return String(input);
        const date = new Date(parts[0], parts[1] - 1, parts[2]);
        return `${ordinal(date.getDate())} ${date.toLocaleString('en-US', { month: 'long' })} ${date.getFullYear()}`;
    }

    function formatPrice(input) {
        const number = Number(input || 0);
        return Number.isFinite(number) ? String(Math.round(number)) : '0';
    }

    function randomQueryNumber() {
        return `UV-${String(Math.floor(1000 + Math.random() * 9000))}`;
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

    function mealPlans(data, room, prices) {
        const plans = Object.keys(prices || {});
        if (plans.length) return plans.join(', ');
        return decodeHtml(value(data, 'mealPlan', value(room, 'meal_plan', 'N/A')));
    }

    function quotationParts(data) {
        const input = data || {};
        const selected = firstRoom(input);
        const hotel = selected.hotel;
        const room = selected.room;
        const prices = selected.prices;
        const hotelName = decodeHtml(value(input, 'hotelName', value(hotel, 'name', 'N/A')));
        const location = decodeHtml(value(input, 'hotelLocation', value(hotel, 'location', value(hotel, 'city', 'N/A'))));
        const roomCategory = decodeHtml(value(input, 'roomCategory', value(room, 'room_name', value(room, 'name', 'N/A'))));
        const mealPlan = decodeHtml(value(input, 'mealPlan', value(room, 'meal_plan', Object.keys(prices)[0] || 'MAPAI')));
        const adults = Number(value(input, 'adults', 1));
        const children = Number(value(input, 'children', 0));
        const rooms = Number(value(input, 'rooms', 1));
        const occupancy = value(input, 'occupancy', adults === 2 ? 'Double' : adults === 1 ? 'Single' : `${adults} Persons`);
        const roomPrice = value(input, 'roomPrice', value(room, 'selected_price', prices.EP || prices.MAP || prices.MAPAI || input.budget || 0));
        const queryId = Number(input.id || 0);
        const queryNumber = value(input, 'queryNumber', queryId > 0 ? `UV-${String(queryId).padStart(4, '0')}` : randomQueryNumber());
        const cancellation = decodeHtml(value(input, 'cancellationPolicy', 'Free cancellation 1 day prior to arrival hotel local time (12:00 Hours), Thereafter any cancellation/no show leads to 100% retention charges.'));
        const contactPerson = decodeHtml(value(input, 'contactPerson', 'Manish Bhatia'));
        const contactPhone = decodeHtml(value(input, 'contactPhone', '919999831144'));
        const contactEmail = decodeHtml(value(input, 'contactEmail', 'manish@airwaystravels.com'));

        return {
            queryNumber,
            hotelName,
            location,
            checkIn: formatDate(input.checkIn),
            checkOut: formatDate(input.checkOut),
            personCount: adults + (children > 0 ? children : 0),
            rooms,
            occupancy: decodeHtml(occupancy),
            roomCategory,
            mealPlan: mealPlans(input, room, prices),
            roomPrice: formatPrice(roomPrice),
            cancellation,
            contactPerson,
            contactPhone,
            contactEmail
        };
    }

    function format(data) {
        const parts = quotationParts(data);
        return [
            '*Airways Travels | Quotation*',
            '',
            `Greetings from Airways Travels. Further with reference to query number *${decodeHtml(parts.queryNumber)}*, find below the quotation as desired:`,
            '',
            `*Hotel Name*: ${parts.hotelName} ,, ${parts.location}`,
            `*Check-In*: ${parts.checkIn}`,
            `*Check-Out*: ${parts.checkOut}`,
            `*No. of Person*: ${parts.personCount}`,
            `*No. of Rooms*: ${parts.rooms} Room`,
            `*Occupancy*: ${parts.occupancy}`,
            `*Room category*: ${parts.roomCategory}`,
            `*Meal plan*: ${parts.mealPlan}`,
            `*Room Price*: ${parts.roomPrice}/- per room per night`,
            '',
            '*Above rates are inclusive of taxes.*',
            '',
            `*Cancellation policy*: ${parts.cancellation}`,
            '',
            '*Rooms and Rates are Subject to Availability, please confirm the same at the earliest to proceed with the booking.*',
            '',
            'Thank you for contacting Airways Travels.',
            'In case of any support please contact us:',
            parts.contactPerson,
            `☎️ Mobile : ${parts.contactPhone}`,
            `✉️ Email : ${parts.contactEmail}`,
            '',
            '_Powered by Airways Travels_'
        ].join('\n');
    }

    function formatMany(items) {
        const quotations = (Array.isArray(items) ? items : []).map(quotationParts);
        if (!quotations.length) return '';
        const first = quotations[0];
        const hotelBlocks = quotations.map((parts) => [
            `*Hotel Name*: ${parts.hotelName} ,, ${parts.location}`,
            `*Check-In*: ${parts.checkIn}`,
            `*Check-Out*: ${parts.checkOut}`,
            `*No. of Person*: ${parts.personCount}`,
            `*No. of Rooms*: ${parts.rooms} Room`,
            `*Occupancy*: ${parts.occupancy}`,
            `*Room category*: ${parts.roomCategory}`,
            `*Meal plan*: ${parts.mealPlan}`,
            `*Room Price*: ${parts.roomPrice}/- per room per night`,
            ''
        ].join('\n')).join('\n');
        return [
            '*Airways Travels | Quotation*',
            '',
            `Greetings from Airways Travels. Further with reference to query number *${decodeHtml(first.queryNumber)}*, find below the quotation as desired:`,
            '',
            hotelBlocks,
            '*Above rates are inclusive of taxes.*',
            '',
            '*Cancellation policy*: ' + first.cancellation,
            '',
            '*Rooms and Rates are Subject to Availability, please confirm the same at the earliest to proceed with the booking.*',
            '',
            'Thank you for contacting Airways Travels.',
            'In case of any support please contact us:',
            first.contactPerson,
            `☎️ Mobile : ${first.contactPhone}`,
            `✉️ Email : ${first.contactEmail}`,
            '',
            '_Powered by Airways Travels_'
        ].join('\n');
    }

    window.AirwaysQuotation = { format, formatMany, plainText: decodeHtml };
})(window);
