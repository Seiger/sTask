<?php namespace Seiger\sTask\Support;

class TaskRunnerDescriptor
{
    public static function default(array $worker): array
    {
        $identifier = (string) ($worker['identifier'] ?? '');

        return array_filter([
            'contract' => 'stask.task-runner.v1',
            'identifier' => $identifier,
            'title' => (string) ($worker['title'] ?? $identifier),
            'description' => (string) ($worker['description'] ?? ''),
            'action' => 'make',
            'run_url' => self::runUrl($identifier, 'make'),
            'progress_url_template' => self::progressUrlTemplate(),
            'download_url_template' => self::downloadUrlTemplate(),
            'terminal_states' => ['completed', 'failed', 'cancelled', 'not_found', 'error'],
            'disable_while' => ['pending', 'preparing', 'running'],
            'log' => [
                'safe_html' => false,
                'max_lines' => 50,
            ],
        ], fn ($value) => $value !== null && $value !== '');
    }

    public static function composer(array $worker): array
    {
        return array_replace_recursive(self::default($worker), [
            'variant' => 'composer_update',
            'options' => [
                ['name' => 'no_dev', 'type' => 'checkbox', 'label' => __('sTask::global.no_dev')],
                ['name' => 'optimize', 'type' => 'checkbox', 'label' => __('sTask::global.optimize_autoloader'), 'default' => true],
                ['name' => 'prefer_stable', 'type' => 'checkbox', 'label' => __('sTask::global.prefer_stable'), 'default' => true],
                ['name' => 'with_dependencies', 'type' => 'checkbox', 'label' => __('sTask::global.with_dependencies'), 'default' => true],
            ],
            'result' => [
                'show_log' => true,
                'show_result' => true,
            ],
        ]);
    }

    public static function artisan(array $worker): array
    {
        return array_replace_recursive(self::default($worker), [
            'variant' => 'artisan',
            'command_list' => [
                'empty_command_lists_available_commands' => true,
                'click_to_fill_only' => true,
                'auto_run_on_command_click' => false,
            ],
            'fields' => [
                ['name' => 'command', 'type' => 'text', 'label' => __('sTask::global.artisan_command')],
                ['name' => 'arguments', 'type' => 'text', 'label' => __('sTask::global.arguments')],
                ['name' => 'confirm', 'type' => 'checkbox', 'label' => __('sTask::global.confirm_dangerous_command')],
            ],
            'security' => [
                'show_rejection' => true,
                'safe_local_smoke' => true,
            ],
        ]);
    }

    protected static function runUrl(string $identifier, string $action): string
    {
        return $identifier !== '' && function_exists('route')
            ? route('sTask.worker.task.run', ['identifier' => $identifier, 'action' => $action])
            : '';
    }

    protected static function progressUrlTemplate(): string
    {
        return function_exists('route')
            ? route('sTask.task.progress', ['id' => '__ID__'])
            : '';
    }

    protected static function downloadUrlTemplate(): string
    {
        return function_exists('route')
            ? route('sTask.task.download', ['id' => '__ID__'])
            : '';
    }
}
