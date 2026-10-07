<?php
// rates.php - All-in-one file (Viewer & Admin Dashboard)
session_start();

// --- 1. SETUP DATA FILE & DEFAULT JSON DATA ---
$data_file = 'hotels.json';

// Automatically create and seed the JSON file if it doesn't exist
if (!file_exists($data_file)) {
    $default_json = <<<'JSON'
[
    {
        "id": "htl_6ab4e26416131",
        "name": "Tarangi Resort & Spa",
        "location": "Jim Corbett",
        "categories": [
            {
                "room_category": "Sarang Room",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "8050",
                "wd_capai_eb": "35%",
                "wd_capai_cweb": "25%",
                "wd_mapai": "9700",
                "wd_mapai_eb": "35%",
                "wd_mapai_cweb": "25%",
                "wd_apai": "11200",
                "wd_apai_eb": "35%",
                "wd_apai_cweb": "25%",
                "we_capai": "9050",
                "we_capai_eb": "35%",
                "we_capai_cweb": "25%",
                "we_mapai": "10700",
                "we_mapai_eb": "35%",
                "we_mapai_cweb": "25%",
                "we_apai": "12200",
                "we_apai_eb": "35%",
                "we_apai_cweb": "25%",
                "remarks": ""
            },
            {
                "room_category": "Luxury Cottage",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "12050",
                "wd_capai_eb": "35%",
                "wd_capai_cweb": "25%",
                "wd_mapai": "13700",
                "wd_mapai_eb": "35%",
                "wd_mapai_cweb": "25%",
                "wd_apai": "15200",
                "wd_apai_eb": "35%",
                "wd_apai_cweb": "25%",
                "we_capai": "13050",
                "we_capai_eb": "35%",
                "we_capai_cweb": "25%",
                "we_mapai": "14700",
                "we_mapai_eb": "35%",
                "we_mapai_cweb": "25%",
                "we_apai": "16200",
                "we_apai_eb": "35%",
                "we_apai_cweb": "25%",
                "remarks": ""
            },
            {
                "room_category": "River View Cottage",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "15550",
                "wd_capai_eb": "35%",
                "wd_capai_cweb": "25%",
                "wd_mapai": "17200",
                "wd_mapai_eb": "35%",
                "wd_mapai_cweb": "25%",
                "wd_apai": "18200",
                "wd_apai_eb": "35%",
                "wd_apai_cweb": "25%",
                "we_capai": "16550",
                "we_capai_eb": "35%",
                "we_capai_cweb": "25%",
                "we_mapai": "18200",
                "we_mapai_eb": "35%",
                "we_mapai_cweb": "25%",
                "we_apai": "19700",
                "we_apai_eb": "35%",
                "we_apai_cweb": "25%",
                "remarks": ""
            },
            {
                "room_category": "Pine Wooden Cottage",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "15550",
                "wd_capai_eb": "35%",
                "wd_capai_cweb": "25%",
                "wd_mapai": "17200",
                "wd_mapai_eb": "35%",
                "wd_mapai_cweb": "25%",
                "wd_apai": "18700",
                "wd_apai_eb": "35%",
                "wd_apai_cweb": "25%",
                "we_capai": "16550",
                "we_capai_eb": "35%",
                "we_capai_cweb": "25%",
                "we_mapai": "18200",
                "we_mapai_eb": "35%",
                "we_mapai_cweb": "25%",
                "we_apai": "19700",
                "we_apai_eb": "35%",
                "we_apai_cweb": "25%",
                "remarks": ""
            },
            {
                "room_category": "Whispering River View Villa (4 Bedroom)",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "32400",
                "wd_capai_eb": "35%",
                "wd_capai_cweb": "25%",
                "wd_mapai": "35700",
                "wd_mapai_eb": "35%",
                "wd_mapai_cweb": "25%",
                "wd_apai": "38700",
                "wd_apai_eb": "35%",
                "wd_apai_cweb": "25%",
                "we_capai": "35400",
                "we_capai_eb": "35%",
                "we_capai_cweb": "25%",
                "we_mapai": "38700",
                "we_mapai_eb": "35%",
                "we_mapai_cweb": "25%",
                "we_apai": "41700",
                "we_apai_eb": "35%",
                "we_apai_cweb": "25%",
                "remarks": ""
            }
        ]
    },
    {
        "id": "htl_6ab4f6787be48",
        "name": "WH Tarangi Ramganga Resort",
        "location": "Jim Corbett",
        "categories": [
            {
                "room_category": "Standard Rooms",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "7300",
                "wd_capai_eb": "35%",
                "wd_capai_cweb": "25%",
                "wd_mapai": "8500",
                "wd_mapai_eb": "35%",
                "wd_mapai_cweb": "25%",
                "wd_apai": "9700",
                "wd_apai_eb": "35%",
                "wd_apai_cweb": "25%",
                "we_capai": "8300",
                "we_capai_eb": "35%",
                "we_capai_cweb": "25%",
                "we_mapai": "9500",
                "we_mapai_eb": "35%",
                "we_mapai_cweb": "25%",
                "we_apai": "10700",
                "we_apai_eb": "35%",
                "we_apai_cweb": "25%",
                "remarks": ""
            },
            {
                "room_category": "Superior Rooms",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "7300",
                "wd_capai_eb": "",
                "wd_capai_cweb": "",
                "wd_mapai": "8500",
                "wd_mapai_eb": "",
                "wd_mapai_cweb": "",
                "wd_apai": "9700",
                "wd_apai_eb": "",
                "wd_apai_cweb": "",
                "we_capai": "8300",
                "we_capai_eb": "",
                "we_capai_cweb": "",
                "we_mapai": "9500",
                "we_mapai_eb": "",
                "we_mapai_cweb": "",
                "we_apai": "10700",
                "we_apai_eb": "",
                "we_apai_cweb": "",
                "remarks": ""
            },
            {
                "room_category": "Deluxe Room",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "8300",
                "wd_capai_eb": "35%",
                "wd_capai_cweb": "25%",
                "wd_mapai": "9500",
                "wd_mapai_eb": "35%",
                "wd_mapai_cweb": "25%",
                "wd_apai": "10700",
                "wd_apai_eb": "35%",
                "wd_apai_cweb": "25%",
                "we_capai": "9300",
                "we_capai_eb": "35%",
                "we_capai_cweb": "25%",
                "we_mapai": "10500",
                "we_mapai_eb": "35%",
                "we_mapai_cweb": "25%",
                "we_apai": "11700",
                "we_apai_eb": "35%",
                "we_apai_cweb": "25%",
                "remarks": ""
            },
            {
                "room_category": "Ramganga Suite",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "10300",
                "wd_capai_eb": "35%",
                "wd_capai_cweb": "25%",
                "wd_mapai": "11500",
                "wd_mapai_eb": "35%",
                "wd_mapai_cweb": "25%",
                "wd_apai": "12700",
                "wd_apai_eb": "35%",
                "wd_apai_cweb": "25%",
                "we_capai": "11300",
                "we_capai_eb": "35%",
                "we_capai_cweb": "25%",
                "we_mapai": "12500",
                "we_mapai_eb": "35%",
                "we_mapai_cweb": "25%",
                "we_apai": "13700",
                "we_apai_eb": "35%",
                "we_apai_cweb": "25%",
                "remarks": ""
            },
            {
                "room_category": "Pine Cottage Nature View",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "10800",
                "wd_capai_eb": "35%",
                "wd_capai_cweb": "25%",
                "wd_mapai": "12000",
                "wd_mapai_eb": "35%",
                "wd_mapai_cweb": "25%",
                "wd_apai": "13200",
                "wd_apai_eb": "35%",
                "wd_apai_cweb": "25%",
                "we_capai": "11800",
                "we_capai_eb": "35%",
                "we_capai_cweb": "25%",
                "we_mapai": "13000",
                "we_mapai_eb": "35%",
                "we_mapai_cweb": "25%",
                "we_apai": "14200",
                "we_apai_eb": "35%",
                "we_apai_cweb": "25%",
                "remarks": ""
            },
            {
                "room_category": "Pine Cottage Pool View",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "11800",
                "wd_capai_eb": "35%",
                "wd_capai_cweb": "25%",
                "wd_mapai": "13000",
                "wd_mapai_eb": "35%",
                "wd_mapai_cweb": "25%",
                "wd_apai": "14200",
                "wd_apai_eb": "35%",
                "wd_apai_cweb": "25%",
                "we_capai": "12800",
                "we_capai_eb": "35%",
                "we_capai_cweb": "25%",
                "we_mapai": "14000",
                "we_mapai_eb": "35%",
                "we_mapai_cweb": "25%",
                "we_apai": "15200",
                "we_apai_eb": "35%",
                "we_apai_cweb": "25%",
                "remarks": ""
            },
            {
                "room_category": "Duplex Cottage (2 Bedrooms)",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "20000",
                "wd_capai_eb": "35%",
                "wd_capai_cweb": "25%",
                "wd_mapai": "22500",
                "wd_mapai_eb": "35%",
                "wd_mapai_cweb": "25%",
                "wd_apai": "25000",
                "wd_apai_eb": "35%",
                "wd_apai_cweb": "25%",
                "we_capai": "21000",
                "we_capai_eb": "35%",
                "we_capai_cweb": "25%",
                "we_mapai": "23500",
                "we_mapai_eb": "35%",
                "we_mapai_cweb": "25%",
                "we_apai": "26000",
                "we_apai_eb": "35%",
                "we_apai_cweb": "25%",
                "remarks": ""
            },
            {
                "room_category": "River Facing Family Cottage (4 Bedrooms)",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "31000",
                "wd_capai_eb": "35%",
                "wd_capai_cweb": "25%",
                "wd_mapai": "35800",
                "wd_mapai_eb": "35%",
                "wd_mapai_cweb": "25%",
                "wd_apai": "40600",
                "wd_apai_eb": "35%",
                "wd_apai_cweb": "25%",
                "we_capai": "32000",
                "we_capai_eb": "35%",
                "we_capai_cweb": "25%",
                "we_mapai": "36800",
                "we_mapai_eb": "35%",
                "we_mapai_cweb": "25%",
                "we_apai": "41600",
                "we_apai_eb": "35%",
                "we_apai_cweb": "25%",
                "remarks": ""
            }
        ]
    },
    {
        "id": "htl_6ab5046c9c2fb",
        "name": "The Golden Tusk",
        "location": "Jim Corbett",
        "categories": [
            {
                "room_category": "Garden Suite",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "9000",
                "wd_capai_eb": "35%",
                "wd_capai_cweb": "25%",
                "wd_mapai": "10000",
                "wd_mapai_eb": "35%",
                "wd_mapai_cweb": "25%",
                "wd_apai": "11000",
                "wd_apai_eb": "35%",
                "wd_apai_cweb": "25%",
                "we_capai": "9000",
                "we_capai_eb": "35%",
                "we_capai_cweb": "25%",
                "we_mapai": "10000",
                "we_mapai_eb": "35%",
                "we_mapai_cweb": "25%",
                "we_apai": "11000",
                "we_apai_eb": "35%",
                "we_apai_cweb": "25%",
                "remarks": ""
            },
            {
                "room_category": "Nature View Suite",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "9000",
                "wd_capai_eb": "35%",
                "wd_capai_cweb": "25%",
                "wd_mapai": "10000",
                "wd_mapai_eb": "35%",
                "wd_mapai_cweb": "25%",
                "wd_apai": "11000",
                "wd_apai_eb": "35%",
                "wd_apai_cweb": "25%",
                "we_capai": "9000",
                "we_capai_eb": "35%",
                "we_capai_cweb": "25%",
                "we_mapai": "10000",
                "we_mapai_eb": "35%",
                "we_mapai_cweb": "25%",
                "we_apai": "11000",
                "we_apai_eb": "35%",
                "we_apai_cweb": "25%",
                "remarks": ""
            },
            {
                "room_category": "Luxury Tents",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "9000",
                "wd_capai_eb": "35%",
                "wd_capai_cweb": "25%",
                "wd_mapai": "10000",
                "wd_mapai_eb": "35%",
                "wd_mapai_cweb": "25%",
                "wd_apai": "11000",
                "wd_apai_eb": "35%",
                "wd_apai_cweb": "25%",
                "we_capai": "9000",
                "we_capai_eb": "35%",
                "we_capai_cweb": "25%",
                "we_mapai": "10000",
                "we_mapai_eb": "35%",
                "we_mapai_cweb": "25%",
                "we_apai": "11000",
                "we_apai_eb": "35%",
                "we_apai_cweb": "25%",
                "remarks": ""
            },
            {
                "room_category": "Pool View Suite",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "11000",
                "wd_capai_eb": "35%",
                "wd_capai_cweb": "25%",
                "wd_mapai": "12000",
                "wd_mapai_eb": "35%",
                "wd_mapai_cweb": "25%",
                "wd_apai": "13000",
                "wd_apai_eb": "35%",
                "wd_apai_cweb": "25%",
                "we_capai": "11000",
                "we_capai_eb": "35%",
                "we_capai_cweb": "25%",
                "we_mapai": "12000",
                "we_mapai_eb": "35%",
                "we_mapai_cweb": "25%",
                "we_apai": "13000",
                "we_apai_eb": "35%",
                "we_apai_cweb": "25%",
                "remarks": ""
            },
            {
                "room_category": "Corbett Suite",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "11000",
                "wd_capai_eb": "35%",
                "wd_capai_cweb": "25%",
                "wd_mapai": "12000",
                "wd_mapai_eb": "35%",
                "wd_mapai_cweb": "25%",
                "wd_apai": "13000",
                "wd_apai_eb": "35%",
                "wd_apai_cweb": "25%",
                "we_capai": "11000",
                "we_capai_eb": "35%",
                "we_capai_cweb": "25%",
                "we_mapai": "12000",
                "we_mapai_eb": "35%",
                "we_mapai_cweb": "25%",
                "we_apai": "13000",
                "we_apai_eb": "35%",
                "we_apai_cweb": "25%",
                "remarks": ""
            },
            {
                "room_category": "Villa",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "13000",
                "wd_capai_eb": "35%",
                "wd_capai_cweb": "25%",
                "wd_mapai": "14000",
                "wd_mapai_eb": "35%",
                "wd_mapai_cweb": "25%",
                "wd_apai": "15000",
                "wd_apai_eb": "35%",
                "wd_apai_cweb": "25%",
                "we_capai": "13000",
                "we_capai_eb": "35%",
                "we_capai_cweb": "25%",
                "we_mapai": "14000",
                "we_mapai_eb": "35%",
                "we_mapai_cweb": "25%",
                "we_apai": "15000",
                "we_apai_eb": "35%",
                "we_apai_cweb": "25%",
                "remarks": ""
            },
            {
                "room_category": "Villa Grande",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "14000",
                "wd_capai_eb": "35%",
                "wd_capai_cweb": "25%",
                "wd_mapai": "15000",
                "wd_mapai_eb": "35%",
                "wd_mapai_cweb": "25%",
                "wd_apai": "16000",
                "wd_apai_eb": "35%",
                "wd_apai_cweb": "25%",
                "we_capai": "14000",
                "we_capai_eb": "35%",
                "we_capai_cweb": "25%",
                "we_mapai": "15000",
                "we_mapai_eb": "35%",
                "we_mapai_cweb": "25%",
                "we_apai": "16000",
                "we_apai_eb": "35%",
                "we_apai_cweb": "25%",
                "remarks": ""
            },
            {
                "room_category": "Tusk Suite",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "14000",
                "wd_capai_eb": "35%",
                "wd_capai_cweb": "25%",
                "wd_mapai": "15000",
                "wd_mapai_eb": "35%",
                "wd_mapai_cweb": "25%",
                "wd_apai": "16000",
                "wd_apai_eb": "35%",
                "wd_apai_cweb": "25%",
                "we_capai": "14000",
                "we_capai_eb": "35%",
                "we_capai_cweb": "25%",
                "we_mapai": "15000",
                "we_mapai_eb": "35%",
                "we_mapai_cweb": "25%",
                "we_apai": "16000",
                "we_apai_eb": "35%",
                "we_apai_cweb": "25%",
                "remarks": ""
            },
            {
                "room_category": "Tiger Suite 2 Bedrooms for 4 Pax",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "18000",
                "wd_capai_eb": "35%",
                "wd_capai_cweb": "25%",
                "wd_mapai": "20000",
                "wd_mapai_eb": "35%",
                "wd_mapai_cweb": "25%",
                "wd_apai": "22000",
                "wd_apai_eb": "35%",
                "wd_apai_cweb": "25%",
                "we_capai": "18000",
                "we_capai_eb": "35%",
                "we_capai_cweb": "25%",
                "we_mapai": "20000",
                "we_mapai_eb": "35%",
                "we_mapai_cweb": "25%",
                "we_apai": "22000",
                "we_apai_eb": "35%",
                "we_apai_cweb": "25%",
                "remarks": ""
            },
            {
                "room_category": "Jungle Spring Villa 4 Seater Jacuzzi",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "15500",
                "wd_capai_eb": "35%",
                "wd_capai_cweb": "25%",
                "wd_mapai": "16500",
                "wd_mapai_eb": "35%",
                "wd_mapai_cweb": "25%",
                "wd_apai": "17500",
                "wd_apai_eb": "35%",
                "wd_apai_cweb": "25%",
                "we_capai": "15500",
                "we_capai_eb": "35%",
                "we_capai_cweb": "25%",
                "we_mapai": "16500",
                "we_mapai_eb": "35%",
                "we_mapai_cweb": "25%",
                "we_apai": "17500",
                "we_apai_eb": "35%",
                "we_apai_cweb": "25%",
                "remarks": ""
            },
            {
                "room_category": "Water Hole Villa (With Pvt Pool & 4 Seater Jacuzzi)",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "23500",
                "wd_capai_eb": "35%",
                "wd_capai_cweb": "25%",
                "wd_mapai": "24500",
                "wd_mapai_eb": "35%",
                "wd_mapai_cweb": "25%",
                "wd_apai": "25500",
                "wd_apai_eb": "35%",
                "wd_apai_cweb": "25%",
                "we_capai": "23500",
                "we_capai_eb": "35%",
                "we_capai_cweb": "25%",
                "we_mapai": "24500",
                "we_mapai_eb": "35%",
                "we_mapai_cweb": "25%",
                "we_apai": "25500",
                "we_apai_eb": "35%",
                "we_apai_cweb": "25%",
                "remarks": ""
            }
        ]
    },
    {
        "id": "htl_6ab601fa0fa36",
        "name": "Namah Nainital A Member of Radisson",
        "location": "Nainital",
        "categories": [
            {
                "room_category": "Standard Room",
                "valid_from": "2026-10-01",
                "valid_to": "2026-12-31",
                "wd_capai": "13850",
                "wd_capai_eb": "2900",
                "wd_capai_cweb": "2900",
                "wd_mapai": "17400",
                "wd_mapai_eb": "4100",
                "wd_mapai_cweb": "4100",
                "wd_apai": "",
                "wd_apai_eb": "",
                "wd_apai_cweb": "",
                "we_capai": "15000",
                "we_capai_eb": "2900",
                "we_capai_cweb": "2900",
                "we_mapai": "18550",
                "we_mapai_eb": "4100",
                "we_mapai_cweb": "4100",
                "we_apai": "",
                "we_apai_eb": "",
                "we_apai_cweb": "",
                "remarks": ""
            },
            {
                "room_category": "Superior Room",
                "valid_from": "2026-10-01",
                "valid_to": "2026-12-31",
                "wd_capai": "15000",
                "wd_capai_eb": "2900",
                "wd_capai_cweb": "2900",
                "wd_mapai": "18550",
                "wd_mapai_eb": "4100",
                "wd_mapai_cweb": "4100",
                "wd_apai": "",
                "wd_apai_eb": "",
                "wd_apai_cweb": "",
                "we_capai": "16250",
                "we_capai_eb": "2900",
                "we_capai_cweb": "2900",
                "we_mapai": "19750",
                "we_mapai_eb": "4100",
                "we_mapai_cweb": "4100",
                "we_apai": "",
                "we_apai_eb": "",
                "we_apai_cweb": "",
                "remarks": ""
            }
        ]
    },
    {
        "id": "htl_6ab608d5af276",
        "name": "La Perle River Resort",
        "location": "Jim Corbett",
        "categories": [
            {
                "room_category": "Cotton Tree",
                "valid_from": "2026-10-01",
                "valid_to": "2026-12-20",
                "wd_capai": "",
                "wd_capai_eb": "",
                "wd_capai_cweb": "",
                "wd_mapai": "5400",
                "wd_mapai_eb": "35%",
                "wd_mapai_cweb": "25%",
                "wd_apai": "5900",
                "wd_apai_eb": "35%",
                "wd_apai_cweb": "25%",
                "we_capai": "",
                "we_capai_eb": "",
                "we_capai_cweb": "",
                "we_mapai": "5400",
                "we_mapai_eb": "35%",
                "we_mapai_cweb": "25%",
                "we_apai": "5900",
                "we_apai_eb": "35%",
                "we_apai_cweb": "25%",
                "remarks": ""
            },
            {
                "room_category": "Cotton Tree Superior",
                "valid_from": "2026-10-01",
                "valid_to": "2026-12-20",
                "wd_capai": "",
                "wd_capai_eb": "",
                "wd_capai_cweb": "",
                "wd_mapai": "5900",
                "wd_mapai_eb": "35%",
                "wd_mapai_cweb": "25%",
                "wd_apai": "6400",
                "wd_apai_eb": "35%",
                "wd_apai_cweb": "25%",
                "we_capai": "",
                "we_capai_eb": "",
                "we_capai_cweb": "",
                "we_mapai": "5900",
                "we_mapai_eb": "35%",
                "we_mapai_cweb": "25%",
                "we_apai": "6400",
                "we_apai_eb": "35%",
                "we_apai_cweb": "25%",
                "remarks": ""
            },
            {
                "room_category": "Leopard Lair",
                "valid_from": "2026-10-01",
                "valid_to": "2026-12-20",
                "wd_capai": "",
                "wd_capai_eb": "",
                "wd_capai_cweb": "",
                "wd_mapai": "6650",
                "wd_mapai_eb": "35%",
                "wd_mapai_cweb": "25%",
                "wd_apai": "7150",
                "wd_apai_eb": "35%",
                "wd_apai_cweb": "25%",
                "we_capai": "",
                "we_capai_eb": "",
                "we_capai_cweb": "",
                "we_mapai": "6650",
                "we_mapai_eb": "35%",
                "we_mapai_cweb": "25%",
                "we_apai": "7150",
                "we_apai_eb": "35%",
                "we_apai_cweb": "25%",
                "remarks": ""
            },
            {
                "room_category": "Tiger Den Cottage",
                "valid_from": "2026-10-01",
                "valid_to": "2026-12-20",
                "wd_capai": "",
                "wd_capai_eb": "",
                "wd_capai_cweb": "",
                "wd_mapai": "7150",
                "wd_mapai_eb": "35%",
                "wd_mapai_cweb": "25%",
                "wd_apai": "7650",
                "wd_apai_eb": "35%",
                "wd_apai_cweb": "25%",
                "we_capai": "",
                "we_capai_eb": "",
                "we_capai_cweb": "",
                "we_mapai": "7150",
                "we_mapai_eb": "35%",
                "we_mapai_cweb": "25%",
                "we_apai": "7650",
                "we_apai_eb": "35%",
                "we_apai_cweb": "25%",
                "remarks": ""
            },
            {
                "room_category": "Elephant Park",
                "valid_from": "2026-10-01",
                "valid_to": "2026-12-20",
                "wd_capai": "",
                "wd_capai_eb": "",
                "wd_capai_cweb": "",
                "wd_mapai": "7650",
                "wd_mapai_eb": "35%",
                "wd_mapai_cweb": "25%",
                "wd_apai": "8150",
                "wd_apai_eb": "35%",
                "wd_apai_cweb": "25%",
                "we_capai": "",
                "we_capai_eb": "",
                "we_capai_cweb": "",
                "we_mapai": "7650",
                "we_mapai_eb": "35%",
                "we_mapai_cweb": "25%",
                "we_apai": "8150",
                "we_apai_eb": "35%",
                "we_apai_cweb": "25%",
                "remarks": ""
            },
            {
                "room_category": "Elephant Park Premium",
                "valid_from": "2026-10-01",
                "valid_to": "2026-12-20",
                "wd_capai": "",
                "wd_capai_eb": "",
                "wd_capai_cweb": "",
                "wd_mapai": "9150",
                "wd_mapai_eb": "35%",
                "wd_mapai_cweb": "25%",
                "wd_apai": "9650",
                "wd_apai_eb": "35%",
                "wd_apai_cweb": "25%",
                "we_capai": "",
                "we_capai_eb": "",
                "we_capai_cweb": "",
                "we_mapai": "9150",
                "we_mapai_eb": "35%",
                "we_mapai_cweb": "25%",
                "we_apai": "9650",
                "we_apai_eb": "35%",
                "we_apai_cweb": "25%",
                "remarks": ""
            },
            {
                "room_category": "Junior Suite",
                "valid_from": "2026-10-01",
                "valid_to": "2026-12-20",
                "wd_capai": "",
                "wd_capai_eb": "",
                "wd_capai_cweb": "",
                "wd_mapai": "9650",
                "wd_mapai_eb": "35%",
                "wd_mapai_cweb": "25%",
                "wd_apai": "10150",
                "wd_apai_eb": "35%",
                "wd_apai_cweb": "25%",
                "we_capai": "",
                "we_capai_eb": "",
                "we_capai_cweb": "",
                "we_mapai": "9650",
                "we_mapai_eb": "35%",
                "we_mapai_cweb": "25%",
                "we_apai": "10150",
                "we_apai_eb": "35%",
                "we_apai_cweb": "25%",
                "remarks": ""
            },
            {
                "room_category": "The Jungle Suite",
                "valid_from": "2026-10-01",
                "valid_to": "2026-12-20",
                "wd_capai": "",
                "wd_capai_eb": "",
                "wd_capai_cweb": "",
                "wd_mapai": "14650",
                "wd_mapai_eb": "35%",
                "wd_mapai_cweb": "25%",
                "wd_apai": "15150",
                "wd_apai_eb": "35%",
                "wd_apai_cweb": "25%",
                "we_capai": "",
                "we_capai_eb": "",
                "we_capai_cweb": "",
                "we_mapai": "14650",
                "we_mapai_eb": "35%",
                "we_mapai_cweb": "25%",
                "we_apai": "15150",
                "we_apai_eb": "35%",
                "we_apai_cweb": "25%",
                "remarks": ""
            }
        ]
    },
    {
        "id": "htl_6ab6185906948",
        "name": "Mango Bloom River Resort",
        "location": "Jim Corbett",
        "categories": [
            {
                "room_category": "The Pine Room",
                "valid_from": "2026-10-01",
                "valid_to": "2026-12-20",
                "wd_capai": "",
                "wd_capai_eb": "",
                "wd_capai_cweb": "",
                "wd_mapai": "6650",
                "wd_mapai_eb": "35%",
                "wd_mapai_cweb": "25%",
                "wd_apai": "7150",
                "wd_apai_eb": "35%",
                "wd_apai_cweb": "25%",
                "we_capai": "",
                "we_capai_eb": "",
                "we_capai_cweb": "",
                "we_mapai": "6650",
                "we_mapai_eb": "35%",
                "we_mapai_cweb": "25%",
                "we_apai": "7150",
                "we_apai_eb": "35%",
                "we_apai_cweb": "25%",
                "remarks": ""
            },
            {
                "room_category": "The Cedar Room",
                "valid_from": "2026-10-01",
                "valid_to": "2026-12-20",
                "wd_capai": "",
                "wd_capai_eb": "",
                "wd_capai_cweb": "",
                "wd_mapai": "7650",
                "wd_mapai_eb": "35%",
                "wd_mapai_cweb": "25%",
                "wd_apai": "8150",
                "wd_apai_eb": "35%",
                "wd_apai_cweb": "25%",
                "we_capai": "",
                "we_capai_eb": "",
                "we_capai_cweb": "",
                "we_mapai": "7650",
                "we_mapai_eb": "35%",
                "we_mapai_cweb": "25%",
                "we_apai": "8150",
                "we_apai_eb": "35%",
                "we_apai_cweb": "25%",
                "remarks": ""
            },
            {
                "room_category": "The Oak Room",
                "valid_from": "2026-10-01",
                "valid_to": "2026-12-20",
                "wd_capai": "",
                "wd_capai_eb": "",
                "wd_capai_cweb": "",
                "wd_mapai": "8150",
                "wd_mapai_eb": "35%",
                "wd_mapai_cweb": "25%",
                "wd_apai": "8650",
                "wd_apai_eb": "35%",
                "wd_apai_cweb": "25%",
                "we_capai": "",
                "we_capai_eb": "",
                "we_capai_cweb": "",
                "we_mapai": "8150",
                "we_mapai_eb": "35%",
                "we_mapai_cweb": "25%",
                "we_apai": "8650",
                "we_apai_eb": "35%",
                "we_apai_cweb": "25%",
                "remarks": ""
            },
            {
                "room_category": "The Palm Suites",
                "valid_from": "2026-10-01",
                "valid_to": "2026-12-20",
                "wd_capai": "",
                "wd_capai_eb": "",
                "wd_capai_cweb": "",
                "wd_mapai": "10650",
                "wd_mapai_eb": "35%",
                "wd_mapai_cweb": "25%",
                "wd_apai": "11150",
                "wd_apai_eb": "35%",
                "wd_apai_cweb": "25%",
                "we_capai": "",
                "we_capai_eb": "",
                "we_capai_cweb": "",
                "we_mapai": "10650",
                "we_mapai_eb": "35%",
                "we_mapai_cweb": "25%",
                "we_apai": "11150",
                "we_apai_eb": "35%",
                "we_apai_cweb": "25%",
                "remarks": ""
            }
        ]
    },
    {
        "id": "htl_6ab657a439060",
        "name": "The Fern Brentwood Resort",
        "location": "Mussoorie",
        "categories": [
            {
                "room_category": "Winter Green",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "7100",
                "wd_capai_eb": "1500",
                "wd_capai_cweb": "1500",
                "wd_mapai": "9500",
                "wd_mapai_eb": "2350",
                "wd_mapai_cweb": "2350",
                "wd_apai": "",
                "wd_apai_eb": "",
                "wd_apai_cweb": "",
                "we_capai": "7100",
                "we_capai_eb": "1500",
                "we_capai_cweb": "1500",
                "we_mapai": "9500",
                "we_mapai_eb": "2350",
                "we_mapai_cweb": "2350",
                "we_apai": "",
                "we_apai_eb": "",
                "we_apai_cweb": "",
                "remarks": ""
            },
            {
                "room_category": "Winter Green Premium",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "7700",
                "wd_capai_eb": "1500",
                "wd_capai_cweb": "1500",
                "wd_mapai": "10100",
                "wd_mapai_eb": "2350",
                "wd_mapai_cweb": "2350",
                "wd_apai": "",
                "wd_apai_eb": "",
                "wd_apai_cweb": "",
                "we_capai": "7700",
                "we_capai_eb": "1500",
                "we_capai_cweb": "1500",
                "we_mapai": "10100",
                "we_mapai_eb": "2350",
                "we_mapai_cweb": "2350",
                "we_apai": "",
                "we_apai_eb": "",
                "we_apai_cweb": "",
                "remarks": ""
            },
            {
                "room_category": "Fern Club (Partial Valley View)",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "8200",
                "wd_capai_eb": "1500",
                "wd_capai_cweb": "1500",
                "wd_mapai": "10700",
                "wd_mapai_eb": "2350",
                "wd_mapai_cweb": "2350",
                "wd_apai": "",
                "wd_apai_eb": "",
                "wd_apai_cweb": "",
                "we_capai": "8200",
                "we_capai_eb": "1500",
                "we_capai_cweb": "1500",
                "we_mapai": "10700",
                "we_mapai_eb": "2350",
                "we_mapai_cweb": "2350",
                "we_apai": "",
                "we_apai_eb": "",
                "we_apai_cweb": "",
                "remarks": ""
            },
            {
                "room_category": "Hazel Suite (4 Pax)",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "12800",
                "wd_capai_eb": "1500",
                "wd_capai_cweb": "1500",
                "wd_mapai": "16000",
                "wd_mapai_eb": "2350",
                "wd_mapai_cweb": "2350",
                "wd_apai": "",
                "wd_apai_eb": "",
                "wd_apai_cweb": "",
                "we_capai": "12800",
                "we_capai_eb": "1500",
                "we_capai_cweb": "1500",
                "we_mapai": "16000",
                "we_mapai_eb": "2350",
                "we_mapai_cweb": "2350",
                "we_apai": "",
                "we_apai_eb": "",
                "we_apai_cweb": "",
                "remarks": ""
            },
            {
                "room_category": "Family Suite (4 Pax)",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "14600",
                "wd_capai_eb": "1500",
                "wd_capai_cweb": "1500",
                "wd_mapai": "17600",
                "wd_mapai_eb": "2350",
                "wd_mapai_cweb": "2350",
                "wd_apai": "",
                "wd_apai_eb": "",
                "wd_apai_cweb": "",
                "we_capai": "14600",
                "we_capai_eb": "1500",
                "we_capai_cweb": "1500",
                "we_mapai": "17600",
                "we_mapai_eb": "2350",
                "we_mapai_cweb": "2350",
                "we_apai": "",
                "we_apai_eb": "",
                "we_apai_cweb": "",
                "remarks": ""
            },
            {
                "room_category": "Fern Club Suite (Valley View)",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "20500",
                "wd_capai_eb": "1500",
                "wd_capai_cweb": "1500",
                "wd_mapai": "23500",
                "wd_mapai_eb": "2350",
                "wd_mapai_cweb": "2350",
                "wd_apai": "",
                "wd_apai_eb": "",
                "wd_apai_cweb": "",
                "we_capai": "20500",
                "we_capai_eb": "1500",
                "we_capai_cweb": "1500",
                "we_mapai": "23500",
                "we_mapai_eb": "2350",
                "we_mapai_cweb": "2350",
                "we_apai": "",
                "we_apai_eb": "",
                "we_apai_cweb": "",
                "remarks": ""
            },
            {
                "room_category": "Presidential Suite (4 Pax)",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "34600",
                "wd_capai_eb": "1500",
                "wd_capai_cweb": "1500",
                "wd_mapai": "37700",
                "wd_mapai_eb": "2350",
                "wd_mapai_cweb": "2350",
                "wd_apai": "",
                "wd_apai_eb": "",
                "wd_apai_cweb": "",
                "we_capai": "34600",
                "we_capai_eb": "1500",
                "we_capai_cweb": "1500",
                "we_mapai": "37700",
                "we_mapai_eb": "2350",
                "we_mapai_cweb": "2350",
                "we_apai": "",
                "we_apai_eb": "",
                "we_apai_cweb": "",
                "remarks": ""
            }
        ]
    },
    {
        "id": "htl_6ab66062b4188",
        "name": "Paatlidun Safari Lodge",
        "location": "Jim Corbett",
        "categories": [
            {
                "room_category": "Superior Room with Balcony Non-View",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "7200",
                "wd_capai_eb": "4500",
                "wd_capai_cweb": "3000",
                "wd_mapai": "9700",
                "wd_mapai_eb": "5500",
                "wd_mapai_cweb": "4000",
                "wd_apai": "13200",
                "wd_apai_eb": "6500",
                "wd_apai_cweb": "5000",
                "we_capai": "7200",
                "we_capai_eb": "4500",
                "we_capai_cweb": "3000",
                "we_mapai": "9700",
                "we_mapai_eb": "5500",
                "we_mapai_cweb": "4000",
                "we_apai": "13200",
                "we_apai_eb": "6500",
                "we_apai_cweb": "5000",
                "remarks": ""
            },
            {
                "room_category": "Superior Hill View with Balcony",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "7200",
                "wd_capai_eb": "4500",
                "wd_capai_cweb": "3000",
                "wd_mapai": "9700",
                "wd_mapai_eb": "5500",
                "wd_mapai_cweb": "4000",
                "wd_apai": "14200",
                "wd_apai_eb": "6500",
                "wd_apai_cweb": "5000",
                "we_capai": "7200",
                "we_capai_eb": "4500",
                "we_capai_cweb": "3000",
                "we_mapai": "9700",
                "we_mapai_eb": "5500",
                "we_mapai_cweb": "4000",
                "we_apai": "14200",
                "we_apai_eb": "6500",
                "we_apai_cweb": "5000",
                "remarks": ""
            },
            {
                "room_category": "Superior Jungle View with Balcony",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "8700",
                "wd_capai_eb": "4500",
                "wd_capai_cweb": "3000",
                "wd_mapai": "11700",
                "wd_mapai_eb": "5500",
                "wd_mapai_cweb": "4000",
                "wd_apai": "14700",
                "wd_apai_eb": "6500",
                "wd_apai_cweb": "5000",
                "we_capai": "8700",
                "we_capai_eb": "4500",
                "we_capai_cweb": "3000",
                "we_mapai": "11700",
                "we_mapai_eb": "5500",
                "we_mapai_cweb": "4000",
                "we_apai": "14700",
                "we_apai_eb": "6500",
                "we_apai_cweb": "5000",
                "remarks": ""
            },
            {
                "room_category": "Jungle Cottage with Jacuzzi",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "13700",
                "wd_capai_eb": "4500",
                "wd_capai_cweb": "3000",
                "wd_mapai": "16200",
                "wd_mapai_eb": "5500",
                "wd_mapai_cweb": "4000",
                "wd_apai": "19700",
                "wd_apai_eb": "6500",
                "wd_apai_cweb": "5000",
                "we_capai": "13700",
                "we_capai_eb": "4500",
                "we_capai_cweb": "3000",
                "we_mapai": "16200",
                "we_mapai_eb": "5500",
                "we_mapai_cweb": "4000",
                "we_apai": "19700",
                "we_apai_eb": "6500",
                "we_apai_cweb": "5000",
                "remarks": ""
            },
            {
                "room_category": "Mud Villa with Jacuzzi",
                "valid_from": "2027-10-01",
                "valid_to": "2027-03-01",
                "wd_capai": "15200",
                "wd_capai_eb": "4500",
                "wd_capai_cweb": "3000",
                "wd_mapai": "18200",
                "wd_mapai_eb": "5500",
                "wd_mapai_cweb": "4000",
                "wd_apai": "21700",
                "wd_apai_eb": "6500",
                "wd_apai_cweb": "5000",
                "we_capai": "15200",
                "we_capai_eb": "4500",
                "we_capai_cweb": "3000",
                "we_mapai": "18200",
                "we_mapai_eb": "5500",
                "we_mapai_cweb": "4000",
                "we_apai": "21700",
                "we_apai_eb": "6500",
                "we_apai_cweb": "5000",
                "remarks": ""
            },
            {
                "room_category": "Hill View Terrace Villa with Jacuzzi",
                "valid_from": "2027-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "16700",
                "wd_capai_eb": "4500",
                "wd_capai_cweb": "3000",
                "wd_mapai": "19700",
                "wd_mapai_eb": "5500",
                "wd_mapai_cweb": "4000",
                "wd_apai": "22700",
                "wd_apai_eb": "6500",
                "wd_apai_cweb": "5000",
                "we_capai": "16700",
                "we_capai_eb": "4500",
                "we_capai_cweb": "3000",
                "we_mapai": "19700",
                "we_mapai_eb": "5500",
                "we_mapai_cweb": "4000",
                "we_apai": "22700",
                "we_apai_eb": "6500",
                "we_apai_cweb": "5000",
                "remarks": ""
            },
            {
                "room_category": "Garden Courtyard Villa with Jacuzzi",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "17700",
                "wd_capai_eb": "4500",
                "wd_capai_cweb": "3000",
                "wd_mapai": "20700",
                "wd_mapai_eb": "5500",
                "wd_mapai_cweb": "4000",
                "wd_apai": "23700",
                "wd_apai_eb": "6500",
                "wd_apai_cweb": "5000",
                "we_capai": "17700",
                "we_capai_eb": "4500",
                "we_capai_cweb": "3000",
                "we_mapai": "20700",
                "we_mapai_eb": "5500",
                "we_mapai_cweb": "4000",
                "we_apai": "23700",
                "we_apai_eb": "6500",
                "we_apai_cweb": "5000",
                "remarks": ""
            },
            {
                "room_category": "Luxury Villa with Private Pool",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "18200",
                "wd_capai_eb": "4500",
                "wd_capai_cweb": "3000",
                "wd_mapai": "21200",
                "wd_mapai_eb": "5500",
                "wd_mapai_cweb": "4000",
                "wd_apai": "24200",
                "wd_apai_eb": "6500",
                "wd_apai_cweb": "5000",
                "we_capai": "18200",
                "we_capai_eb": "4500",
                "we_capai_cweb": "3000",
                "we_mapai": "21200",
                "we_mapai_eb": "5500",
                "we_mapai_cweb": "4000",
                "we_apai": "24200",
                "we_apai_eb": "6500",
                "we_apai_cweb": "5000",
                "remarks": ""
            },
            {
                "room_category": "Signature Pool Villa with Hanging Bed & Jacuzzi",
                "valid_from": "2026-01-01",
                "valid_to": "2027-03-31",
                "wd_capai": "19200",
                "wd_capai_eb": "4500",
                "wd_capai_cweb": "3000",
                "wd_mapai": "22200",
                "wd_mapai_eb": "5500",
                "wd_mapai_cweb": "4000",
                "wd_apai": "25200",
                "wd_apai_eb": "6500",
                "wd_apai_cweb": "5000",
                "we_capai": "19200",
                "we_capai_eb": "4500",
                "we_capai_cweb": "3000",
                "we_mapai": "22200",
                "we_mapai_eb": "5500",
                "we_mapai_cweb": "4000",
                "we_apai": "25200",
                "we_apai_eb": "6500",
                "we_apai_cweb": "5000",
                "remarks": ""
            },
            {
                "room_category": "Premium Pool Villa with Sky Bed",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "21200",
                "wd_capai_eb": "4500",
                "wd_capai_cweb": "3000",
                "wd_mapai": "24200",
                "wd_mapai_eb": "5500",
                "wd_mapai_cweb": "4000",
                "wd_apai": "27200",
                "wd_apai_eb": "6500",
                "wd_apai_cweb": "5000",
                "we_capai": "21200",
                "we_capai_eb": "4500",
                "we_capai_cweb": "3000",
                "we_mapai": "24200",
                "we_mapai_eb": "5500",
                "we_mapai_cweb": "4000",
                "we_apai": "27200",
                "we_apai_eb": "6500",
                "we_apai_cweb": "5000",
                "remarks": ""
            }
        ]
    },
    {
        "id": "htl_6abcf8caf3aad",
        "name": "ECKO Premier Vibrant",
        "location": "Udaipur",
        "categories": [
            {
                "room_category": "Classic Room",
                "valid_from": "2026-10-01",
                "valid_to": "2026-12-20",
                "wd_capai": "3700",
                "wd_capai_eb": "1000",
                "wd_capai_cweb": "900",
                "wd_mapai": "4700",
                "wd_mapai_eb": "1500",
                "wd_mapai_cweb": "1400",
                "wd_apai": "",
                "wd_apai_eb": "",
                "wd_apai_cweb": "",
                "we_capai": "3700",
                "we_capai_eb": "1000",
                "we_capai_cweb": "900",
                "we_mapai": "4700",
                "we_mapai_eb": "1500",
                "we_mapai_cweb": "1400",
                "we_apai": "",
                "we_apai_eb": "",
                "we_apai_cweb": "",
                "remarks": ""
            },
            {
                "room_category": "Superior Room",
                "valid_from": "2026-10-01",
                "valid_to": "2026-12-20",
                "wd_capai": "4200",
                "wd_capai_eb": "1000",
                "wd_capai_cweb": "900",
                "wd_mapai": "5200",
                "wd_mapai_eb": "1500",
                "wd_mapai_cweb": "1400",
                "wd_apai": "",
                "wd_apai_eb": "",
                "wd_apai_cweb": "",
                "we_capai": "4200",
                "we_capai_eb": "1000",
                "we_capai_cweb": "900",
                "we_mapai": "5200",
                "we_mapai_eb": "1500",
                "we_mapai_cweb": "1400",
                "we_apai": "",
                "we_apai_eb": "",
                "we_apai_cweb": "",
                "remarks": ""
            },
            {
                "room_category": "Premium Room",
                "valid_from": "2026-10-01",
                "valid_to": "2026-12-20",
                "wd_capai": "4700",
                "wd_capai_eb": "1000",
                "wd_capai_cweb": "900",
                "wd_mapai": "5700",
                "wd_mapai_eb": "1500",
                "wd_mapai_cweb": "1400",
                "wd_apai": "",
                "wd_apai_eb": "",
                "wd_apai_cweb": "",
                "we_capai": "4700",
                "we_capai_eb": "1000",
                "we_capai_cweb": "900",
                "we_mapai": "5700",
                "we_mapai_eb": "1500",
                "we_mapai_cweb": "1400",
                "we_apai": "",
                "we_apai_eb": "",
                "we_apai_cweb": "",
                "remarks": ""
            }
        ]
    },
    {
        "id": "htl_6abcfa54663a9",
        "name": "ECKO Kasang Regency Hill Resort",
        "location": "Lansdowne",
        "categories": [
            {
                "room_category": "Deluxe with Balcony",
                "valid_from": "2026-10-01",
                "valid_to": "2026-12-20",
                "wd_capai": "3700",
                "wd_capai_eb": "1000",
                "wd_capai_cweb": "900",
                "wd_mapai": "4700",
                "wd_mapai_eb": "1500",
                "wd_mapai_cweb": "1400",
                "wd_apai": "",
                "wd_apai_eb": "",
                "wd_apai_cweb": "",
                "we_capai": "3700",
                "we_capai_eb": "1000",
                "we_capai_cweb": "900",
                "we_mapai": "4700",
                "we_mapai_eb": "1500",
                "we_mapai_cweb": "1400",
                "we_apai": "",
                "we_apai_eb": "",
                "we_apai_cweb": "",
                "remarks": ""
            },
            {
                "room_category": "Superior Deluxe Room",
                "valid_from": "2026-10-01",
                "valid_to": "2026-12-20",
                "wd_capai": "4200",
                "wd_capai_eb": "1000",
                "wd_capai_cweb": "900",
                "wd_mapai": "5200",
                "wd_mapai_eb": "1500",
                "wd_mapai_cweb": "1400",
                "wd_apai": "",
                "wd_apai_eb": "",
                "wd_apai_cweb": "",
                "we_capai": "4200",
                "we_capai_eb": "1000",
                "we_capai_cweb": "900",
                "we_mapai": "5200",
                "we_mapai_eb": "1500",
                "we_mapai_cweb": "1400",
                "we_apai": "",
                "we_apai_eb": "",
                "we_apai_cweb": "",
                "remarks": ""
            }
        ]
    },
    {
        "id": "htl_6abcfc1316a12",
        "name": "ECKO City Centre",
        "location": "Rishikesh",
        "categories": [
            {
                "room_category": "Deluxe Room",
                "valid_from": "2026-10-01",
                "valid_to": "2026-12-20",
                "wd_capai": "3700",
                "wd_capai_eb": "1000",
                "wd_capai_cweb": "900",
                "wd_mapai": "4700",
                "wd_mapai_eb": "1500",
                "wd_mapai_cweb": "1400",
                "wd_apai": "",
                "wd_apai_eb": "",
                "wd_apai_cweb": "",
                "we_capai": "3700",
                "we_capai_eb": "1000",
                "we_capai_cweb": "900",
                "we_mapai": "4700",
                "we_mapai_eb": "1500",
                "we_mapai_cweb": "1400",
                "we_apai": "",
                "we_apai_eb": "",
                "we_apai_cweb": "",
                "remarks": ""
            },
            {
                "room_category": "Executive Room",
                "valid_from": "2026-10-01",
                "valid_to": "2026-12-20",
                "wd_capai": "4200",
                "wd_capai_eb": "1000",
                "wd_capai_cweb": "900",
                "wd_mapai": "5200",
                "wd_mapai_eb": "1500",
                "wd_mapai_cweb": "1400",
                "wd_apai": "",
                "wd_apai_eb": "",
                "wd_apai_cweb": "",
                "we_capai": "4200",
                "we_capai_eb": "1000",
                "we_capai_cweb": "900",
                "we_mapai": "5200",
                "we_mapai_eb": "1500",
                "we_mapai_cweb": "1400",
                "we_apai": "",
                "we_apai_eb": "",
                "we_apai_cweb": "",
                "remarks": ""
            },
            {
                "room_category": "Presidential Suite",
                "valid_from": "2026-10-01",
                "valid_to": "2026-12-20",
                "wd_capai": "7700",
                "wd_capai_eb": "1000",
                "wd_capai_cweb": "900",
                "wd_mapai": "8700",
                "wd_mapai_eb": "1500",
                "wd_mapai_cweb": "1400",
                "wd_apai": "",
                "wd_apai_eb": "",
                "wd_apai_cweb": "",
                "we_capai": "7700",
                "we_capai_eb": "1000",
                "we_capai_cweb": "900",
                "we_mapai": "8700",
                "we_mapai_eb": "1500",
                "we_mapai_cweb": "1400",
                "we_apai": "",
                "we_apai_eb": "",
                "we_apai_cweb": "",
                "remarks": ""
            }
        ]
    },
    {
        "id": "htl_6abcfe854c70e",
        "name": "ECKO Tapovan By The Rishikesh",
        "location": "Rishikesh",
        "categories": [
            {
                "room_category": "Deluxe with Balcony",
                "valid_from": "2026-10-01",
                "valid_to": "2027-12-20",
                "wd_capai": "4200",
                "wd_capai_eb": "1000",
                "wd_capai_cweb": "900",
                "wd_mapai": "4700",
                "wd_mapai_eb": "1500",
                "wd_mapai_cweb": "1400",
                "wd_apai": "",
                "wd_apai_eb": "",
                "wd_apai_cweb": "",
                "we_capai": "4200",
                "we_capai_eb": "1000",
                "we_capai_cweb": "900",
                "we_mapai": "4700",
                "we_mapai_eb": "1500",
                "we_mapai_cweb": "1400",
                "we_apai": "",
                "we_apai_eb": "",
                "we_apai_cweb": "",
                "remarks": ""
            },
            {
                "room_category": "Executive with Balcony",
                "valid_from": "2026-10-01",
                "valid_to": "2026-12-20",
                "wd_capai": "4700",
                "wd_capai_eb": "1000",
                "wd_capai_cweb": "900",
                "wd_mapai": "5700",
                "wd_mapai_eb": "1500",
                "wd_mapai_cweb": "1400",
                "wd_apai": "",
                "wd_apai_eb": "",
                "wd_apai_cweb": "",
                "we_capai": "4700",
                "we_capai_eb": "1000",
                "we_capai_cweb": "900",
                "we_mapai": "5700",
                "we_mapai_eb": "1500",
                "we_mapai_cweb": "1400",
                "we_apai": "",
                "we_apai_eb": "",
                "we_apai_cweb": "",
                "remarks": ""
            },
            {
                "room_category": "Luxury Suite with Balcony Ganga View",
                "valid_from": "2026-10-01",
                "valid_to": "2026-12-20",
                "wd_capai": "7700",
                "wd_capai_eb": "1000",
                "wd_capai_cweb": "900",
                "wd_mapai": "8700",
                "wd_mapai_eb": "1500",
                "wd_mapai_cweb": "1400",
                "wd_apai": "",
                "wd_apai_eb": "",
                "wd_apai_cweb": "",
                "we_capai": "7700",
                "we_capai_eb": "1000",
                "we_capai_cweb": "900",
                "we_mapai": "8700",
                "we_mapai_eb": "1500",
                "we_mapai_cweb": "1400",
                "we_apai": "",
                "we_apai_eb": "",
                "we_apai_cweb": "",
                "remarks": ""
            }
        ]
    },
    {
        "id": "htl_6abd013842445",
        "name": "ECKO Antarman on The Ganges",
        "location": "Haridwar",
        "categories": [
            {
                "room_category": "Deluxe Ganga Facing",
                "valid_from": "2026-10-01",
                "valid_to": "2026-12-20",
                "wd_capai": "4400",
                "wd_capai_eb": "1000",
                "wd_capai_cweb": "900",
                "wd_mapai": "5400",
                "wd_mapai_eb": "1500",
                "wd_mapai_cweb": "1400",
                "wd_apai": "",
                "wd_apai_eb": "",
                "wd_apai_cweb": "",
                "we_capai": "4400",
                "we_capai_eb": "1000",
                "we_capai_cweb": "900",
                "we_mapai": "5400",
                "we_mapai_eb": "1500",
                "we_mapai_cweb": "1400",
                "we_apai": "",
                "we_apai_eb": "",
                "we_apai_cweb": "",
                "remarks": ""
            },
            {
                "room_category": "Executive with Balcony",
                "valid_from": "2026-10-01",
                "valid_to": "2026-12-20",
                "wd_capai": "5100",
                "wd_capai_eb": "1000",
                "wd_capai_cweb": "900",
                "wd_mapai": "6100",
                "wd_mapai_eb": "1500",
                "wd_mapai_cweb": "1400",
                "wd_apai": "",
                "wd_apai_eb": "",
                "wd_apai_cweb": "",
                "we_capai": "5100",
                "we_capai_eb": "1000",
                "we_capai_cweb": "900",
                "we_mapai": "6100",
                "we_mapai_eb": "1500",
                "we_mapai_cweb": "1400",
                "we_apai": "",
                "we_apai_eb": "",
                "we_apai_cweb": "",
                "remarks": ""
            },
            {
                "room_category": "Family Suite on Ghat",
                "valid_from": "2026-10-01",
                "valid_to": "2026-12-20",
                "wd_capai": "10200",
                "wd_capai_eb": "1000",
                "wd_capai_cweb": "900",
                "wd_mapai": "12200",
                "wd_mapai_eb": "1500",
                "wd_mapai_cweb": "1400",
                "wd_apai": "",
                "wd_apai_eb": "",
                "wd_apai_cweb": "",
                "we_capai": "10200",
                "we_capai_eb": "1000",
                "we_capai_cweb": "900",
                "we_mapai": "12200",
                "we_mapai_eb": "1500",
                "we_mapai_cweb": "1400",
                "we_apai": "",
                "we_apai_eb": "",
                "we_apai_cweb": "",
                "remarks": ""
            }
        ]
    },
    {
        "id": "htl_6abe2e73ae2cd",
        "name": "The Oasis A Member of Radisson",
        "location": "Mussoorie",
        "categories": [
            {
                "room_category": "Standard Room",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "10100",
                "wd_capai_eb": "3000",
                "wd_capai_cweb": "2000",
                "wd_mapai": "12600",
                "wd_mapai_eb": "4000",
                "wd_mapai_cweb": "3000",
                "wd_apai": "",
                "wd_apai_eb": "",
                "wd_apai_cweb": "",
                "we_capai": "10100",
                "we_capai_eb": "3000",
                "we_capai_cweb": "2000",
                "we_mapai": "12600",
                "we_mapai_eb": "4000",
                "we_mapai_cweb": "3000",
                "we_apai": "",
                "we_apai_eb": "",
                "we_apai_cweb": "",
                "remarks": ""
            },
            {
                "room_category": "Superior Room",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "11100",
                "wd_capai_eb": "3000",
                "wd_capai_cweb": "2000",
                "wd_mapai": "13600",
                "wd_mapai_eb": "4000",
                "wd_mapai_cweb": "3000",
                "wd_apai": "",
                "wd_apai_eb": "",
                "wd_apai_cweb": "",
                "we_capai": "11100",
                "we_capai_eb": "3000",
                "we_capai_cweb": "2000",
                "we_mapai": "13600",
                "we_mapai_eb": "4000",
                "we_mapai_cweb": "3000",
                "we_apai": "",
                "we_apai_eb": "",
                "we_apai_cweb": "",
                "remarks": ""
            },
            {
                "room_category": "Deluxe Room with Balcony",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "12100",
                "wd_capai_eb": "3000",
                "wd_capai_cweb": "2000",
                "wd_mapai": "14600",
                "wd_mapai_eb": "4000",
                "wd_mapai_cweb": "3000",
                "wd_apai": "",
                "wd_apai_eb": "",
                "wd_apai_cweb": "",
                "we_capai": "12100",
                "we_capai_eb": "3000",
                "we_capai_cweb": "2000",
                "we_mapai": "14600",
                "we_mapai_eb": "4000",
                "we_mapai_cweb": "3000",
                "we_apai": "",
                "we_apai_eb": "",
                "we_apai_cweb": "",
                "remarks": ""
            }
        ]
    },
    {
        "id": "htl_6ac2396e0cce5",
        "name": "De Coracao",
        "location": "Rishikesh",
        "categories": [
            {
                "room_category": "Deluxe Room",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "5300",
                "wd_capai_eb": "1500",
                "wd_capai_cweb": "1000",
                "wd_mapai": "6300",
                "wd_mapai_eb": "2000",
                "wd_mapai_cweb": "1500",
                "wd_apai": "7300",
                "wd_apai_eb": "2500",
                "wd_apai_cweb": "2000",
                "we_capai": "5300",
                "we_capai_eb": "1500",
                "we_capai_cweb": "1000",
                "we_mapai": "6300",
                "we_mapai_eb": "2000",
                "we_mapai_cweb": "1500",
                "we_apai": "7300",
                "we_apai_eb": "2500",
                "we_apai_cweb": "2000",
                "remarks": ""
            },
            {
                "room_category": "Super Deluxe",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "6300",
                "wd_capai_eb": "1500",
                "wd_capai_cweb": "1000",
                "wd_mapai": "7300",
                "wd_mapai_eb": "2000",
                "wd_mapai_cweb": "1500",
                "wd_apai": "8300",
                "wd_apai_eb": "2500",
                "wd_apai_cweb": "2000",
                "we_capai": "6300",
                "we_capai_eb": "1500",
                "we_capai_cweb": "1000",
                "we_mapai": "7300",
                "we_mapai_eb": "2000",
                "we_mapai_cweb": "1500",
                "we_apai": "8300",
                "we_apai_eb": "2500",
                "we_apai_cweb": "2000",
                "remarks": ""
            },
            {
                "room_category": "Premium Suite",
                "valid_from": "2026-10-01",
                "valid_to": "2026-03-31",
                "wd_capai": "7300",
                "wd_capai_eb": "1500",
                "wd_capai_cweb": "1000",
                "wd_mapai": "8300",
                "wd_mapai_eb": "2000",
                "wd_mapai_cweb": "1500",
                "wd_apai": "9300",
                "wd_apai_eb": "2500",
                "wd_apai_cweb": "2000",
                "we_capai": "7300",
                "we_capai_eb": "1500",
                "we_capai_cweb": "1000",
                "we_mapai": "8300",
                "we_mapai_eb": "2000",
                "we_mapai_cweb": "1500",
                "we_apai": "9300",
                "we_apai_eb": "2500",
                "we_apai_cweb": "2000",
                "remarks": ""
            },
            {
                "room_category": "Suite",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "8300",
                "wd_capai_eb": "1500",
                "wd_capai_cweb": "1000",
                "wd_mapai": "9300",
                "wd_mapai_eb": "2000",
                "wd_mapai_cweb": "1500",
                "wd_apai": "10300",
                "wd_apai_eb": "2500",
                "wd_apai_cweb": "2000",
                "we_capai": "8300",
                "we_capai_eb": "1500",
                "we_capai_cweb": "1000",
                "we_mapai": "9300",
                "we_mapai_eb": "2000",
                "we_mapai_cweb": "1500",
                "we_apai": "10300",
                "we_apai_eb": "2500",
                "we_apai_cweb": "2000",
                "remarks": ""
            },
            {
                "room_category": "Ganga Suite",
                "valid_from": "2026-10-01",
                "valid_to": "2027-03-31",
                "wd_capai": "9300",
                "wd_capai_eb": "1500",
                "wd_capai_cweb": "1000",
                "wd_mapai": "10300",
                "wd_mapai_eb": "2000",
                "wd_mapai_cweb": "1500",
                "wd_apai": "11300",
                "wd_apai_eb": "2500",
                "wd_apai_cweb": "2000",
                "we_capai": "9300",
                "we_capai_eb": "1500",
                "we_capai_cweb": "1000",
                "we_mapai": "10300",
                "we_mapai_eb": "2000",
                "we_mapai_cweb": "1500",
                "we_apai": "11300",
                "we_apai_eb": "2500",
                "we_apai_cweb": "2000",
                "remarks": ""
            }
        ]
    }
]
JSON;
    file_put_contents($data_file, $default_json);
}

