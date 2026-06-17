<?php

use Seiger\sTask\Controllers\sTaskController;

if (!defined('IN_MANAGER_MODE') || !IN_MANAGER_MODE) {
    die('No access');
}

echo app(sTaskController::class)->index()->render();
