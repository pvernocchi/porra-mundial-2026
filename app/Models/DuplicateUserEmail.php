<?php
declare(strict_types=1);

namespace App\Models;

use RuntimeException;

final class DuplicateUserEmail extends RuntimeException
{
}

