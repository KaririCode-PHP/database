<?php

declare(strict_types=1);

namespace KaririCode\Database\Contract;

use KaririCode\Database\ValueObject\ConnectionParams;

// ── Driver ───────────────────────────────────────────────────────────
//
// Thin factory creating DriverConnection instances from params.
// Each driver encapsulates a specific PHP database extension.
// Middleware wraps drivers via the decorator pattern.
// ─────────────────────────────────────────────────────────────────────

interface Driver
{
    public function connect(ConnectionParams $params): DriverConnection;
}
