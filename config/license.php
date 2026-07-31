<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Vendor public key
    |--------------------------------------------------------------------------
    |
    | Base64 Ed25519 public key used to verify licence keys. This is the half
    | that ships to customers — it can only verify signatures, never create
    | them. Generate a pair with `php artisan license:keygen` and keep the
    | secret key off every machine you deliver.
    |
    */

    'public_key' => env('LICENSE_PUBLIC_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Installed licence key
    |--------------------------------------------------------------------------
    |
    | The licence itself. Read from LICENSE_KEY when set, otherwise from the
    | file below (written by `php artisan license:install`), which lives on the
    | storage volume so it survives container rebuilds.
    |
    */

    'key' => env('LICENSE_KEY'),

    'key_path' => storage_path('app/license.key'),

    /*
    |--------------------------------------------------------------------------
    | Machine binding
    |--------------------------------------------------------------------------
    |
    | Host fingerprint passed in by start-prod.bat (Windows MachineGuid +
    | system volume serial, hashed). A licence issued with a machine binding
    | only validates on the machine whose fingerprint matches. Licences issued
    | without one run anywhere.
    |
    | Containers can't read host hardware themselves, so when this is empty the
    | app falls back to an install fingerprint persisted on the storage volume.
    | That still detects a plain copy of the app, but not someone who clones
    | the whole volume set — see the README for what this does and doesn't buy.
    |
    */

    'machine_id' => env('LICENSE_MACHINE_ID'),

    'install_id_path' => storage_path('app/install.id'),

    /*
    |--------------------------------------------------------------------------
    | Expiry handling
    |--------------------------------------------------------------------------
    |
    | `warn_days` starts showing a renewal banner that many days before expiry.
    | `grace_days` keeps the system fully usable for that long *after* expiry,
    | with a louder banner, so a late renewal never takes a shop offline
    | mid-service. Past the grace window the admin panel is blocked.
    |
    */

    'warn_days' => (int) env('LICENSE_WARN_DAYS', 14),

    'grace_days' => (int) env('LICENSE_GRACE_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Enforcement
    |--------------------------------------------------------------------------
    |
    | Set to false to disable blocking entirely (development, or a deployment
    | you don't licence). Banners still render so you can see licence state.
    |
    */

    'enforce' => (bool) env('LICENSE_ENFORCE', false),

    /*
    |--------------------------------------------------------------------------
    | Support contact
    |--------------------------------------------------------------------------
    |
    | Shown on the "licence required" screen so the customer knows who to call.
    |
    */

    'vendor' => env('LICENSE_VENDOR', 'Omni POS'),

    'support_email' => env('LICENSE_SUPPORT_EMAIL', ''),

    'support_phone' => env('LICENSE_SUPPORT_PHONE', ''),

];
