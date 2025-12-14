<?php

declare(strict_types=1);

use App\Bootstrap\AppBootstrap;

if (!defined('APP_START_TIME')) {
    define('APP_START_TIME', time());
}

return AppBootstrap::init();
