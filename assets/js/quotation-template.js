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

    function formatMealPlans(input, prices) {
        const availablePrices = prices && typeof prices === 'object' ? prices : {};
        const labels = { EP: 'EP', CP: 'CP', MAP: 'MAP', AP: 'AP', AI: 'AI' };
        const plans = Object.entries(availablePrices)
            .filter(([, price]) => Number(price) > 0)
            .map(([code, price]) => `${labels[code] || code} - ${formatPrice(price)}/- per room per night`);
        if (plans.length) return plans.join(', ');
        const selectedPlan = decodeHtml(value(input, 'mealPlan', ''));
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

    function format(data) {
        const input = data || {};
        const selected = firstRoom(input);
        const hotel = selected.hotel;
        const room = selected.room;
        const prices = selected.prices;
        const hotelName = decodeHtml(value(input, 'hotelName', value(hotel, 'name', 'N/A')));
        const location = decodeHtml(value(input, 'hotelLocation', value(hotel, 'location', value(hotel, 'city', 'N/A'))));
        const roomCategory = decodeHtml(value(input, 'roomCategory', value(room, 'room_name', value(room, 'name', 'N/A'))));
        const mealPlan = formatMealPlans(input, prices);
        const adults = Number(value(input, 'adults', 1));
        const children = Number(value(input, 'children', 0));
        const rooms = Number(value(input, 'rooms', 1));
        const occupancy = value(input, 'occupancy', adults === 2 ? 'Double' : adults === 1 ? 'Single' : `${adults} Persons`);
        const roomPrice = value(input, 'roomPrice', value(room, 'selected_price', prices.EP || prices.MAP || prices.MAPAI || input.budget || 0));
        const queryNumber = value(input, 'queryNumber', input.id ? `UV-${String(input.id).padStart(4, '0')}` : 'UV-0001');
        const cancellation = decodeHtml(value(input, 'cancellationPolicy', 'Free cancellation 1 day prior to arrival hotel local time (12:00 Hours), Thereafter any cancellation/no show leads to 100% retention charges.'));
        const contactPerson = decodeHtml(value(input, 'contactPerson', 'Manish Bhatia'));
        const contactPhone = decodeHtml(value(input, 'contactPhone', '919999831144'));
        const contactEmail = decodeHtml(value(input, 'contactEmail', 'manish@airwaystravels.com'));

        return [
            '*Airways Travels | Quotation*',
            '',
            `Greetings from Airways Travels. Further with reference to query number *${decodeHtml(queryNumber)}*, find below the quotation as desired:`,
            '',
            `*Hotel Name*: ${hotelName} ,, ${location}`,
            `*Check-In*: ${formatDate(input.checkIn)}`,
            `*Check-Out*: ${formatDate(input.checkOut)}`,
            `*No. of Person*: ${adults + (children > 0 ? children : 0)}`,
            `*No. of Rooms*: ${rooms} Room`,
            `*Occupancy*: ${decodeHtml(occupancy)}`,
            `*Room category*: ${roomCategory}`,
            `*Meal plan*: ${mealPlan}`,
            `*Room Price*: ${formatPrice(roomPrice)}/- per room per night`,
            '',
            'Above rates are inclusive of taxes.',
            `*Cancellation policy*: ${cancellation}`,
            '',
            '*Rooms and Rates are Subject to Availability, please confirm the same at the earliest to proceed with the booking.*',
            '',
            'Thank you for contacting Airways Travels.',
            'In case of any support please contact us:',
            contactPerson,
            `☎️ Mobile : ${contactPhone}`,
            `✉️ Email : ${contactEmail}`,
            '',
            '_Powered by Airways Travels_'
        ].join('\n');
    }

    function formatMany(items) {
        const quotations = Array.isArray(items) ? items : [];
        if (quotations.length <= 1) return quotations.length ? format(quotations[0]).trim() : '';

        const firstQuotation = format(quotations[0]).trim();
        const lines = firstQuotation.split('\n');
        const roomCategoryIndex = lines.findIndex((line) => line.startsWith('*Room category*:'));
        const mealPlanIndex = lines.findIndex((line) => line.startsWith('*Meal plan*:'));
        const roomPriceIndex = lines.findIndex((line) => line.startsWith('*Room Price*:'));
        if (roomCategoryIndex < 0 || mealPlanIndex < 0 || roomPriceIndex < 0) return firstQuotation;

        const seen = new Set();
        const roomOptions = [];
        quotations.forEach((item) => {
            const selected = firstRoom(item);
            const room = selected.room;
            const roomName = decodeHtml(value(item, 'roomCategory', value(room, 'room_name', value(room, 'name', 'N/A'))));
            const mealPlan = formatMealPlans(item, selected.prices);
            const roomPrice = formatPrice(value(item, 'roomPrice', value(room, 'selected_price', selected.prices.EP || selected.prices.MAP || item.budget || 0)));
            const hotelName = decodeHtml(value(item, 'hotelName', value(selected.hotel, 'name', 'N/A')));
            const optionKey = `${hotelName}|${roomName}|${mealPlan}|${roomPrice}`;
            if (seen.has(optionKey)) return;
            seen.add(optionKey);
            roomOptions.push({ hotelName, roomName, mealPlan, roomPrice });
        });

        const firstHotelName = roomOptions[0]?.hotelName || '';
        const optionLines = [];
        roomOptions.forEach((option, index) => {
            if (option.hotelName !== firstHotelName) optionLines.push(`*Hotel Name*: ${option.hotelName}`);
            optionLines.push(`*Room category*: ${option.roomName}`);
            optionLines.push(`*Meal plan*: ${option.mealPlan}`);
            optionLines.push(`*Room Price*: ${option.roomPrice}/- per room per night`);
            if (index < roomOptions.length - 1) optionLines.push('');
        });

        lines.splice(roomCategoryIndex, roomPriceIndex - roomCategoryIndex + 1, ...optionLines);
        return lines.join('\n');
    }

    window.AirwaysQuotation = { format, formatMany, plainText: decodeHtml };
})(window);
