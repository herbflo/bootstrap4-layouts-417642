<?php

/*
|--------------------------------------------------------------------------
| Auto-created links from OpenStreetMap
|--------------------------------------------------------------------------
|
| Used by `php artisan links:auto-create`. Every day the command picks the
| next category from `categories` and the next country (top-level location
| in Admin > Locations), then creates `per_day` links from OpenStreetMap
| businesses that have a name, website, phone number and e-mail address.
| Links are saved like the bulk importer saves them: owner 1, enabled 1,
| status 2, with the category's language.
|
*/

return [

    'per_day' => 15,

    // Admin user the links are posted by.
    'owner' => 1,

    // The day starts with one category and one country. If they have fewer
    // than per_day matches, it moves on to the next countries, then to the
    // next categories, until per_day links are created.
    // Countries to try per category (0 = all of them).
    'max_countries_per_run' => 0,
    // Categories to try per day.
    'max_categories_per_run' => 3,

    // Stop once a run has taken this many seconds (each country takes about
    // a minute), so a slow OpenStreetMap server can't keep the job running.
    'max_runtime' => 1800,

    // Location titles that differ from the English country name in
    // OpenStreetMap, mapped to the ISO 3166-1 code (e.g. 'USA' => 'US').
    'country_codes' => [
        'USA' => 'US',
        'United States' => 'US',
        'UK' => 'GB',
        'United Kingdom' => 'GB',
        'UAE' => 'AE',
    ],

    // Only businesses that have every one of these are used.
    'require_phone' => true,
    'require_email' => true,

    'overpass' => [
        'endpoints' => [
            'https://overpass-api.de/api/interpreter',
            'https://overpass.kumi.systems/api/interpreter',
            'https://overpass.private.coffee/api/interpreter',
        ],
        // Busy public servers often answer 429/502/504; retry each one this many times.
        'tries_per_endpoint' => 1,
        // Seconds per request. Large countries (e.g. Germany) need a few minutes.
        'timeout' => 180,
        // Max OSM results fetched per query, before phone/e-mail filtering and de-duplication.
        'fetch_limit' => 1000,
        'user_agent' => 'LinkDirectoryPro/1.0 (+https://linkdirectory.pro; support@linkdirectory.pro)',
    ],

    /*
    | Site category slug => OpenStreetMap tag filters. The command rotates
    | through these in order, one category per day. Categories missing from
    | the database are skipped. Tag reference: https://wiki.openstreetmap.org/wiki/Map_features
    */
    'categories' => [
        'real-estate-services' => ['["office"="estate_agent"]', '["shop"="estate_agent"]'],
        'golf' => ['["leisure"="golf_course"]', '["sport"="golf"]'],
        'martial-arts' => ['["sport"~"martial_arts|karate|judo|taekwondo|boxing"]'],
        'tennis' => ['["sport"="tennis"]["leisure"="sports_centre"]', '["club"="sport"]["sport"="tennis"]'],
        'surfing' => ['["shop"="surf"]', '["sport"="surfing"]'],
        'antique' => ['["shop"="antiques"]'],
        'bar' => ['["amenity"="bar"]'],
        'movies-films' => ['["amenity"="cinema"]'],
        'theatre' => ['["amenity"="theatre"]'],
        'finance-investment' => ['["office"="financial"]', '["office"="financial_advisor"]', '["office"="accountant"]'],
        'aviation' => ['["aeroway"="aerodrome"]["website"]', '["office"="airline"]'],
        'caravans-campers-rv-s' => ['["shop"="caravan"]', '["tourism"="caravan_site"]'],
        'cars-vans-utes' => ['["shop"="car"]', '["shop"="car_repair"]'],
        'motorcycles-scooters' => ['["shop"="motorcycle"]'],
        'bags' => ['["shop"="bag"]'],
        'accessories' => ['["shop"="jewelry"]', '["shop"="fashion_accessories"]'],
        'dress-making-alterations' => ['["shop"="tailor"]', '["craft"="tailor"]', '["craft"="dressmaker"]'],
        'shoes' => ['["shop"="shoes"]'],
        'colleges-and-universities' => ['["amenity"="university"]', '["amenity"="college"]'],
        'child-health' => ['["healthcare:speciality"="paediatrics"]'],
        'dentist' => ['["amenity"="dentist"]'],
        'fitness' => ['["leisure"="fitness_centre"]'],
        'general-practitioner' => ['["amenity"="doctors"]'],
        'health-care' => ['["amenity"="clinic"]', '["healthcare"="physiotherapist"]'],
        'spa' => ['["leisure"="spa"]', '["shop"="massage"]'],
        'aged-care-services' => ['["social_facility"="nursing_home"]', '["social_facility"="assisted_living"]'],
        'appliances' => ['["shop"="appliance"]'],
        'child-care-centre' => ['["amenity"="childcare"]', '["amenity"="kindergarten"]'],
        'lightning' => ['["shop"="lighting"]', '["craft"="electrician"]'],
        'radios' => ['["studio"="radio"]'],
        'television-channels' => ['["studio"="television"]'],
        'pet-accessories' => ['["shop"="pet"]'],
        'services-stores' => ['["amenity"="veterinary"]', '["shop"="pet_grooming"]'],
        'asian' => ['["amenity"="restaurant"]["cuisine"~"asian|chinese|japanese|thai|vietnamese|korean|indian"]'],
        'bakery' => ['["shop"="bakery"]'],
        'brewery' => ['["craft"="brewery"]', '["industrial"="brewery"]'],
        'burgers' => ['["amenity"~"restaurant|fast_food"]["cuisine"~"burger"]'],
        'cafe' => ['["amenity"="cafe"]'],
        'fastfood' => ['["amenity"="fast_food"]'],
        'ice-cream' => ['["amenity"="ice_cream"]', '["shop"="ice_cream"]'],
        'italian' => ['["amenity"="restaurant"]["cuisine"~"italian"]'],
        'pizza' => ['["amenity"~"restaurant|fast_food"]["cuisine"~"pizza"]'],
        'seafood' => ['["amenity"="restaurant"]["cuisine"~"seafood|fish"]'],
        'vegan' => ['["amenity"="restaurant"]["diet:vegan"~"yes|only"]'],
        'books' => ['["shop"="books"]'],
        'electronics' => ['["shop"="electronics"]'],
        'office-products' => ['["shop"="stationery"]'],
        'personal-care' => ['["shop"="hairdresser"]', '["shop"="cosmetics"]'],
        'toys' => ['["shop"="toys"]'],
        'bottle-shop' => ['["shop"="alcohol"]', '["shop"="wine"]'],
        'bridal-shop' => ['["shop"="wedding"]'],
        'cake-shop' => ['["shop"="pastry"]', '["shop"="confectionery"]'],
        'coffee-tea-shop' => ['["shop"="coffee"]', '["shop"="tea"]'],
        'computer-shop' => ['["shop"="computer"]'],
        'fishing-shop' => ['["shop"="fishing"]'],
        'health-beauty-shop' => ['["shop"="beauty"]', '["shop"="chemist"]'],
        'backpacking' => ['["tourism"="hostel"]'],
        'holiday-homes' => ['["tourism"="chalet"]', '["tourism"="apartment"]', '["tourism"="guest_house"]'],
        'hotels' => ['["tourism"="hotel"]', '["tourism"="motel"]'],
        'pub-bar' => ['["amenity"="pub"]'],
        'tours' => ['["shop"="travel_agency"]', '["office"="travel_agent"]'],
    ],
];
