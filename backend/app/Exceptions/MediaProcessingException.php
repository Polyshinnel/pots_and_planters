<?php

namespace App\Exceptions;

use RuntimeException;

// Messages in this exception are application-owned and safe to show to an administrator.
class MediaProcessingException extends RuntimeException {}
