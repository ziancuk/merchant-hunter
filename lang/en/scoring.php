<?php

return [
    'bands' => [
        'hot' => 'Prime Target',
        'warm' => 'Promising',
        'medium' => 'Potential',
        'cold' => 'Watch',
    ],

    'labels' => [
        'category' => 'Category fit',
        'quality' => 'Quality (rating & reviews)',
        'proximity' => 'Proximity to outlet',
        'density' => 'Area density',
        'contact' => 'Contact completeness',
        'new' => 'Newly opened',
    ],

    'reasons' => [
        'closed' => 'Business closed: score 0',
        'category' => 'Category weight :weight',
        'rating_ignored' => 'Rating not counted (neutral value :value)',
        'rating_missing' => 'No rating yet (neutral value :value)',
        'rating' => 'Rating :rating from :reviews reviews',
        'neighbors' => ':count Hitachi outlets within 500 m',
        'no_neighbors' => 'No Hitachi outlet within 500 m',
        'distance_unknown' => '; distance to outlet unknown',
        'nearest' => '; nearest outlet :distance m',
        'density' => ':count other prospects within 500 m',
        'contact_none' => 'No phone, website or address yet',
        'contact_have' => 'Available: :items',
        'contact_phone' => 'phone',
        'contact_website' => 'website',
        'contact_address' => 'address',
        'not_new' => 'Not a newly opened business',
        'new_age' => 'Opened :days days ago',
        'new_expired' => 'Newly opened, but :days days have passed',
    ],
];
