<?php

declare(strict_types=1);

return [
    // Numero oficial del consultorio (formato internacional sin espacios)
    'clinic_phone' => '593994476914',

    // Opciones: simulate | twilio | meta | 360dialog
    'provider' => 'meta',

    // Codigo de pais para normalizar telefonos locales (ej: Ecuador 593)
    'default_country_code' => '593',


    // Meta Cloud API
    // Completar desde Meta for Developers:
    // WhatsApp Business -> API Setup -> Temporary/Permanent Access Token y Phone Number ID.
    'meta_token' => (string) (getenv('META_WHATSAPP_TOKEN') ?: 'EAAM827ZCmn6gBQ6hCPzyZAibXILkdl9rXK1b160ypIwbzXRipQymCY0AMfEqEWEFW3h34L3ViNTABcrUZCSYzPCaPy5hy7FTqiR37JZAARszaMqhXU0vUoaG82toZC34iD97wMSA2D2TcZCC2qls2MyZBCBYCZBt5dcf6ZAlltmW4ep7aXqQjDz9de8yZAVSFPAJSWOhLpx7y9VhiBM1wFNeZBqxJtXyxuINvxq3T3i6ZCa3soLi1qxqCtczaU8XMRDilYY4hMQQtIhy9CfbSyTmMHCkFAZDZD'),
    'meta_phone_number_id' => '1032105303315080',
    'meta_api_version' => 'v20.0',

    // 360dialog
    'd360_api_key' => '',
    'd360_base_url' => 'https://waba-v2.360dialog.io',

    // General
    'request_timeout' => 20,
];
