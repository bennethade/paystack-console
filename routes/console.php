<?php

use Illuminate\Support\Facades\Schedule;

// Request history retention (see Settings › history_retention_days).
Schedule::command('paystack:prune-history')->daily();
