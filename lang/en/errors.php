<?php

return [
    'not_found' => 'Data not found.',
    'too_many_requests' => 'Too many requests. Please try again in a moment.',

    'back_to_map' => 'Back to the map',

    'pages' => [
        '401' => ['title' => 'Sign-in required', 'message' => 'Your session is invalid or has ended. Please sign in again.'],
        '403' => ['title' => 'Access denied', 'message' => 'You do not have permission to open this page.'],
        '404' => ['title' => 'Page not found', 'message' => 'The address you opened does not exist or has moved.'],
        '419' => ['title' => 'Session expired', 'message' => 'This page has been open too long. Reload the page and try again.'],
        '429' => ['title' => 'Too many requests', 'message' => 'You are sending requests too fast. Wait a moment and try again.'],
        '500' => ['title' => 'Server error', 'message' => 'Sorry, something went wrong on our side. Try again shortly; if it continues, contact the admin.'],
        '503' => ['title' => 'Under maintenance', 'message' => 'The service is temporarily unavailable. Please come back in a few moments.'],
    ],
];
