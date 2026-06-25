<?php

// ───────────────────────────────────────────────
// API route entry point — split into sub-files:
//   api/auth.php   → authentication & user account
//   api/mobile.php → data queries for mobile devices
// ───────────────────────────────────────────────

require __DIR__ . '/api/auth.php';
require __DIR__ . '/api/mobile.php';
require __DIR__ . '/api/crm.php';
