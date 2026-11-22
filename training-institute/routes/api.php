<?php
/**
 * Token-authenticated JSON API for corporate merchants (/api/v1/...).
 * CSRF is not used here (no cookies); the 'api' middleware validates
 * the Bearer key against merchant_api_keys.key_hash. Endpoints are added
 * together with the merchant module in Phase 4.
 */
declare(strict_types=1);

/** @var \App\Core\Router $router */
