<?php

return [
    'prospects' => [
        'filename' => 'prospects',
        'sheet' => 'Prospects',
        'columns' => [
            'id', 'name', 'category', 'city', 'province', 'address', 'phone', 'phone_simulated', 'rating',
            'review_count', 'score', 'status', 'handled_by', 'last_contacted', 'distance_to_outlet_m',
        ],
    ],
    'dashboard' => [
        'filename' => 'dashboard',
        'columns' => ['section', 'name', 'value', 'prospects', 'won', 'penetration'],
        'section_kpi' => 'KPI',
        'section_funnel' => 'Funnel',
        'section_penetration' => 'Penetration',
    ],
];
