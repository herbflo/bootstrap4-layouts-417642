<?php

/*
|--------------------------------------------------------------------------
| Auto-created links from OpenStreetMap
|--------------------------------------------------------------------------
|
| Used by `php artisan links:auto-create`. Every day the command picks the
| next category from `categories` and the next country from the site's
| countries, then creates `per_day` links from OpenStreetMap businesses
| that have a name, website, phone number and e-mail address.
|
*/

return [

    'per_day' => 15,

    // Model classes used by the site (change to App\Link / App\Category on older Laravel installs).
    'models' => [
        'link' => \App\Models\Link::class,
        'category' => \App\Models\Category::class,
    ],

    // Column on the categories table that holds the slug used in /category/... URLs.
    'category_slug_column' => 'slug',

    // Columns on the links table. Set one to null to skip it. Columns that do
    // not exist in the links table are dropped automatically before insert.
    'link_columns' => [
        'category' => 'category_id',
        'phone' => 'phone_number',
        'email' => 'email',
        'address' => 'address',
        // Auto-detected from country_id / location_id / country when null.
        'country' => null,
    ],

    // Extra fixed values for every auto-created link (e.g. 'status' => 1, 'user_id' => 1).
    'extra_columns' => [],

    // Where the site's countries come from. When the table does not exist,
    // the static list below is used instead (ISO 3166-1 alpha-2 => name).
    'countries' => [
        'table' => 'countries',
        'fallback' => [
            'AU' => 'Australia',
        ],
    ],

    // If one country returns fewer than per_day results, top up from the
    // next countries in the list (1 = today's country only).
    'max_countries_per_run' => 3,

    // Only businesses that have every one of these are used.
    'require_phone' => true,
    'require_email' => true,

    'overpass' => [
        'endpoints' => [
            'https://overpass-api.de/api/interpreter',
            'https://overpass.kumi.systems/api/interpreter',
        ],
        'timeout' => 180,
        // Max OSM results fetched per query, before de-duplication.
        'fetch_limit' => 300,
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
