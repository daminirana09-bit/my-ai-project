<?php

namespace App\Services\Translation;

use RuntimeException;

/** The translation service refused or answered badly; the message is safe to show. */
class TranslationFailed extends RuntimeException {}
