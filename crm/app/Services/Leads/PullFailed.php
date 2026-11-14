<?php

namespace App\Services\Leads;

use RuntimeException;

/** A portal refused or answered badly; the message is safe to show to the admin. */
class PullFailed extends RuntimeException {}
