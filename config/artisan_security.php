<?php return [
    /*
    |--------------------------------------------------------------------------
    | Dangerous Commands
    |--------------------------------------------------------------------------
    |
    | Commands that are completely forbidden in production environment.
    | These commands can destroy data and should never be executed in production.
    |
    */
    'dangerous_commands' => [
        'migrate:fresh',    // Drops all tables
        'migrate:reset',    // Rollback all migrations
        'db:wipe',          // Drop all tables, views, and types
    ],

    /*
    |--------------------------------------------------------------------------
    | Confirmation Required
    |--------------------------------------------------------------------------
    |
    | Commands that require explicit confirmation before execution.
    | User must add 'confirm=true' to options to execute these commands.
    |
    */
    'confirmation_required' => [
        'migrate',
        'migrate:refresh',
        'migrate:rollback',
        'db:seed',
        'cache:clear-full',
    ],

    /*
    |--------------------------------------------------------------------------
    | Whitelisted Commands
    |--------------------------------------------------------------------------
    |
    | If this array is not empty, ONLY these commands will be allowed.
    | Leave empty to allow all commands (except dangerous ones).
    | Use patterns with '*' for wildcards, e.g., 'cache:*', 'view:*'
    |
    */
    'whitelist' => [
        // Examples:
        // 'cache:*',        // Allow all cache commands
        // 'view:clear',     // Allow specific command
        // 'list',           // Allow list command
    ],

    /*
    |--------------------------------------------------------------------------
    | Blacklisted Commands
    |--------------------------------------------------------------------------
    |
    | Commands that are explicitly forbidden (in addition to dangerous commands).
    | These will be blocked regardless of whitelist settings.
    |
    */
    'blacklist' => [
        // Examples:
        // 'key:generate',   // Prevent regenerating app key
        // 'down',           // Prevent maintenance mode
    ],

    /*
    |--------------------------------------------------------------------------
    | Enable Security Checks
    |--------------------------------------------------------------------------
    |
    | When false, all security checks are disabled (NOT RECOMMENDED).
    | Use this only for development/testing purposes.
    |
    */
    'enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Log All Executions
    |--------------------------------------------------------------------------
    |
    | Log every artisan command execution for audit purposes.
    | Logs include: command, user, timestamp, arguments.
    |
    */
    'log_executions' => true,

    /*
    |--------------------------------------------------------------------------
    | Require Manager Permission
    |--------------------------------------------------------------------------
    |
    | Permission key required to execute artisan commands.
    | Set to null to allow all manager users.
    |
    */
    'required_permission' => 'run_artisan',
];
