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
        const hotelName = value(input, 'hotelName', value(hotel, 'name', 'N/A'));
        const location = value(input, 'hotelLocation', value(hotel, 'location', value(hotel, 'city', 'N/A')));
        const roomCategory = value(input, 'roomCategory', value(room, 'room_name', value(room, 'name', 'N/A')));
        const mealPlan = value(input, 'mealPlan', value(room, 'meal_plan', Object.keys(prices)[0] || 'MAPAI'));
        const adults = Number(value(input, 'adults', 1));
        const children = Number(value(input, 'children', 0));
        const rooms = Number(value(input, 'rooms', 1));
        const occupancy = value(input, 'occupancy', adults === 2 ? 'Double' : adults === 1 ? 'Single' : `${adults} Persons`);
        const roomPrice = value(input, 'roomPrice', value(room, 'selected_price', prices.EP || prices.MAP || prices.MAPAI || input.budget || 0));
        const queryNumber = value(input, 'queryNumber', input.id ? `L${input.id}` : 'N/A');
        const tax = value(input, 'tax', '18%');
        const cancellation = value(input, 'cancellationPolicy', 'Free cancellation 1 day prior to arrival hotel local time (12:00 Hours), Thereafter any cancellation/no show leads to 100% retention charges.');
        const contactPerson = value(input, 'contactPerson', 'Manish Bhatia');
        const contactPhone = value(input, 'contactPhone', '919999831144');
        const contactEmail = value(input, 'contactEmail', 'manish@airwaystravels.com');

        return `*Airways Travels | Quotation*

    Greetings from Airways Travels. Further with reference to query number *${queryNumber}*, find below the quotation as desired:

    *Hotel Name*: ${hotelName} ,, ${location}
    *Check-In*: ${formatDate(input.checkIn)}
    *Check-Out*: ${formatDate(input.checkOut)}
    *No. of Person*: ${adults + (children > 0 ? children : 0)}
    *No. of Rooms*: ${rooms} Room
    *Occupancy*: ${occupancy}
    *Room category*: ${roomCategory}
    *Meal plan*: ${mealPlan}
    *Room Price*: ${formatPrice(roomPrice)}/- per room per night

    *Above rates are inclusive of ${tax} taxes.*

    *Cancellation policy*: ${cancellation}

    *Rooms and Rates are Subject to Availability, please confirm the same at the earliest to proceed with the booking.*

    Thank you for contacting Airways Travels.
    In case of any support please contact us:
    ${contactPerson}
    ☎️ Mobile : ${contactPhone}
    ✉️ Email : ${contactEmail}

    _Powered by Airways Travels_`;
    }

    window.AirwaysQuotation = { format };
})(window);
