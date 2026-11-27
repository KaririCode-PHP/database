<?php

declare(strict_types=1);

namespace KaririCode\Database\Contract;

// ── DataStoreEvent ───────────────────────────────────────────────────
//
// Marker interface shared between kariricode/database and
// kariricode/nosql for unified observability. All connection
// lifecycle and operation events implement this.
// ─────────────────────────────────────────────────────────────────────

interface DataStoreEvent {}
