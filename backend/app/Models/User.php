<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Identity\Models\User as IdentityUser;

/**
 * Compatibility bridge for Laravel defaults and third-party packages that
 * resolve App\Models\User. The canonical model lives in the Identity domain.
 */
class User extends IdentityUser {}