// Determine routing
$view = isset($_GET['view']) ? $_GET['view'] : 'viewer';

// --- 2. ADMIN DASHBOARD BLOCK (Formerly uk2.php) ---
if ($view === 'admin') {
    // Load current data
    $raw_data = file_get_contents($data_file);
    $hotel_data = $raw_data ? json_decode($raw_data, true) : [];
    if (!is_array($hotel_data)) {
        $hotel_data = [];
    }

    // PATCH: Ensure all existing hotels have an ID so deletion works perfectly
    $data_updated = false;
    foreach ($hotel_data as &$hotel) {
        if (empty($hotel['id'])) {
            $hotel['id'] = uniqid('htl_');
            $data_updated = true;
        }
    }
    unset($hotel); 
    if ($data_updated) {
        file_put_contents($data_file, json_encode(array_values($hotel_data), JSON_PRETTY_PRINT));
    }

    $message = '';
    $messageType = '';

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'save_rate') {
            $edit_hotel_id = $_POST['edit_hotel_id'] ?? '';
            $edit_cat_index = $_POST['edit_cat_index'] ?? '';
            
            $hotel_name = trim($_POST['hotel_name'] ?? '');
            $location = trim($_POST['location'] ?? '');
            
            // Build the category array
            $new_category = [
                'room_category' => trim($_POST['room_category'] ?? ''),
                'valid_from' => trim($_POST['valid_from'] ?? ''),
                'valid_to' => trim($_POST['valid_to'] ?? ''),
                'wd_capai' => trim($_POST['wd_capai'] ?? ''),
                'wd_capai_eb' => trim($_POST['wd_capai_eb'] ?? ''),
                'wd_capai_cweb' => trim($_POST['wd_capai_cweb'] ?? ''),
                'wd_mapai' => trim($_POST['wd_mapai'] ?? ''),
                'wd_mapai_eb' => trim($_POST['wd_mapai_eb'] ?? ''),
                'wd_mapai_cweb' => trim($_POST['wd_mapai_cweb'] ?? ''),
                'wd_apai' => trim($_POST['wd_apai'] ?? ''),
                'wd_apai_eb' => trim($_POST['wd_apai_eb'] ?? ''),
                'wd_apai_cweb' => trim($_POST['wd_apai_cweb'] ?? ''),
                'we_capai' => trim($_POST['we_capai'] ?? ''),
                'we_capai_eb' => trim($_POST['we_capai_eb'] ?? ''),
                'we_capai_cweb' => trim($_POST['we_capai_cweb'] ?? ''),
                'we_mapai' => trim($_POST['we_mapai'] ?? ''),
                'we_mapai_eb' => trim($_POST['we_mapai_eb'] ?? ''),
                'we_mapai_cweb' => trim($_POST['we_mapai_cweb'] ?? ''),
                'we_apai' => trim($_POST['we_apai'] ?? ''),
                'we_apai_eb' => trim($_POST['we_apai_eb'] ?? ''),
                'we_apai_cweb' => trim($_POST['we_apai_cweb'] ?? ''),
                'remarks' => trim($_POST['remarks'] ?? '')
            ];

            if ($edit_hotel_id !== '' && $edit_cat_index !== '') {
                // Edit existing
                foreach ($hotel_data as &$hotel) {
                    if ((string)$hotel['id'] === (string)$edit_hotel_id) {
                        $hotel['name'] = $hotel_name;
                        $hotel['location'] = $location;
                        $hotel['categories'][(int)$edit_cat_index] = $new_category;
                        break;
                    }
                }
                $message = "Rate updated successfully!";
                $messageType = "success";
            } else {
                // Add new
                $found_hotel = false;
                foreach ($hotel_data as &$hotel) {
                    if (strtolower($hotel['name']) === strtolower($hotel_name) && strtolower($hotel['location']) === strtolower($location)) {
                        $hotel['categories'][] = $new_category;
                        $found_hotel = true;
                        break;
                    }
                }
                
                if (!$found_hotel) {
                    $new_hotel = [
                        'id' => uniqid('htl_'),
                        'name' => $hotel_name,
                        'location' => $location,
                        'categories' => [$new_category]
                    ];
                    $hotel_data[] = $new_hotel;
                }
                $message = "New rate added successfully!";
                $messageType = "success";
            }
            
            file_put_contents($data_file, json_encode(array_values($hotel_data), JSON_PRETTY_PRINT));
            header("Location: ?view=admin&msg=" . urlencode($message) . "&type=" . $messageType);
            exit;
        }

        if ($action === 'delete_hotel') {
            $delete_id = $_POST['delete_id'] ?? '';
            $hotel_data = array_filter($hotel_data, function($h) use ($delete_id) {
                return (string)$h['id'] !== (string)$delete_id;
            });
            file_put_contents($data_file, json_encode(array_values($hotel_data), JSON_PRETTY_PRINT));
            header("Location: ?view=admin&msg=" . urlencode("Hotel deleted entirely.") . "&type=success");
            exit;
        }

        if ($action === 'delete_category') {
            $delete_hotel_id = $_POST['delete_hotel_id'] ?? '';
            $delete_cat_index = $_POST['delete_cat_index'] ?? '';
            
            foreach ($hotel_data as &$hotel) {
                if ((string)$hotel['id'] === (string)$delete_hotel_id) {
                    array_splice($hotel['categories'], $delete_cat_index, 1);
                    break;
                }
            }
            // If hotel has no categories left, remove the hotel
            $hotel_data = array_filter($hotel_data, function($h) {
                return count($h['categories']) > 0;
            });
            
            file_put_contents($data_file, json_encode(array_values($hotel_data), JSON_PRETTY_PRINT));
            header("Location: ?view=admin&msg=" . urlencode("Room rate deleted.") . "&type=success");
            exit;
        }
    }

    // Display messages from redirect
    if (isset($_GET['msg'])) {
        $message = htmlspecialchars($_GET['msg']);
        $messageType = htmlspecialchars($_GET['type'] ?? 'info');
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Dashboard - Uttarakhand Ventures</title>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <style>
            :root {
                --bg: #fff6f0;
                --surface: #ffffff;
                --text-primary: #111827;
                --text-secondary: #4b5563;
                --text-tertiary: #9ca3af;
                --border: #e5e7eb;
                --table-border: #5CAE5D;
                --accent: #EB7F29;
                --accent-hover: #D16D1E;
                --danger: #ef4444;
                --danger-hover: #dc2626;
                --focus-ring: rgba(235, 127, 41, 0.15);
            }
            body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg); margin: 0; padding: 0; color: var(--text-primary); font-size: 16px; -webkit-font-smoothing: antialiased; }
            .navbar { background-color: var(--surface); border-bottom: 1px solid var(--border); padding: 16px 40px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 50; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); }
            .navbar-brand { display: flex; align-items: center; gap: 12px; font-size: 22px; font-weight: 800; color: var(--text-primary); letter-spacing: -0.025em; }
            .navbar-brand::before { content: ''; display: block; width: 8px; height: 24px; background: var(--accent); border-radius: 4px; }
            .header-actions { display: flex; gap: 12px; }
            .btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 20px; border-radius: 8px; font-weight: 600; font-size: 16px; cursor: pointer; text-decoration: none; border: 1px solid transparent; transition: all 0.2s ease; font-family: inherit; }
            .btn-outline { background: var(--surface); border-color: var(--border); color: var(--text-secondary); box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
            .btn-outline:hover { background: #f9fafb; color: var(--text-primary); }
            .btn-dark { background: #1f2937; color: #fff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
            .btn-dark:hover { background: #111827; }
            .btn-danger { background: #fee2e2; color: var(--danger); }
            .btn-danger:hover { background: #fecaca; }
            .btn-primary { background: var(--accent); color: #fff; box-shadow: 0 4px 6px -1px rgba(235, 127, 41, 0.2); }
            .btn-primary:hover { background: var(--accent-hover); transform: translateY(-1px); }
            .btn-sm { padding: 6px 14px; font-size: 14px; border-radius: 6px; }
            .btn-icon { padding: 6px; font-size: 16px; }
            .container { max-width: 1400px; margin: 40px auto; padding: 0 20px; display: flex; flex-direction: column; gap: 32px; }
            .panel { background: var(--surface); border-radius: 16px; border: 1px solid var(--border); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.025); overflow: hidden; }
            .panel-header { padding: 24px 32px; border-bottom: 1px solid var(--border); background: #fcfcfd; }
            .panel-title { font-size: 20px; font-weight: 700; color: var(--text-primary); margin: 0; }
            .panel-body { padding: 32px; }
            .section-label { font-size: 16px; font-weight: 700; color: var(--accent); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 20px; margin-top: 32px; display: flex; align-items: center; gap: 10px; }
            .section-label:first-child { margin-top: 0; }
            .section-label::after { content: ''; flex-grow: 1; height: 1px; background: var(--border); }
            .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; }
            .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 16px; }
            .form-group { display: flex; flex-direction: column; gap: 8px; }
            .form-group label { font-size: 15px; font-weight: 600; color: var(--text-secondary); }
            .form-group label span { font-weight: 400; color: var(--text-tertiary); margin-left: 4px; font-style: italic; }
            .input-control { padding: 14px 18px; border: 1px solid var(--border); border-radius: 8px; font-family: inherit; font-size: 16px; color: var(--text-primary); background: #f9fafb; width: 100%; box-sizing: border-box; transition: all 0.2s ease; }
            .input-control:focus { background: var(--surface); outline: none; border-color: var(--accent); box-shadow: 0 0 0 4px var(--focus-ring); }
            .rate-block { background: #f8fafc; border: 1px solid var(--border); border-radius: 12px; padding: 20px; margin-bottom: 20px; }
            .rate-row { display: flex; align-items: center; gap: 24px; margin-bottom: 16px; }
            .rate-row:last-child { margin-bottom: 0; }
            .rate-label { width: 120px; font-size: 16px; font-weight: 700; color: var(--text-primary); }
            .form-footer { margin-top: 32px; padding-top: 24px; border-top: 1px solid var(--border); display: flex; gap: 12px; }
            .search-container { padding: 20px 32px; border-bottom: 1px solid #E5955B; background: #F2A971; }
            .search-container input { width: 100%; padding: 16px 20px 16px 48px; border: 1px solid var(--border); border-radius: 8px; font-size: 16px; font-family: inherit; background-color: var(--surface); background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' fill='%239ca3af' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: 16px center; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: all 0.2s; box-sizing: border-box; }
            .search-container input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 4px var(--focus-ring); }
            .table-responsive { overflow-x: auto; width: 100%; }
            table { width: 100%; border-collapse: collapse; text-align: left; border: 2px solid var(--table-border); }
            th, td { padding: 18px 20px; border: 1px solid var(--table-border) !important; font-size: 15px; }
            thead tr:first-child th { background: #FCAB73; color: #000; font-weight: 800; font-size: 13px; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; } 
            thead tr:first-child th:nth-child(5) { background-color: #498BF4; }
            thead tr:first-child th:nth-child(6) { background-color: #3FD9B9; }
            .sub-header th { font-size: 12px; color: #000; padding: 12px 20px; border-top: 1px solid var(--table-border); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; }
            .sub-header th:nth-child(1), .sub-header th:nth-child(2), .sub-header th:nth-child(3) { background: #74A4F6; }
            .sub-header th:nth-child(4), .sub-header th:nth-child(5), .sub-header th:nth-child(6) { background: #5BE0C6; }
            .main-row { background: var(--surface); cursor: pointer; transition: background 0.15s; }
            .main-row td { font-weight: 700; color: var(--text-primary); }
            .main-row td:nth-child(1), .main-row td:nth-child(2) { background: #FC954F; color: #fff; }
            .main-row .hotel-meta { font-size: 14px; color: #ffe5d4; font-weight: 500; margin-top: 4px; } 
            .main-row:hover td:nth-child(1), .main-row:hover td:nth-child(2) { background: #fca060; }
            .sub-row { background: #fcfcfd; }
            .sub-row td { color: var(--text-secondary); }
            .sub-row .cat-name { font-weight: 600; color: var(--text-primary); }
            .actions-cell { display: flex; gap: 8px; align-items: center; }
            .toggle-icon { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; background: rgba(255,255,255,0.2); color: #fff; border-radius: 6px; font-size: 18px; font-weight: bold; }
            .main-row:hover .toggle-icon { background: rgba(255,255,255,0.4); }
            .badge-validity { background: #dbeafe; color: #1e3a8a; padding: 4px 8px; border-radius: 4px; font-size: 13px; font-weight: 600; white-space: nowrap; } 
            .text-truncate { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        </style>
    </head>
    <body>

    <?php if($message): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                toast: true, position: 'top-end', showConfirmButton: false, timer: 3000,
                icon: '<?php echo $messageType; ?>', title: '<?php echo $message; ?>',
                customClass: { popup: 'swal2-popup' }
            });
        });
    </script>
    <?php endif; ?>

    <!-- Top Navigation -->
    <header class="navbar">
        <div class="navbar-brand">Uttarakhand Ventures Workspace</div>
        <div class="header-actions">
            <a href="?" class="btn btn-outline">← View Live Rates</a>
            <button class="btn btn-danger" onclick="window.location.href='?';">Logout</button>
        </div>
    </header>

    <div class="container">
        <!-- Form Section -->
        <div class="panel">
            <div class="panel-header">
                <h2 class="panel-title">Add / Update Hotel Rates</h2>
            </div>
            <div class="panel-body">
                <form method="POST" id="hotelForm">
                    <input type="hidden" name="action" id="formAction" value="save_rate">
                    <input type="hidden" name="edit_hotel_id" id="edit_hotel_id" value="">
                    <input type="hidden" name="edit_cat_index" id="edit_cat_index" value="">

                    <div class="section-label">General Information</div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Hotel Name</label>
                            <input type="text" class="input-control" name="hotel_name" id="hotel_name" placeholder="e.g., Grand Hyatt" required>
                        </div>
                        <div class="form-group">
                            <label>Location</label>
                            <input type="text" class="input-control" name="location" id="location" placeholder="e.g., Delhi" required>
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Room Category</label>
                            <input type="text" class="input-control" name="room_category" id="room_category" placeholder="e.g., Premium Deluxe" required>
                        </div>
                    </div>

                    <div class="section-label">Validity Period</div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Valid From <span>(Leave blank for Always Valid)</span></label>
                            <input type="date" class="input-control" name="valid_from" id="valid_from">
                        </div>
                        <div class="form-group">
                            <label>Valid To <span>(Leave blank for Always Valid)</span></label>
                            <input type="date" class="input-control" name="valid_to" id="valid_to">
                        </div>
                    </div>

                    <div class="section-label">Pricing Configurations</div>
                    <div class="grid-2" style="gap: 32px;">
                        <!-- Weekday -->
                        <div class="rate-block">
                            <h4 style="margin:0 0 16px 0; color:var(--text-primary); font-size:16px;">Weekday Rates</h4>
                            <div class="rate-row">
                                <div class="rate-label">CPAI Rates</div>
                                <div class="grid-3" style="width: 100%; margin: 0;">
                                    <input type="number" class="input-control" name="wd_capai" id="wd_capai" placeholder="₹ Base">
                                    <input type="text" class="input-control" name="wd_capai_eb" id="wd_capai_eb" placeholder="EB (₹ / %)">
                                    <input type="text" class="input-control" name="wd_capai_cweb" id="wd_capai_cweb" placeholder="CWEB (₹ / %)">
                                </div>
                            </div>
                            <div class="rate-row">
                                <div class="rate-label">MAPAI Rates</div>
                                <div class="grid-3" style="width: 100%; margin: 0;">
                                    <input type="number" class="input-control" name="wd_mapai" id="wd_mapai" placeholder="₹ Base">
                                    <input type="text" class="input-control" name="wd_mapai_eb" id="wd_mapai_eb" placeholder="EB (₹ / %)">
                                    <input type="text" class="input-control" name="wd_mapai_cweb" id="wd_mapai_cweb" placeholder="CWEB (₹ / %)">
                                </div>
                            </div>
                            <div class="rate-row">
                                <div class="rate-label">APAI Rates</div>
                                <div class="grid-3" style="width: 100%; margin: 0;">
                                    <input type="number" class="input-control" name="wd_apai" id="wd_apai" placeholder="₹ Base">
                                    <input type="text" class="input-control" name="wd_apai_eb" id="wd_apai_eb" placeholder="EB (₹ / %)">
                                    <input type="text" class="input-control" name="wd_apai_cweb" id="wd_apai_cweb" placeholder="CWEB (₹ / %)">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Weekend -->
                        <div class="rate-block">
                            <h4 style="margin:0 0 16px 0; color:var(--text-primary); font-size:16px;">Weekend Rates</h4>
                            <div class="rate-row">
                                <div class="rate-label">CPAI Rates</div>
                                <div class="grid-3" style="width: 100%; margin: 0;">
                                    <input type="number" class="input-control" name="we_capai" id="we_capai" placeholder="₹ Base">
                                    <input type="text" class="input-control" name="we_capai_eb" id="we_capai_eb" placeholder="EB (₹ / %)">
                                    <input type="text" class="input-control" name="we_capai_cweb" id="we_capai_cweb" placeholder="CWEB (₹ / %)">
                                </div>
                            </div>
                            <div class="rate-row">
                                <div class="rate-label">MAPAI Rates</div>
                                <div class="grid-3" style="width: 100%; margin: 0;">
                                    <input type="number" class="input-control" name="we_mapai" id="we_mapai" placeholder="₹ Base">
                                    <input type="text" class="input-control" name="we_mapai_eb" id="we_mapai_eb" placeholder="EB (₹ / %)">
                                    <input type="text" class="input-control" name="we_mapai_cweb" id="we_mapai_cweb" placeholder="CWEB (₹ / %)">
                                </div>
                            </div>
                            <div class="rate-row">
                                <div class="rate-label">APAI Rates</div>
                                <div class="grid-3" style="width: 100%; margin: 0;">
                                    <input type="number" class="input-control" name="we_apai" id="we_apai" placeholder="₹ Base">
                                    <input type="text" class="input-control" name="we_apai_eb" id="we_apai_eb" placeholder="EB (₹ / %)">
                                    <input type="text" class="input-control" name="we_apai_cweb" id="we_apai_cweb" placeholder="CWEB (₹ / %)">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="section-label">Additional Details</div>
                    <div class="form-group">
                        <label>Remarks / Inclusions</label>
                        <textarea class="input-control" name="remarks" id="remarks" rows="2" placeholder="e.g., Breakfast Included..."></textarea>
                    </div>

                    <div class="form-footer">
                        <button type="submit" class="btn btn-primary" id="submitBtn" style="padding: 14px 36px; font-size:17px;">SAVE HOTEL DETAILS</button>
                        <button type="button" class="btn btn-outline" style="display:none;" id="cancelEditBtn" onclick="resetForm()">Cancel Edit</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Table Section -->
        <div class="panel">
            <div class="panel-header" style="display:flex; justify-content:space-between; align-items:center;">
                <h2 class="panel-title">Manage Database</h2>
                <span style="font-size: 15px; color: var(--text-tertiary); font-weight: 500;">Click on a hotel row to view room categories</span>
            </div>
            
            <div class="search-container">
                <input type="text" id="tableSearch" placeholder="Search by Hotel Name, Location, or Category...">
            </div>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th rowspan="2" style="width: 50px;"></th>
                            <th rowspan="2">Hotel Info</th>
                            <th rowspan="2">Room Category</th>
                            <th rowspan="2">Validity</th>
                            <th colspan="3" style="text-align: center; border-bottom: 1px solid var(--border); border-left: 1px solid var(--border);">Weekdays</th>
                            <th colspan="3" style="text-align: center; border-bottom: 1px solid var(--border); border-left: 1px solid var(--border);">Weekend</th>
                            <th rowspan="2" style="border-left: 1px solid var(--border);">Remarks</th>
                            <th rowspan="2" style="text-align:right;">Actions</th>
                        </tr>
                        <tr class="sub-header">
                            <th style="border-left: 1px solid var(--border);">CPAI</th><th>MAPAI</th><th>APAI</th>
                            <th style="border-left: 1px solid var(--border);">CPAI</th><th>MAPAI</th><th>APAI</th>
                        </tr>
                    </thead>
                    <tbody id="hotelTableBody">
                        <?php if (empty($hotel_data)): ?>
                            <tr><td colspan="12" style="text-align: center; padding: 60px; color: var(--text-tertiary); font-size:16px;">No records found. Start adding hotels above.</td></tr>
                        <?php else: ?>
                            <?php foreach ($hotel_data as $hotel): ?>
                                <tr class="main-row search-row" onclick="toggleRows('cat_<?php echo $hotel['id']; ?>')">
                                    <td style="text-align: center;"><span class="toggle-icon">+</span></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($hotel['name']); ?></div>
                                        <div class="hotel-meta">📍 <?php echo htmlspecialchars($hotel['location']); ?></div>
                                    </td>
                                    <td>-</td><td>-</td>
                                    <td style="border-left: 1px solid var(--border);">-</td><td>-</td><td>-</td>
                                    <td style="border-left: 1px solid var(--border);">-</td><td>-</td><td>-</td>
                                    <td style="border-left: 1px solid var(--border);">-</td>
                                    <td style="text-align:right;" onclick="event.stopPropagation();">
                                        <form method="POST" style="display:inline;" onsubmit="event.stopPropagation(); return confirm('Warning: Are you sure you want to delete this entire hotel and all its rates?');">
                                            <input type="hidden" name="action" value="delete_hotel">
                                            <input type="hidden" name="delete_id" value="<?php echo $hotel['id']; ?>">
                                            <button type="submit" class="btn btn-outline btn-sm" onclick="event.stopPropagation();" style="color:var(--danger); border-color:#fecaca;">Delete Hotel</button>
                                        </form>
                                    </td>
                                </tr>
                                
                                <?php foreach ($hotel['categories'] as $index => $cat): ?>
                                    <tr class="sub-row search-row cat_<?php echo $hotel['id']; ?>" style="display:none;"
                                        data-hotel-id="<?php echo $hotel['id']; ?>"
                                        data-cat-index="<?php echo $index; ?>"
                                        data-json="<?php echo htmlspecialchars(json_encode($cat), ENT_QUOTES, 'UTF-8'); ?>"
                                        data-hname="<?php echo htmlspecialchars($hotel['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-hloc="<?php echo htmlspecialchars($hotel['location'], ENT_QUOTES, 'UTF-8'); ?>">
                                        
                                        <td style="text-align:right; color:var(--border);">↳</td>
                                        <td></td>
                                        <td class="cat-name"><?php echo htmlspecialchars($cat['room_category']); ?></td>
                                        <td>
                                            <span class="badge-validity">
                                            <?php 
                                                if($cat['valid_from'] && $cat['valid_to']) echo date('d/m/y', strtotime($cat['valid_from'])) . ' - ' . date('d/m/y', strtotime($cat['valid_to']));
                                                else echo "Always";
                                            ?>
                                            </span>
                                        </td>
                                        <td style="border-left: 1px dashed var(--border);"><?php echo htmlspecialchars($cat['wd_capai'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($cat['wd_mapai'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($cat['wd_apai'] ?? '-'); ?></td>
                                        <td style="border-left: 1px dashed var(--border);"><?php echo htmlspecialchars($cat['we_capai'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($cat['we_mapai'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($cat['we_apai'] ?? '-'); ?></td>
                                        <td class="text-truncate" style="border-left: 1px dashed var(--border);" title="<?php echo htmlspecialchars($cat['remarks'] ?? ''); ?>"><?php echo htmlspecialchars($cat['remarks'] ?? '-'); ?></td>
                                        <td class="actions-cell" style="justify-content:flex-end;">
                                            <button type="button" onclick="loadForEdit(this)" class="btn btn-dark btn-sm">Edit</button>
                                            <form method="POST" style="display:inline;" onsubmit="event.stopPropagation(); return confirm('Delete this room rate?');">
                                                <input type="hidden" name="action" value="delete_category">
                                                <input type="hidden" name="delete_hotel_id" value="<?php echo $hotel['id']; ?>">
                                                <input type="hidden" name="delete_cat_index" value="<?php echo $index; ?>">
                                                <button type="submit" class="btn btn-danger btn-icon" onclick="event.stopPropagation();" title="Delete Room Rate">×</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function toggleRows(className) {
            const rows = document.getElementsByClassName(className);
            for(let i=0; i<rows.length; i++) {
                rows[i].style.display = rows[i].style.display === 'none' ? 'table-row' : 'none';
            }
        }

        // Load data into form for editing
        function loadForEdit(btnElement) {
            const tr = btnElement.closest('.sub-row');
            const catData = JSON.parse(tr.getAttribute('data-json'));
            
            document.getElementById('edit_hotel_id').value = tr.getAttribute('data-hotel-id');
            document.getElementById('edit_cat_index').value = tr.getAttribute('data-cat-index');
            
            document.getElementById('hotel_name').value = tr.getAttribute('data-hname');
            document.getElementById('location').value = tr.getAttribute('data-hloc');
            
            document.getElementById('room_category').value = catData.room_category || '';
            document.getElementById('valid_from').value = catData.valid_from || '';
            document.getElementById('valid_to').value = catData.valid_to || '';
            
            const keys = [
                'wd_capai', 'wd_capai_eb', 'wd_capai_cweb', 'wd_mapai', 'wd_mapai_eb', 'wd_mapai_cweb', 'wd_apai', 'wd_apai_eb', 'wd_apai_cweb',
                'we_capai', 'we_capai_eb', 'we_capai_cweb', 'we_mapai', 'we_mapai_eb', 'we_mapai_cweb', 'we_apai', 'we_apai_eb', 'we_apai_cweb'
            ];
            
            keys.forEach(k => {
                if(document.getElementById(k)) document.getElementById(k).value = catData[k] || '';
            });
            
            document.getElementById('remarks').value = catData.remarks || '';
            
            document.getElementById('submitBtn').innerHTML = 'UPDATE HOTEL DETAILS';
            document.getElementById('cancelEditBtn').style.display = 'inline-flex';
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function resetForm() {
            document.getElementById('hotelForm').reset();
            document.getElementById('edit_hotel_id').value = '';
            document.getElementById('edit_cat_index').value = '';
            document.getElementById('submitBtn').innerHTML = 'SAVE HOTEL DETAILS';
            document.getElementById('cancelEditBtn').style.display = 'none';
        }

        // Search functionality
        document.getElementById('tableSearch').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('.search-row');
            
            rows.forEach(row => {
                let text = row.innerText.toLowerCase();
                if(text.includes(filter)) {
                    row.style.display = '';
                    if(row.classList.contains('sub-row')) {
                       row.style.display = 'table-row'; 
                    }
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>
    </body>
    </html>
    <?php
    exit;
}

// --- 3. VIEWER DASHBOARD BLOCK (Formerly uk1.php) ---

// --- PAGE PASSWORD PROTECTION ---
$page_password = 'Air#@4001'; // <--- CHANGE THE PAGE VISITOR PASSWORD HERE

if (isset($_POST['site_login_pwd'])) {
    if ($_POST['site_login_pwd'] === $page_password) {
        $_SESSION['uk1_logged_in'] = true;
        header("Location: ?"); // Redirect to self
        exit;
    } else {
        $login_error = "Incorrect password. Please try again.";
    }
}

if (empty($_SESSION['uk1_logged_in'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login - Uttarakhand Ventures</title>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <style>
            body { font-family: 'Plus Jakarta Sans', sans-serif; background: radial-gradient(circle at top left, #f8fafc 0%, #e2e8f0 100%); display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .login-card { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); padding: 48px 40px; border-radius: 24px; box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.1), inset 0 1px 0 rgba(255, 255, 255, 1); border: 1px solid rgba(255, 255, 255, 0.5); width: 100%; max-width: 380px; text-align: center; transform: translateY(0); animation: floatIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
            @keyframes floatIn { 0% { opacity: 0; transform: translateY(20px); } 100% { opacity: 1; transform: translateY(0); } }
            .login-card h2 { margin-top: 0; color: #0f172a; margin-bottom: 28px; font-weight: 800; font-size: 26px; letter-spacing: -0.5px; }
            .input-group { margin-bottom: 24px; text-align: left; }
            .input-group input { width: 100%; padding: 16px 18px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 15px; box-sizing: border-box; font-family: inherit; font-weight: 500; background: #f8fafc; color: #1e293b; transition: all 0.3s ease; }
            .input-group input:focus { outline: none; border-color: #EB7F29; background: #ffffff; box-shadow: 0 0 0 4px rgba(235, 127, 41, 0.15); }
            .input-group input::placeholder { color: #94a3b8; }
            .login-btn { background: linear-gradient(135deg, #EB7F29 0%, #D16D1E 100%); color: #fff; border: none; width: 100%; padding: 16px; border-radius: 12px; font-size: 16px; font-weight: 700; cursor: pointer; transition: all 0.3s ease; font-family: inherit; box-shadow: 0 10px 20px -5px rgba(235, 127, 41, 0.4); letter-spacing: 0.5px; }
            .login-btn:hover { transform: translateY(-2px); box-shadow: 0 15px 25px -5px rgba(235, 127, 41, 0.5); }
            .login-btn:active { transform: translateY(1px); }
            .error-msg { background: #fef2f2; color: #ef4444; font-size: 14px; padding: 12px; border-radius: 10px; margin-bottom: 24px; font-weight: 600; border: 1px solid #fecaca; display: flex; align-items: center; justify-content: center; }
        </style>
    </head>
    <body>
        <div class="login-card">
            <h2>Secure Access</h2>
            <?php if(!empty($login_error)) echo "<div class='error-msg'>{$login_error}</div>"; ?>
            <form method="POST">
                <div class="input-group">
                    <input type="password" name="site_login_pwd" placeholder="Enter dashboard password" required autofocus>
                </div>
                <button type="submit" class="login-btn">Access Dashboard</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Safely load and decode data from the JSON file to prevent errors
$raw_data = file_get_contents($data_file);
$hotel_data = $raw_data ? json_decode($raw_data, true) : [];
if (!is_array($hotel_data)) {
    $hotel_data = []; 
}

// Helper function to guarantee ₹ sign displays, EXCEPT when it's a percentage (%)
function displayRupee($val) {
    $val = trim($val);
    if ($val === '' || $val === '-') return '-';
    if (strpos($val, '%') !== false) return $val; 
    if (strpos($val, '₹') === false) {
        return '₹' . $val;
    }
    return $val;
}

// Helper function to display EB and CWED below the base rate
function getAddonBadge($eb, $cwed) {
    $eb_clean = trim(str_replace('₹', '', $eb));
    $cwed_clean = trim(str_replace('₹', '', $cwed));
    
    $has_eb = ($eb_clean !== '' && $eb_clean !== '-');
    $has_cwed = ($cwed_clean !== '' && $cwed_clean !== '-');
    
    if (!$has_eb && !$has_cwed) return '';
    
    $html = "<div class='rate-addon-badge'>";
    if ($has_eb) $html .= "<span>EB:</span> " . htmlspecialchars(displayRupee($eb));
    if ($has_eb && $has_cwed) $html .= " <span style='color:#cbd5e1; margin:0 4px;'>|</span> ";
    if ($has_cwed) $html .= "<span>CWEB:</span> " . htmlspecialchars(displayRupee($cwed));
    $html .= "</div>";
    return $html;
}

// Initialize variables for search criteria
$search_location = isset($_GET['location']) ? trim($_GET['location']) : '';
$search_hotel = isset($_GET['hotel']) ? trim($_GET['hotel']) : '';

// Filter the data based on search input
$filtered_data = [];
if (!empty($hotel_data)) {
    $filtered_data = array_filter($hotel_data, function($hotel) use ($search_location, $search_hotel) {
        $match_location = true;
        $match_hotel = true;

        if ($search_location !== '') {
            $match_location = stripos($hotel['location'], $search_location) !== false;
        }
        
        if ($search_hotel !== '') {
            $match_hotel = stripos($hotel['name'], $search_hotel) !== false;
        }

        return $match_location && $match_hotel;
    });
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uttarakhand Ventures Hotel Rates</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --bg-color: #fff6f0; --card-bg: rgba(255, 255, 255, 0.97); --primary: #0f172a; --text-main: #334155; --text-muted: #64748b; --border: #e2e8f0; --accent: #EB7F29; --accent-hover: #D16D1E; --ring: rgba(235, 127, 41, 0.2); --glass-border: rgba(255, 255, 255, 1); }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); background-attachment: fixed; min-height: 100vh; margin: 0; padding: 40px 20px; color: var(--text-main); -webkit-font-smoothing: antialiased; font-size: 15px; }
        .container { max-width: 1400px; margin: 0 auto; background: var(--card-bg); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); padding: 48px; border-radius: 24px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.08), 0 0 0 1px rgba(255,255,255,0.5) inset; border: 1px solid rgba(226, 232, 240, 0.6); animation: fadeIn 0.5s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .header-wrap { display: flex; justify-content: space-between; align-items: center; margin-bottom: 36px; padding-bottom: 24px; border-bottom: 2px solid var(--border); }
        h2 { color: var(--primary); font-size: 28px; font-weight: 800; margin: 0; letter-spacing: -0.5px; display: flex; align-items: center; gap: 12px; }
        .admin-link { padding: 12px 24px; background-color: #ffffff; color: var(--text-main); text-decoration: none; border-radius: 12px; font-weight: 700; font-size: 15px; transition: all 0.3s ease; border: 1px solid var(--border); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); cursor: pointer; display: flex; align-items: center; gap: 8px; }
        .admin-link:hover { background-color: var(--bg-color); color: var(--primary); border-color: #cbd5e1; transform: translateY(-1px); box-shadow: 0 6px 12px -2px rgba(0, 0, 0, 0.08); }
        .search-box { display: flex; gap: 16px; margin-bottom: 36px; padding: 24px; background-color: #F2A971; border-radius: 8px; align-items: center; flex-wrap: wrap; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid #E5955B; }
        .search-box select, .search-box input[type="text"] { padding: 16px 20px; border: 1px solid #CCCCCC; border-radius: 4px; flex: 1; min-width: 240px; font-size: 15px; font-weight: 600; font-family: inherit; color: var(--primary); transition: all 0.3s ease; background-color: #FFFFFF; }
        .search-box select:focus, .search-box input[type="text"]:focus { outline: none; border-color: var(--accent); background: #fff; box-shadow: 0 0 0 4px var(--ring); }
        .search-box a { padding: 16px 32px; background: var(--accent); color: white; text-decoration: none; border-radius: 4px; font-weight: 700; font-size: 15px; transition: all 0.3s ease; box-shadow: 0 8px 15px -5px rgba(235, 127, 41, 0.4); text-align: center; white-space: nowrap; }
        .search-box a:hover { transform: translateY(-2px); box-shadow: 0 12px 20px -5px rgba(235, 127, 41, 0.5); background: var(--accent-hover); }
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 4px; background: #fff; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05); margin-bottom: 100px; }
        table { width: 100%; border-collapse: collapse; border-spacing: 0; font-size: 15px; border: 2px solid #5CAE5D;} 
        table th, table td { border: 1px solid #5CAE5D; padding: 14px 16px; text-align: center; }
        table th { background-color: #FCAB73; color: #000; font-weight: 800; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; position: sticky; top: 0; z-index: 10; border-bottom: 1px solid #5CAE5D !important; }
        table thead tr:first-child th:nth-child(5) { background-color: #498BF4; color: #000; }
        table thead tr:first-child th:nth-child(6) { background-color: #3FD9B9; color: #000; }
        table thead tr:nth-child(2) th:nth-child(1), table thead tr:nth-child(2) th:nth-child(2), table thead tr:nth-child(2) th:nth-child(3) { background-color: #74A4F6; color: #000; }
        table thead tr:nth-child(2) th:nth-child(4), table thead tr:nth-child(2) th:nth-child(5), table thead tr:nth-child(2) th:nth-child(6) { background-color: #5BE0C6; color: #000; }
        .sortable { cursor: pointer; transition: all 0.2s ease; user-select: none; }
        .sortable:hover { opacity: 0.9; }
        .sort-icon { font-size: 12px; margin-left: 6px; opacity: 0.6; display: inline-block; transition: all 0.2s ease; }
        .sortable:hover .sort-icon { opacity: 1; transform: translateY(1px); }
        .sortable.active-sort .sort-icon { opacity: 1; font-weight: bold; }
        table tr.main-row { transition: all 0.2s ease; cursor: pointer; background-color: #ffffff; }
        table tr.main-row:hover { background-color: #f8fafc; transform: scale(1.001); box-shadow: 0 4px 10px rgba(0,0,0,0.02); z-index: 5; position: relative; }
        table tr.main-row td:nth-child(1), table tr.main-row td:nth-child(2) { background-color: #FC954F; color: #ffffff; }
        table tr.main-row td strong { font-size: 16px; font-weight: 800; letter-spacing: -0.2px; }
        table tr.main-row td:nth-child(2) strong { color: #ffffff; }
        table tr.main-row td small { font-size: 13px; font-weight: 600; color: #94a3b8; display: block; margin-top: 4px; }
        table tr.main-row td:nth-child(2) small { color: #ffe5d4; }
        .toggle-btn { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; background-color: rgba(255,255,255,0.2); color: #fff; font-size: 12px; border-radius: 50%; cursor: pointer; user-select: none; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        table tr.main-row:hover .toggle-btn { background-color: rgba(255,255,255,0.4); }
        .toggle-btn.active { transform: rotate(180deg); }
        .sub-menu-row { display: none; background-color: #ffffff; }
        .sub-menu-row td { color: var(--text-main); font-size: 14px; } 
        .sub-menu-row td:nth-child(3) { font-weight: 600; color: var(--primary); }
        .sub-menu-row td:nth-child(11) { font-weight: 500; color: var(--text-muted); font-size: 13px; max-width: 250px; line-height: 1.5; }
        .dash-icon { display: inline-block; width: 32px; text-align: center; color: #cbd5e1; font-size: 20px; font-weight: 800; }
        .no-data { text-align: center; color: var(--text-muted); padding: 80px 20px; font-size: 16px; font-weight: 600; }
        .swal2-popup { font-family: 'Plus Jakarta Sans', sans-serif !important; border-radius: 20px !important; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15) !important; padding: 24px !important; }
        input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--accent); cursor: pointer; margin: 0; border-radius: 4px; transition: all 0.2s ease; }
        input[type="checkbox"]:hover { transform: scale(1.1); }
        .floating-copy-btn { position: fixed; bottom: 40px; left: 50%; transform: translateX(-50%) translateY(150px); background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: white; padding: 18px 40px; border-radius: 50px; font-weight: 800; font-size: 16px; border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.3), 0 0 20px rgba(255,255,255,0.1) inset; cursor: pointer; display: flex; align-items: center; gap: 14px; transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1); opacity: 0; z-index: 1000; letter-spacing: 0.5px; }
        .floating-copy-btn.show { transform: translateX(-50%) translateY(0); opacity: 1; }
        .floating-copy-btn:hover { background: linear-gradient(135deg, #000000 0%, #0f172a 100%); transform: translateX(-50%) translateY(-5px) scale(1.02); box-shadow: 0 25px 45px -10px rgba(0, 0, 0, 0.4); }
        .floating-copy-btn svg { width: 22px; height: 22px; }
        .copy-badge { background: var(--accent); color: white; padding: 4px 12px; border-radius: 20px; font-size: 14px; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .validity-badge { color: #333; font-size: 13px; font-weight: 600; display: inline-block; white-space: nowrap; }
        .validity-always { color: #059669; }
        .rate-addon-badge { font-size: 12px; color: #475569; margin-top: 6px; line-height: 1.4; background: #f8fafc; padding: 4px 8px; border-radius: 4px; border: 1px solid #e2e8f0; display: inline-block; font-weight: 600; }
        .rate-addon-badge span { font-weight: 800; color: var(--accent); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;}
    </style>
</head>
<body>

<div class="container">
    <div class="header-wrap">
        <h2>🏩 Hotel Rates Dashboard</h2>
        <a href="#" onclick="promptAdminPassword(event)" class="admin-link">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
            Admin Access
        </a>
    </div>

    <div class="search-box">
        <select id="locationSelect" name="location">
            <option value="">Explore All Locations</option>
            <?php 
                if (!empty($hotel_data)) {
                    $unique_locations = array_unique(array_column($hotel_data, 'location'));
                    foreach ($unique_locations as $loc): 
                        $selected = (strcasecmp($search_location, $loc) == 0) ? 'selected' : '';
            ?>
                    <option value="<?php echo htmlspecialchars($loc); ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($loc); ?></option>
            <?php endforeach; } ?>
        </select>
        <input type="text" id="hotelInput" name="hotel" placeholder="Type Hotel Name to instantly search..." value="<?php echo htmlspecialchars($search_hotel); ?>" autocomplete="off">
        <a href="?" onclick="showToast('Filters reset successfully.', 'success')">Reset Filters</a>
    </div>

    <div class="table-responsive">
        <table id="mainTable">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 50px; text-align:center;"></th>
                    <th rowspan="2">Hotel Name</th>
                    <th rowspan="2">Room Category</th>
                    <th rowspan="2">Validity</th>
                    <th colspan="3" style="text-align: center;">Weekdays</th>
                    <th colspan="3" style="text-align: center;">Weekend</th>
                    <th rowspan="2">Remarks</th>
                    <th rowspan="2" style="text-align: center; width: 60px;">
                        <input type="checkbox" id="selectAllBtn" title="Select All Visible">
                    </th>
                </tr>
                <tr>
                    <th class="sortable" onclick="sortTable(4, this)">CPAI <span class="sort-icon">↕</span></th>
                    <th class="sortable" onclick="sortTable(5, this)">MAPAI <span class="sort-icon">↕</span></th>
                    <th class="sortable" onclick="sortTable(6, this)">APAI <span class="sort-icon">↕</span></th>
                    <th class="sortable" onclick="sortTable(7, this)">CPAI <span class="sort-icon">↕</span></th>
                    <th class="sortable" onclick="sortTable(8, this)">MAPAI <span class="sort-icon">↕</span></th>
                    <th class="sortable" onclick="sortTable(9, this)">APAI <span class="sort-icon">↕</span></th>
                </tr>
            </thead>
            <tbody id="tableBody">
                <?php if (!empty($filtered_data)): ?>
                    <?php foreach ($filtered_data as $row): ?>
                        
                        <tr class="main-row" 
                            onclick="toggleRow(this, 'submenu-<?php echo $row['id']; ?>')"
                            data-search-name="<?php echo htmlspecialchars(strtolower($row['name'])); ?>"
                            data-search-loc="<?php echo htmlspecialchars(strtolower($row['location'])); ?>">
                            <td style="text-align:center;"><span class="toggle-btn">+</span></td>
                            <td style="text-align:left;">
                                <strong><?php echo htmlspecialchars($row['name']); ?></strong><br>
                                <small><?php echo htmlspecialchars($row['location']); ?></small>
                            </td>
                            <td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td>
                            <td style="text-align:center;" onclick="event.stopPropagation();">
                                <input type="checkbox" class="hotel-checkbox" data-id="<?php echo $row['id']; ?>" title="Select Entire Hotel">
                            </td>
                        </tr>
                        
                        <?php foreach ($row['categories'] as $category): 
                            // Validity Logic for Display and Copy
                            $valid_from_raw = $category['valid_from'] ?? '';
                            $valid_to_raw = $category['valid_to'] ?? '';
                            
                            if (!empty($valid_from_raw) && !empty($valid_to_raw)) {
                                $valid_badge = "<span class='validity-badge'>" . date('d/m/y', strtotime($valid_from_raw)) . " - " . date('d/m/y', strtotime($valid_to_raw)) . "</span>";
                                $valid_text_copy = "Valid season rate " . date('jS M Y', strtotime($valid_from_raw)) . " - " . date('jS M Y', strtotime($valid_to_raw));
                            } elseif (!empty($valid_from_raw)) {
                                $valid_badge = "<span class='validity-badge'>From " . date('d/m/y', strtotime($valid_from_raw)) . "</span>";
                                $valid_text_copy = "Valid season rate From " . date('jS M Y', strtotime($valid_from_raw));
                            } elseif (!empty($valid_to_raw)) {
                                $valid_badge = "<span class='validity-badge'>Until " . date('d/m/y', strtotime($valid_to_raw)) . "</span>";
                                $valid_text_copy = "Valid season rate Till " . date('jS M Y', strtotime($valid_to_raw));
                            } else {
                                $valid_badge = "<span class='validity-badge validity-always'>Always Valid</span>";
                                $valid_text_copy = "Always Valid";
                            }

                            // Capture EB and CWEB to bind to the checkbox data attributes
                            $eb_val = $category['wd_capai_eb'] ?: ($category['we_capai_eb'] ?: ($category['wd_mapai_eb'] ?: ($category['we_mapai_eb'] ?: ($category['wd_apai_eb'] ?: ($category['we_apai_eb'] ?: '')))));
                            $cweb_val = $category['wd_capai_cweb'] ?: ($category['we_capai_cweb'] ?: ($category['wd_mapai_cweb'] ?: ($category['we_mapai_cweb'] ?: ($category['wd_apai_cweb'] ?: ($category['we_apai_cweb'] ?: '')))));
                        ?>
                            <tr class="sub-menu-row submenu-<?php echo $row['id']; ?>">
                                <td style="text-align:center;"></td>
                                <td></td>
                                <td><?php echo htmlspecialchars($category['room_category'] ?? '-'); ?></td>
                                <td><?php echo $valid_badge; ?></td>
                                
                                <td>
                                    <?php echo htmlspecialchars(displayRupee($category['wd_capai'] ?? '-')); ?><br>
                                    <?php echo getAddonBadge($category['wd_capai_eb'] ?? '', $category['wd_capai_cweb'] ?? ''); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars(displayRupee($category['wd_mapai'] ?? '-')); ?><br>
                                    <?php echo getAddonBadge($category['wd_mapai_eb'] ?? '', $category['wd_mapai_cweb'] ?? ''); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars(displayRupee($category['wd_apai'] ?? '-')); ?><br>
                                    <?php echo getAddonBadge($category['wd_apai_eb'] ?? '', $category['wd_apai_cweb'] ?? ''); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars(displayRupee($category['we_capai'] ?? '-')); ?><br>
                                    <?php echo getAddonBadge($category['we_capai_eb'] ?? '', $category['we_capai_cweb'] ?? ''); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars(displayRupee($category['we_mapai'] ?? '-')); ?><br>
                                    <?php echo getAddonBadge($category['we_mapai_eb'] ?? '', $category['we_mapai_cweb'] ?? ''); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars(displayRupee($category['we_apai'] ?? '-')); ?><br>
                                    <?php echo getAddonBadge($category['we_apai_eb'] ?? '', $category['we_apai_cweb'] ?? ''); ?>
                                </td>

                                <td><?php echo htmlspecialchars($category['remarks'] ?? '-'); ?></td>
                                
                                <td style="text-align:center;">
                                    <input type="checkbox" class="rate-checkbox" 
                                        data-hotel="<?php echo htmlspecialchars($row['name']); ?>"
                                        data-loc="<?php echo htmlspecialchars($row['location']); ?>"
                                        data-cat="<?php echo htmlspecialchars($category['room_category'] ?? '-'); ?>"
                                        data-valid="<?php echo htmlspecialchars($valid_text_copy); ?>"
                                        data-wdc="<?php echo htmlspecialchars(displayRupee($category['wd_capai'] ?? '-')); ?>"
                                        data-wdm="<?php echo htmlspecialchars(displayRupee($category['wd_mapai'] ?? '-')); ?>"
                                        data-wda="<?php echo htmlspecialchars(displayRupee($category['wd_apai'] ?? '-')); ?>"
                                        data-wec="<?php echo htmlspecialchars(displayRupee($category['we_capai'] ?? '-')); ?>"
                                        data-wem="<?php echo htmlspecialchars(displayRupee($category['we_mapai'] ?? '-')); ?>"
                                        data-wea="<?php echo htmlspecialchars(displayRupee($category['we_apai'] ?? '-')); ?>"
                                        data-eb="<?php echo htmlspecialchars($eb_val); ?>"
                                        data-cweb="<?php echo htmlspecialchars($cweb_val); ?>"
                                        data-rem="<?php echo htmlspecialchars($category['remarks'] ?? '-'); ?>">
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="12" class="no-data">
                            <svg style="width: 56px; height: 56px; color: #cbd5e1; margin: 0 auto 15px auto; display: block;" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                            No hotels found. Try adjusting your search criteria.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<button id="floatingCopyBtn" class="floating-copy-btn" onclick="copySelectedData()">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
    Copy Data <span class="copy-badge" id="copyCount">0</span>
</button>

<!-- JavaScript -->
<script>
    // --- Password Prompt Logic ---
    function promptAdminPassword(e) {
        e.preventDefault();
        Swal.fire({
            title: 'Admin Access',
            input: 'password',
            inputLabel: 'Password Required',
            inputPlaceholder: 'Enter your admin password',
            showCancelButton: true,
            confirmButtonColor: '#EB7F29',
            confirmButtonText: 'Login',
            preConfirm: (password) => {
                if (!password) {
                    Swal.showValidationMessage('Please enter a password');
                } else if (password !== 'M@nish#@uK32') { // CHANGE YOUR SECURE PASSWORD
                    Swal.showValidationMessage('Incorrect password');
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '?view=admin';
            }
        });
    }

    function showToast(message, iconType = 'info') {
        const bgColors = { 'success': '#5CAE5D', 'info': '#498BF4', 'error': '#ef4444' };
        const Toast = Swal.mixin({
            toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true,
            iconColor: 'white', scrollbarPadding: false, customClass: { popup: 'colored-toast' },
            background: bgColors[iconType] || '#EB7F29', color: 'white'
        });
        Toast.fire({ icon: iconType, title: message });
    }

    function toggleRow(rowElement, rowClass) {
        const rows = document.getElementsByClassName(rowClass);
        if(rows.length === 0) return;
        const btn = rowElement.querySelector('.toggle-btn');
        const isHidden = rows[0].style.display === "none" || rows[0].style.display === "";
        for (let i = 0; i < rows.length; i++) rows[i].style.display = isHidden ? "table-row" : "none";
        if (isHidden) { btn.innerHTML = "−"; btn.classList.add('active'); } else { btn.innerHTML = "+"; btn.classList.remove('active'); }
    }

    // --- CHECKBOX & COPY LOGIC ---
    document.addEventListener('DOMContentLoaded', function() {
        const selectAllBtn = document.getElementById('selectAllBtn');
        const hotelCheckboxes = document.querySelectorAll('.hotel-checkbox');
        const rateCheckboxes = document.querySelectorAll('.rate-checkbox');
        const floatingCopyBtn = document.getElementById('floatingCopyBtn');
        const copyCount = document.getElementById('copyCount');

        function updateCopyButton() {
            const selectedCount = document.querySelectorAll('.rate-checkbox:checked').length;
            if (selectedCount > 0) {
                copyCount.innerText = selectedCount;
                floatingCopyBtn.classList.add('show');
            } else {
                floatingCopyBtn.classList.remove('show');
            }
        }

        if (selectAllBtn) {
            selectAllBtn.addEventListener('change', function() {
                const isChecked = this.checked;
                const mainRows = document.querySelectorAll('tr.main-row');
                
                mainRows.forEach(row => {
                    if (row.style.display !== 'none') {
                        const hCb = row.querySelector('.hotel-checkbox');
                        if (hCb) hCb.checked = isChecked;
                        
                        const targetClass = row.getAttribute('onclick').match(/'([^']+)'/)[1];
                        const subRows = document.querySelectorAll(`.${targetClass} .rate-checkbox`);
                        subRows.forEach(cb => cb.checked = isChecked);
                    }
                });
                updateCopyButton();
            });
        }

        hotelCheckboxes.forEach(hCb => {
            hCb.addEventListener('change', function() {
                const isChecked = this.checked;
                const hotelId = this.getAttribute('data-id');
                const subRows = document.querySelectorAll(`.submenu-${hotelId} .rate-checkbox`);
                subRows.forEach(cb => cb.checked = isChecked);
                updateCopyButton();
            });
        });

        rateCheckboxes.forEach(rCb => {
            rCb.addEventListener('change', updateCopyButton);
        });
    });

    // Exact Format WhatsApp/Email Template Logic
    function copySelectedData() {
        const selected = document.querySelectorAll('.rate-checkbox:checked');
        if (selected.length === 0) return;

        let hotels = {};

        selected.forEach((cb) => {
            let hName = cb.getAttribute('data-hotel');
            let hLoc = cb.getAttribute('data-loc');
            let hotelNameWithLoc = hName + (hLoc ? ", " + hLoc : "");
            let validity = cb.getAttribute('data-valid');
            
            if(!hotels[hotelNameWithLoc]) {
                hotels[hotelNameWithLoc] = {};
            }
            if(!hotels[hotelNameWithLoc][validity]) {
                hotels[hotelNameWithLoc][validity] = [];
            }
            
            hotels[hotelNameWithLoc][validity].push({
                cat: cb.getAttribute('data-cat'),
                wdc: cb.getAttribute('data-wdc'),
                wdm: cb.getAttribute('data-wdm'),
                wda: cb.getAttribute('data-wda'),
                wec: cb.getAttribute('data-wec'),
                wem: cb.getAttribute('data-wem'),
                wea: cb.getAttribute('data-wea'),
                eb: cb.getAttribute('data-eb'),
                cweb: cb.getAttribute('data-cweb'),
                rem: cb.getAttribute('data-rem')
            });
        });

        let copyText = "";
        let hotelCounter = 0;

        Object.keys(hotels).forEach(hotelNameWithLoc => {
            if(hotelCounter > 0) {
                // Separation between entirely different hotels
                copyText += `\n\n`; 
            }
            hotelCounter++;
            
            // Format 1: Bold Hotel Name & Location
            copyText += `*${hotelNameWithLoc}*\n`;
            
            let validities = hotels[hotelNameWithLoc];
            let validityCounter = 0;

            Object.keys(validities).forEach(validity => {
                if(validityCounter > 0) {
                    copyText += `--------------------------------------------------\n\n`;
                }
                validityCounter++;

                // Format 2: Bold Validity
                if(validity && !validity.includes('Always Valid')) {
                    copyText += `*${validity}*\n\n`;
                } else {
                    copyText += `*Valid season rate Always Valid*\n\n`;
                }

                validities[validity].forEach((c, idx) => {
                    if(idx > 0) copyText += `\n`; 

                    // Format 3: Bold Category
                    copyText += `🗝️ *${c.cat}*\n`;
                    
                    const fmtNum = (p) => p ? p.replace(/[^\d]/g, '') : '';
                    // Allow digits and % sign for Addons, strip ₹ 
                    const fmtAddon = (p) => p ? p.replace(/[^\d%]/g, '') : '';

                    let hasCp = false;
                    let hasMap = false;

                    if((c.wdc && c.wdc !== '-') || (c.wec && c.wec !== '-')) {
                        if(c.wdc && c.wdc !== '-') copyText += `${fmtNum(c.wdc)} Weekday Per Room Per Night With Breakfast\n`;
                        if(c.wec && c.wec !== '-') copyText += `${fmtNum(c.wec)} Weekend Per Room Per Night With Breakfast\n`;
                        hasCp = true;
                    }
                    
                    if((c.wdm && c.wdm !== '-') || (c.wem && c.wem !== '-')) {
                        if(hasCp) copyText += `\n`; // Spacing between plans
                        if(c.wdm && c.wdm !== '-') copyText += `${fmtNum(c.wdm)} Weekday Per Room Per Night With Breakfast & Dinner\n`;
                        if(c.wem && c.wem !== '-') copyText += `${fmtNum(c.wem)} Weekend Per Room Per Night With Breakfast & Dinner\n`;
                        hasMap = true;
                    }
                    
                    if((c.wda && c.wda !== '-') || (c.wea && c.wea !== '-')) {
                        if(hasCp || hasMap) copyText += `\n`; // Spacing between plans
                        if(c.wda && c.wda !== '-') copyText += `${fmtNum(c.wda)} Weekday Per Room Per Night With Breakfast & Lunch & Dinner\n`;
                        if(c.wea && c.wea !== '-') copyText += `${fmtNum(c.wea)} Weekend Per Room Per Night With Breakfast & Lunch & Dinner\n`;
                    }

                    // Format 4: Bold Extra Bed and Remarks
                    let extraBeds = [];
                    if(c.cweb && c.cweb !== '-' && c.cweb !== '') {
                        extraBeds.push(`Child With Extra Bed - ${fmtAddon(c.cweb)}`);
                    }
                    if(c.eb && c.eb !== '-' && c.eb !== '') {
                        extraBeds.push(`Adult With Extra Bed - ${fmtAddon(c.eb)}`);
                    }
                    
                    if(extraBeds.length > 0) {
                        copyText += `\n*Extra Bed*\n`;
                        extraBeds.forEach(eb => copyText += `${eb}\n`);
                    }
                    
                    if(c.rem && c.rem !== '-') {
                        copyText += `\n*Remarks:*\n${c.rem}\n`;
                    }
                });
            });
        });

        copyText = copyText.trimEnd() + `\n--------------------------------------------------\n`;
        // Bold the footer request text and email/CC labels
        copyText += `*We request you to please send email for your query/booking*\n`;
        copyText += `*Email:* manish@uttarakhandventures.com,\n`;
        copyText += `*CC:* del@uttarakhandventures.com, ops@uttarakhandventures.com,b2b@uttarakhandventures.com`;

        navigator.clipboard.writeText(copyText).then(() => {
            showToast("Data copied successfully!", "success");
        }).catch(err => {
            showToast("Failed to copy data.", "error");
        });
    }

    // --- SORTING LOGIC ---
    let currentSortCol = -1;
    let currentSortDir = 'asc';

    function sortTable(colIndex, headerElement) {
        const tbody = document.getElementById('tableBody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        let groups = [];
        let currentGroup = null;
        
        rows.forEach(row => {
            if (row.classList.contains('main-row')) {
                if (currentGroup) groups.push(currentGroup);
                currentGroup = { main: row, subs: [], sortValue: null };
            } else if (row.classList.contains('sub-menu-row') && currentGroup) {
                currentGroup.subs.push(row);
            }
        });
        if (currentGroup) groups.push(currentGroup);
        if(groups.length === 0) return; 

        if (currentSortCol === colIndex) {
            currentSortDir = currentSortDir === 'asc' ? 'desc' : 'asc';
        } else {
            currentSortCol = colIndex;
            currentSortDir = 'asc';
        }

        groups.forEach(g => {
            let prices = g.subs.map(sub => {
                let text = sub.cells[colIndex].innerText.replace(/[^\d]/g, '');
                return text ? parseInt(text, 10) : null;
            }).filter(p => p !== null);
            
            if (prices.length === 0) {
                g.sortValue = currentSortDir === 'asc' ? Infinity : -Infinity;
            } else {
                g.sortValue = currentSortDir === 'asc' ? Math.min(...prices) : Math.max(...prices);
            }
        });

        groups.sort((a, b) => {
            if (a.sortValue === b.sortValue) return 0;
            if (currentSortDir === 'asc') return a.sortValue > b.sortValue ? 1 : -1;
            return a.sortValue < b.sortValue ? 1 : -1;
        });

        groups.forEach(g => {
            tbody.appendChild(g.main);
            g.subs.forEach(sub => tbody.appendChild(sub));
        });

        document.querySelectorAll('.sortable').forEach(th => {
            th.classList.remove('active-sort');
            th.querySelector('.sort-icon').innerHTML = '↕';
        });

        headerElement.classList.add('active-sort');
        headerElement.querySelector('.sort-icon').innerHTML = currentSortDir === 'asc' ? '↑' : '↓';

        const colName = headerElement.innerText.replace(/[^A-Za-z]/g, '');
        const mode = currentSortDir === 'asc' ? 'Lowest to Highest' : 'Highest to Lowest';
        showToast(`Sorted by ${colName} (${mode})`, 'info');
    }

    // --- ADVANCED LIVE SEARCH LOGIC ---
    let debounceTimer;
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(() => showToast("Welcome to Uttarakhand Ventures", "info"), 500);

        const hotelInput = document.getElementById('hotelInput');
        const locSelect = document.getElementById('locationSelect');

        function liveSearch() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                const locText = locSelect ? locSelect.value.toLowerCase().trim() : "";
                const filter = hotelInput ? hotelInput.value.toLowerCase().trim() : "";
                const mainRows = document.querySelectorAll('tr.main-row');
                
                mainRows.forEach(row => {
                    const hotelName = row.getAttribute('data-search-name') || "";
                    const locationName = row.getAttribute('data-search-loc') || "";
                    
                    const matchesLoc = locText === "" || locationName === locText;
                    // Advanced search: check if hotel name OR location name includes the typed text
                    const matchesText = hotelName.includes(filter) || locationName.includes(filter);

                    if (matchesLoc && matchesText) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                        const toggleBtn = row.querySelector('.toggle-btn');
                        if(toggleBtn && toggleBtn.classList.contains('active')) {
                            const targetClass = toggleBtn.closest('.main-row').getAttribute('onclick').match(/'([^']+)'/)[1];
                            toggleRow(row, targetClass);
                        }
                        const hCb = row.querySelector('.hotel-checkbox');
                        if(hCb) hCb.checked = false;
                        const matchResult = row.getAttribute('onclick').match(/'([^']+)'/);
                        if (matchResult) {
                            const subRows = document.querySelectorAll(`.${matchResult[1]} .rate-checkbox`);
                            subRows.forEach(cb => cb.checked = false);
                        }
                    }
                });
                
                const selectAllBtn = document.getElementById('selectAllBtn');
                if (selectAllBtn) selectAllBtn.checked = false;
                
                const firstRateCb = document.querySelector('.rate-checkbox');
                if(firstRateCb) {
                    const event = new Event('change');
                    firstRateCb.dispatchEvent(event);
                }
            }, 150);
        }

        if (hotelInput) hotelInput.addEventListener('input', liveSearch);
        if (locSelect) locSelect.addEventListener('change', liveSearch);
    });
</script>
</body>
</html>