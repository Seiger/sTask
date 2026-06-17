<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$tests = 0;

$read = static function (string $path) use ($root): string {
    $fullPath = $root . '/' . ltrim($path, '/');

    if (!is_file($fullPath)) {
        throw new RuntimeException("Missing file: {$path}");
    }

    return (string) file_get_contents($fullPath);
};

$assert = static function (bool $condition, string $message) use (&$failures, &$tests): void {
    $tests++;

    if (!$condition) {
        $failures[] = $message;
    }
};

$contains = static function (string $haystack, string $needle, string $message) use ($assert): void {
    $assert(str_contains($haystack, $needle), $message);
};

$notContains = static function (string $haystack, string $needle, string $message) use ($assert): void {
    $assert(!str_contains($haystack, $needle), $message);
};

$composer = json_decode($read('composer.json'), true);
$assert(is_array($composer), 'composer.json must be valid JSON.');
$assert(($composer['name'] ?? null) === 'seiger/stask', 'composer package name must stay seiger/stask.');
$assert(($composer['require']['evolution-cms/evo-ui'] ?? null) === '^1.0.6', 'sTask must pin evo-ui baseline dependency.');
$assert(($composer['scripts']['test'] ?? null) === 'php tests/run.php', 'composer test must run the package smoke suite.');
$assert(
    in_array('Seiger\\sTask\\sTaskServiceProvider', $composer['extra']['laravel']['providers'] ?? [], true),
    'Laravel provider must stay registered in composer extra.'
);

$docsRoot = $root . '/docs';
$docsReadme = $read('docs/README.md');
foreach (['en', 'uk', 'pl', 'de', 'fr'] as $locale) {
    $contains($docsReadme, "({$locale}/README.md)", "Root docs README must link {$locale} docs.");
    foreach ([
        'README.md',
        'user-guide.md',
        'developer-guide.md',
        'reference.md',
        'configuration.md',
        'troubleshooting.md',
        'custom-worker-migration.md',
        'frontend-guide.md',
        'backend-guide.md',
    ] as $file) {
        $path = "docs/{$locale}/{$file}";
        $content = $read($path);
        $assert(trim($content) !== '', "{$path} must not be empty.");
    }

    $localeReadme = $read("docs/{$locale}/README.md");
    $contains($localeReadme, '(user-guide.md)', "{$locale} README must link user guide.");
    $contains($localeReadme, '(developer-guide.md)', "{$locale} README must link developer guide.");
    $contains($localeReadme, '(reference.md)', "{$locale} README must link reference.");
    $contains($localeReadme, '(configuration.md)', "{$locale} README must link configuration.");
    $contains($localeReadme, '(troubleshooting.md)', "{$locale} README must link troubleshooting.");
    $contains($localeReadme, '(custom-worker-migration.md)', "{$locale} README must link custom worker migration.");
    $contains($localeReadme, '(frontend-guide.md)', "{$locale} README must link frontend guide.");
    $contains($localeReadme, '(backend-guide.md)', "{$locale} README must link backend guide.");
}

$docsIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($docsRoot));
foreach ($docsIterator as $fileInfo) {
    if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'md') {
        continue;
    }

    $relativePath = 'docs/' . ltrim(str_replace($docsRoot, '', $fileInfo->getPathname()), '/');
    $content = (string) file_get_contents($fileInfo->getPathname());
    preg_match_all('/\[[^\]]+\]\(([^)#][^)]+\.md(?:#[^)]+)?)\)/', $content, $matches);

    foreach ($matches[1] as $target) {
        $target = explode('#', $target, 2)[0];
        if (preg_match('/^[a-z]+:\/\//i', $target)) {
            continue;
        }

        $targetPath = realpath(dirname($fileInfo->getPathname()) . '/' . $target);
        $assert(
            $targetPath !== false && str_starts_with($targetPath, $docsRoot) && is_file($targetPath),
            "Markdown link {$target} in {$relativePath} must resolve inside docs."
        );
    }
}

$provider = $read('src/sTaskServiceProvider.php');
$contains($provider, "mergeConfigFrom(dirname(__DIR__) . '/config/sTaskCheck.php', 'cms.settings')", 'Provider must merge sTask CMS settings.');
$contains($provider, "loadMigrationsFrom(__DIR__ . '/Database/Migrations')", 'Provider must load package migrations.');
$contains($provider, "loadTranslationsFrom(dirname(__DIR__) . '/lang', 'sTask')", 'Provider must load sTask translations namespace.');
$contains($provider, "loadViewsFrom(dirname(__DIR__) . '/views', 'sTask')", 'Provider must load sTask views namespace.');
$contains($provider, "mergeConfigFrom(dirname(__DIR__) . '/config/tasks/table.php', 'stask.tasks.table')", 'Provider must merge the sTask EvoUI tasks table preset.');
$contains($provider, "mergeConfigFrom(dirname(__DIR__) . '/config/workers/table.php', 'stask.workers.table')", 'Provider must merge the sTask EvoUI workers table preset.');
$contains($provider, "mergeConfigFrom(dirname(__DIR__) . '/config/logs/table.php', 'stask.logs.table')", 'Provider must merge the sTask EvoUI logs table preset.');
$contains($provider, "Livewire::component('stask.module-panel'", 'Provider must register the sTask EvoUI module panel.');
$contains($provider, '$this->loadRoutes();', 'Provider must load manager routes.');
$contains($provider, '$this->loadPluginsFrom(dirname(__DIR__) . \'/plugins/\');', 'Provider must load Evolution plugin bridge.');
$contains($provider, '$this->app->registerModule(', 'Provider must register the Evolution manager module.');
$contains($provider, "module_title", 'Manager registration must use localized module_title.');
$contains($provider, "module_icon", 'Manager registration must use localized module_icon.');
$contains($provider, 'TaskWorker::class', 'Provider must register/schedule TaskWorker.');
$notContains($provider, 'abort(', 'Provider must not use Laravel abort fallback in manager boot path.');

$module = $read('module/sTaskModule.php');
$contains($module, 'IN_MANAGER_MODE', 'Module entry must keep manager-mode guard.');
$contains($module, "die('No access')", 'Module entry must keep Evolution-compatible no-access guard.');
$contains($module, 'app(sTaskController::class)->index()->render()', 'Module entry must render sTask controller index.');

$controller = $read('src/Controllers/sTaskController.php');
$contains($controller, "view('sTask::module.shell'", 'Controller index must render the EvoUI shell.');
$contains($controller, "view('sTask::module.task-detail'", 'Controller task detail must render the EvoUI task detail shell.');
$contains($controller, "'dashboard'", 'Controller must define dashboard module tab.');
$contains($controller, "'tasks'", 'Controller must define tasks module tab.');
$contains($controller, "'workers'", 'Controller must define workers module tab.');
$contains($controller, "'logs'", 'Controller must define logs module tab.');
$contains($controller, "'performance'", 'Controller must define performance module tab.');
$contains($controller, 'use Seiger\\sTask\\Models\\sWorker as sWorker;', 'Controller must import the real lowercase-s worker model.');
$notContains($controller, 'use Seiger\\sTask\\Models\\Worker;', 'Controller must not import the non-existent Worker model.');
$contains($controller, "redirect()->route('sTask.index', ['get' => 'workers'])", 'Legacy workers/settings routes must redirect to the EvoUI workers tab.');
$notContains($controller, "view('sTask::workers'", 'Controller must not render the legacy workers page as an active surface.');
$notContains($controller, "view('sTask::workerSettings'", 'Controller must not render the legacy worker settings page as an active surface.');

$shell = $read('views/module/shell.blade.php');
$contains($shell, 'EvoUI\\Support\\ManagerContext', 'EvoUI shell must use ManagerContext theme bridge.');
$contains($shell, "@include('evo::partials.assets')", 'EvoUI shell must load EvoUI local assets.');
$contains($shell, 'data-evo-ui-root', 'EvoUI shell must expose data-evo-ui-root.');
$contains($shell, '<livewire:stask.module-panel', 'EvoUI shell must mount the sTask module panel.');
$notContains($shell, 'stask.min.css', 'EvoUI shell must not load old sTask CSS.');
$notContains($shell, 'stask.js', 'EvoUI shell must not load old sTask JS.');
$notContains($shell, 'cdn.jsdelivr', 'EvoUI shell must not load jsdelivr assets.');
$notContains($shell, 'unpkg', 'EvoUI shell must not load unpkg assets.');
$notContains($shell, 'media/script/main.js', 'EvoUI shell must not load legacy manager main.js.');

$taskDetail = $read('views/module/task-detail.blade.php');
$contains($taskDetail, 'EvoUI\\Support\\ManagerContext', 'Task detail shell must use ManagerContext theme bridge.');
$contains($taskDetail, "@include('evo::partials.assets')", 'Task detail shell must load EvoUI local assets.');
$contains($taskDetail, 'data-evo-ui-root', 'Task detail shell must expose data-evo-ui-root.');
$contains($taskDetail, '<x-evo::card', 'Task detail shell must use shared EvoUI cards.');
$contains($taskDetail, '<x-evo::badge', 'Task detail shell must use shared EvoUI badges.');
$notContains($taskDetail, "@extends('sTask::index')", 'Task detail shell must not extend legacy sTask index.');
$notContains($taskDetail, '<style', 'Task detail shell must not add local style blocks.');
$notContains($taskDetail, '<script', 'Task detail shell must not add local script blocks.');
$notContains($taskDetail, 'stask.min.css', 'Task detail shell must not load old sTask CSS.');
$notContains($taskDetail, 'cdn.jsdelivr', 'Task detail shell must not load CDN assets.');

$modulePanel = $read('src/Livewire/ModulePanel.php');
$contains($modulePanel, 'class ModulePanel extends Component', 'sTask ModulePanel must be a Livewire component.');
$contains($modulePanel, 'DashboardData::class', 'sTask ModulePanel must delegate dashboard data to DashboardData.');
$contains($modulePanel, 'openTaskDetails(int $id)', 'Dashboard recent task rows must open the details modal.');
$contains($modulePanel, 'LogsTableData::class', 'Dashboard task detail modal must reuse the logs detail provider.');
$contains($modulePanel, 'closeModal()', 'Dashboard task detail modal must expose the shared EvoUI close method.');
$contains($modulePanel, 'clearWorkerCache()', 'sTask ModulePanel must expose cache clear action.');
$contains($modulePanel, 'sTaskFacade::clearWorkerCache()', 'sTask ModulePanel must call the real cache clear service.');

$dashboardData = $read('src/Support/DashboardData.php');
$contains($dashboardData, 'class DashboardData', 'DashboardData support class must exist.');
$contains($dashboardData, 'sTaskFacade::getStats()', 'DashboardData must read real sTask stats.');
$contains($dashboardData, 'sTaskModel::with', 'DashboardData must read recent tasks from sTaskModel.');
$contains($dashboardData, 'public function cards(): array', 'DashboardData must expose dashboard card data.');
$contains($dashboardData, 'public function recentTasks', 'DashboardData must expose recent task rows.');
$contains($dashboardData, 'public function recentErrors', 'DashboardData must expose recent failed task rows.');
$contains($dashboardData, 'statusColor', 'DashboardData must map status tones/colors.');
$contains($dashboardData, 'performanceCards', 'DashboardData must expose performance cards.');
$contains($dashboardData, 'performanceAlerts', 'DashboardData must expose performance alerts.');
$contains($dashboardData, 'cacheStats', 'DashboardData must expose cache stats.');
$contains($dashboardData, 'sTaskFacade::getPerformanceMetrics', 'DashboardData performance cards must use real metrics.');
$contains($dashboardData, 'sTaskFacade::getCacheStats', 'DashboardData performance cards must use real cache stats.');

$modulePanelView = $read('views/livewire/module-panel.blade.php');
$contains($modulePanelView, '<x-evo::module-tab-shell', 'Module panel view must use the shared EvoUI module tab shell.');
$contains($modulePanelView, '<x-evo::dashboard', 'Dashboard tab must use the shared EvoUI dashboard primitive.');
$contains($modulePanelView, ':cards=', 'Dashboard tab must feed shared dashboard cards.');
$contains($modulePanelView, '<livewire:evo-ui.module-table', 'Tasks tab must use the shared EvoUI module table.');
$contains($modulePanelView, 'preset="stask.tasks"', 'Tasks tab must render the sTask tasks table preset.');
$contains($modulePanelView, 'preset="stask.workers"', 'Workers tab must render the sTask workers table preset.');
$contains($modulePanelView, 'preset="stask.logs"', 'Logs tab must render the sTask logs table preset.');
$contains($modulePanelView, '@if($recentErrorRows->isNotEmpty())', 'Dashboard tab must hide recent error logs when there are no errors.');
$contains($modulePanelView, 'wire:dblclick="openTaskDetails', 'Dashboard recent task rows must open task details on double-click.');
$contains($modulePanelView, 'wire:click.stop="openTaskDetails', 'Dashboard recent task actions must open task details without navigating.');
$contains($modulePanelView, '<x-evo::icon name="eye"', 'Dashboard details action must use an eye icon instead of text-only links.');
$contains($modulePanelView, '<x-evo::modal', 'Dashboard task details must open in an EvoUI modal.');
$notContains($modulePanelView, '<x-evo::card :label="__(\'sTask::global.recent_tasks\')"', 'Recent tasks must not be wrapped in an extra outer card.');
$notContains($modulePanelView, '@lang(\'sTask::global.no_error_logs\')', 'Dashboard must not render an empty recent error block.');
$contains($modulePanelView, ':cards="$performanceCards"', 'Performance tab must render real dashboard cards.');
$contains($modulePanelView, '$performanceAlerts', 'Performance tab must render performance alerts.');
$contains($modulePanelView, '$cacheStats', 'Performance tab must render cache stats.');
$contains($modulePanelView, 'wire:click="clearWorkerCache"', 'Performance tab must expose guarded cache clear action.');
$notContains($modulePanelView, 'stask-evo-ui-010', 'Performance tab must not keep the implementation placeholder.');
$notContains($modulePanelView, '<style', 'Module panel must not add local style blocks.');
$notContains($modulePanelView, '<script', 'Module panel must not add local script blocks.');

$taskRunnerDescriptor = $read('src/Support/TaskRunnerDescriptor.php');
$contains($taskRunnerDescriptor, 'class TaskRunnerDescriptor', 'sTask must expose a declarative task-runner descriptor.');
$contains($taskRunnerDescriptor, 'stask.task-runner.v1', 'Task-runner descriptor must expose a stable contract version.');
$contains($taskRunnerDescriptor, 'progress_url_template', 'Task-runner descriptor must include progress URL template.');
$contains($taskRunnerDescriptor, 'download_url_template', 'Task-runner descriptor must include download URL template.');
$contains($taskRunnerDescriptor, 'terminal_states', 'Task-runner descriptor must include terminal states.');
$contains($taskRunnerDescriptor, 'disable_while', 'Task-runner descriptor must include disable/re-enable rules.');
$contains($taskRunnerDescriptor, 'composer', 'Task-runner descriptor must include Composer update variant.');
$contains($taskRunnerDescriptor, 'artisan', 'Task-runner descriptor must include Artisan variant.');
$contains($taskRunnerDescriptor, 'auto_run_on_command_click', 'Artisan descriptor must preserve click-to-fill without auto-run.');
$contains($taskRunnerDescriptor, 'show_rejection', 'Artisan descriptor must expose security rejection visibility.');

$taskRunnerView = $read('views/widgets/task-runner.blade.php');
$contains($taskRunnerView, 'data-stask-task-runner', 'Task-runner view must expose descriptor payload.');
$contains($taskRunnerView, 'evo-ui-task-runner', 'Task-runner view must use the EvoUI task-runner class boundary.');
$notContains($taskRunnerView, '<script', 'Task-runner view must not embed inline scripts.');
$notContains($taskRunnerView, '<style', 'Task-runner view must not embed inline styles.');

$baseWorker = $read('src/Workers/BaseWorker.php');
$composerWorker = $read('src/Workers/ComposerUpdateWorker.php');
$artisanWorker = $read('src/Workers/ArtisanWorker.php');
$contains($baseWorker, 'TaskRunnerDescriptor::default', 'Default worker widget must use the task-runner descriptor.');
$contains($composerWorker, 'TaskRunnerDescriptor::composer', 'Composer widget must use the task-runner descriptor.');
$contains($artisanWorker, 'TaskRunnerDescriptor::artisan', 'Artisan widget must use the task-runner descriptor.');
$notContains($baseWorker, 'partials.defaultWorkerWidget', 'Base worker must not render the legacy default widget.');
$notContains($composerWorker, 'composerUpdateWorkerWidget', 'Composer worker must not render the legacy inline widget.');
$notContains($artisanWorker, 'artisanWorkerWidget', 'Artisan worker must not render the legacy inline widget.');

$tasksTableConfig = $read('config/tasks/table.php');
$contains($tasksTableConfig, "'key' => 'stask.tasks'", 'Tasks table config must use the stask.tasks preset key.');
$contains($tasksTableConfig, "\\Seiger\\sTask\\Tables\\TasksTableData::class", 'Tasks table config must use the sTask table provider.');
$contains($tasksTableConfig, "'default_sort' => 'created_at_label'", 'Tasks table config default sort must reference a sortable column key.');
$contains($tasksTableConfig, 'sTask::global.search_tasks', 'Tasks table config must expose a localized search placeholder.');
$contains($tasksTableConfig, "'state' => 'worker_id'", 'Tasks table config must include a worker filter.');
$contains($tasksTableConfig, "'state' => 'action'", 'Tasks table config must include an action filter.');
$contains($tasksTableConfig, "'state' => 'status'", 'Tasks table config must include a status filter.');
$contains($tasksTableConfig, "'state' => 'priority'", 'Tasks table config must include a priority filter.');
$contains($tasksTableConfig, "'state' => 'attempts'", 'Tasks table config must include an attempts filter.');
$contains($tasksTableConfig, "'state' => 'created_at'", 'Tasks table config must include a created date filter.');
$contains($tasksTableConfig, "'type' => 'date-range'", 'Tasks table config must include a created date-range filter.');
$contains($tasksTableConfig, "'type' => 'multi-select'", 'Tasks table filters must use standard EvoUI multi-select filters.');
$notContains($tasksTableConfig, "'type' => 'select'", 'Tasks table filters must not use single select filters.');
$notContains($tasksTableConfig, "'default' => 'all'", 'Tasks table filters must not use static all select defaults.');
$contains($tasksTableConfig, "'key' => 'id_label'", 'Tasks table config must include ID label column.');
$contains($tasksTableConfig, "'key' => 'worker_title'", 'Tasks table config must include worker column.');
$contains($tasksTableConfig, "'key' => 'worker_identifier'", 'Tasks table config must include worker identifier column.');
$contains($tasksTableConfig, "'key' => 'status_badge'", 'Tasks table config must include status badge column.');
$contains($tasksTableConfig, "'key' => 'priority_badge'", 'Tasks table config must include priority badge column.');
$contains($tasksTableConfig, "'key' => 'attempts_label'", 'Tasks table config must include attempts column.');
$contains($tasksTableConfig, "'key' => 'started_by'", 'Tasks table config must include started-by column.');
$contains($tasksTableConfig, "'key' => 'message_excerpt'", 'Tasks table config must include message column.');
$contains($tasksTableConfig, "'key' => 'updated_at_label'", 'Tasks table config must include updated column.');
$contains($tasksTableConfig, "'row_dblclick_action' => 'details'", 'Tasks table rows must open the details action modal on double-click.');
$contains($tasksTableConfig, "'method' => 'openActionModal'", 'Tasks table details must open an EvoUI action modal.');
$contains($tasksTableConfig, "'action_argument' => true", 'Tasks table details must pass action key and task id.');
$contains($tasksTableConfig, '$logDetailsModal', 'Tasks table detail modal must reuse the read-only logs detail modal.');
$contains($tasksTableConfig, "'modal' => \$logDetailsModal", 'Tasks table detail action must mount the shared logs modal payload.');
$contains($tasksTableConfig, "'key' => 'details'", 'Tasks table config must include details row action.');

$tasksTableData = $read('src/Tables/TasksTableData.php');
$contains($tasksTableData, 'class TasksTableData', 'TasksTableData provider must exist.');
$contains($tasksTableData, 'public function total(): int', 'TasksTableData must expose total().');
$contains($tasksTableData, 'public function rows(int $page, int $perPage): array', 'TasksTableData must expose rows().');
$contains($tasksTableData, 'public function filterGroups(): array', 'TasksTableData must expose filterGroups().');
$contains($tasksTableData, "sTaskModel::query()->with(['worker', 'user'])", 'TasksTableData must query real task rows with worker and user relations.');
$contains($tasksTableData, "'label' => __('sTask::global.pending')", 'TasksTableData filter groups must return EvoUI label keys.');
$notContains($tasksTableData, "'name' => __('sTask::global.pending')", 'TasksTableData filter groups must not use stale name keys.');
$contains($tasksTableData, "whereIn('identifier'", 'TasksTableData must apply worker multi-select filter through identifiers.');
$contains($tasksTableData, "whereIn('action'", 'TasksTableData must apply action multi-select filter.');
$contains($tasksTableData, "whereIn('status'", 'TasksTableData must apply multi-selected statuses.');
$contains($tasksTableData, "whereIn('priority'", 'TasksTableData must apply multi-selected priorities.');
$contains($tasksTableData, "whereIn('attempts'", 'TasksTableData must apply attempts multi-select filter.');
$contains($tasksTableData, "where('created_at', '>='", 'TasksTableData must apply date range from bound.');
$contains($tasksTableData, "where('created_at', '<='", 'TasksTableData must apply date range to bound.');
$contains($tasksTableData, 'LogsTableData::class', 'TasksTableData detail modal must reuse LogsTableData payloads.');
$contains($tasksTableData, 'public function modalData(int $id): array', 'TasksTableData must expose modal data for the shared detail modal.');
$contains($tasksTableData, 'priorityFilterValue', 'TasksTableData must map numeric multi-select priority ids.');
$contains($tasksTableData, "route('sTask.task.show'", 'TasksTableData rows must link to the existing task detail route.');
$contains($tasksTableData, 'statusColor', 'TasksTableData must map task statuses to badge colors.');
$contains($tasksTableData, 'priorityColor', 'TasksTableData must map task priorities to badge colors.');
$contains($tasksTableData, "'sort_field'", 'TasksTableData must use provider-safe sort_field values from config.');

$logsTableConfig = $read('config/logs/table.php');
$contains($logsTableConfig, "'key' => 'stask.logs'", 'Logs table config must use the stask.logs preset key.');
$contains($logsTableConfig, "\\Seiger\\sTask\\Tables\\LogsTableData::class", 'Logs table config must use the sTask logs provider.');
$contains($logsTableConfig, "'state' => 'worker_id'", 'Logs table config must include worker filter.');
$contains($logsTableConfig, "'state' => 'status'", 'Logs table config must include status filter.');
$contains($logsTableConfig, "'type' => 'date-range'", 'Logs table config must include created date-range filter.');
$contains($logsTableConfig, "'row_dblclick_action' => 'details'", 'Logs table rows must open the details action modal on double-click.');
$contains($logsTableConfig, "'method' => 'openActionModal'", 'Logs table details must open an EvoUI action modal.');
$contains($logsTableConfig, "'action_argument' => true", 'Logs table action modal must pass action key and task id.');
$contains($logsTableConfig, "'readonly' => true", 'Logs table detail modal must be read-only.');
$contains($logsTableConfig, "'submit' => false", 'Logs table detail modal must hide submit.');
$contains($logsTableConfig, "'type' => 'code'", 'Logs table detail modal must render log/meta/result code fields.');

$logsTableData = $read('src/Tables/LogsTableData.php');
$contains($logsTableData, 'class LogsTableData', 'LogsTableData provider must exist.');
$contains($logsTableData, 'public function total(): int', 'LogsTableData must expose total().');
$contains($logsTableData, 'public function rows(int $page, int $perPage): array', 'LogsTableData must expose rows().');
$contains($logsTableData, 'public function filterGroups(): array', 'LogsTableData must expose filterGroups().');
$contains($logsTableData, 'public function modalData(int $id): array', 'LogsTableData must expose task detail modal data.');
$contains($logsTableData, "sTaskModel::query()->with(['worker', 'user'])", 'LogsTableData must query real task rows with worker and user relations.');
$contains($logsTableData, "whereIn('identifier'", 'LogsTableData must apply worker multi-select filter through identifiers.');
$contains($logsTableData, "whereIn('status'", 'LogsTableData must apply multi-selected statuses.');
$contains($logsTableData, "where('created_at', '>='", 'LogsTableData must apply date range from bound.');
$contains($logsTableData, "where('created_at', '<='", 'LogsTableData must apply date range to bound.');
$contains($logsTableData, 'prettyPayload', 'LogsTableData must pretty-print modal meta/result payloads.');
$contains($logsTableData, 'statusColor', 'LogsTableData must map task statuses to badge colors.');

$workersTableConfig = $read('config/workers/table.php');
$contains($workersTableConfig, "'key' => 'stask.workers'", 'Workers table config must use the stask.workers preset key.');
$contains($workersTableConfig, "\\Seiger\\sTask\\Tables\\WorkersTableData::class", 'Workers table config must use the sTask workers provider.');
$contains($workersTableConfig, "'default_sort' => 'position'", 'Workers table config default sort must reference a sortable column key.');
$contains($workersTableConfig, 'sTask::global.search_workers', 'Workers table config must expose a localized search placeholder.');
$contains($workersTableConfig, "'actions' => [", 'Workers table config must duplicate core row actions in the toolbar.');
$contains($workersTableConfig, "'provider' => 'runSelectedWorker'", 'Workers toolbar run action must call provider-backed selected run.');
$contains($workersTableConfig, "'provider' => 'toggleSelectedActive'", 'Workers toolbar toggle action must call provider-backed selected toggle.');
$contains($workersTableConfig, "'state' => 'active'", 'Workers table config must include an active filter.');
$contains($workersTableConfig, "'state' => 'class_exists'", 'Workers table config must include a class_exists filter.');
$contains($workersTableConfig, "'state' => 'hidden'", 'Workers table config must include a hidden filter.');
$contains($workersTableConfig, "'type' => 'multi-select'", 'Workers table filters must use standard EvoUI multi-select filters.');
$notContains($workersTableConfig, "'state' => 'active',\n            'type' => 'select'", 'Workers table active filter must not regress to a single select.');
$notContains($workersTableConfig, "'default' => 'all'", 'Workers table filters must not use static all select defaults.');
$contains($workersTableConfig, "'key' => 'worker_link'", 'Workers table config must include worker link column.');
$contains($workersTableConfig, "'key' => 'description_excerpt'", 'Workers table config must include description column.');
$contains($workersTableConfig, "'key' => 'active_badge'", 'Workers table config must include active badge column.');
$contains($workersTableConfig, "'key' => 'class_exists_badge'", 'Workers table config must include class exists badge column.');
$contains($workersTableConfig, "'key' => 'last_task_badge'", 'Workers table config must include last task badge column.');
$contains($workersTableConfig, "'modal' => [", 'Workers table config must enable the EvoUI edit modal.');
$contains($workersTableConfig, "'row_dblclick' => true", 'Workers table rows must open the worker edit modal on double-click.');
$contains($workersTableConfig, "'method' => 'openEditModal'", 'Workers table config must use standard EvoUI edit action.');
$contains($workersTableConfig, "'method' => 'runRowAction'", 'Workers table config must use the generic EvoUI provider row action for run.');
$contains($workersTableConfig, "'provider' => 'runWorker'", 'Workers table run action must call the sTask provider runWorker method.');
$contains($workersTableConfig, "'disabled_field' => 'run_disabled'", 'Workers table run action must be disabled for non-runnable workers.');
$contains($workersTableConfig, "'method' => 'togglePublished'", 'Workers table config must use the existing EvoUI wire toggle hook.');
$notContains($workersTableConfig, "'type' => 'placeholder'", 'Workers table config must not leave run as a placeholder.');

$workersTableData = $read('src/Tables/WorkersTableData.php');
$contains($workersTableData, 'class WorkersTableData', 'WorkersTableData provider must exist.');
$contains($workersTableData, 'public function total(): int', 'WorkersTableData must expose total().');
$contains($workersTableData, 'public function rows(int $page, int $perPage): array', 'WorkersTableData must expose rows().');
$contains($workersTableData, 'public function filterGroups(): array', 'WorkersTableData must expose filterGroups().');
$contains($workersTableData, 'public function togglePublished(int $id): void', 'WorkersTableData must expose EvoUI togglePublished hook for active state.');
$contains($workersTableData, 'public function modalData(int $id): array', 'WorkersTableData must expose modal edit data.');
$contains($workersTableData, 'public function saveModal(array $data, ?int $id, string $mode): ?int', 'WorkersTableData must save worker edit modal data.');
$contains($workersTableData, 'public function runWorker(int $id, array $action = []): ?int', 'WorkersTableData must expose provider-backed run action.');
$contains($workersTableData, 'public function runSelectedWorker(array $action = [], ?int $id = null): ?int', 'WorkersTableData must expose toolbar run action.');
$contains($workersTableData, 'public function toggleSelectedActive(array $action = [], ?int $id = null): ?int', 'WorkersTableData must expose toolbar active toggle action.');
$contains($workersTableData, "sWorker::query()->withCount('tasks')", 'WorkersTableData must query real workers with task counts.');
$contains($workersTableData, "'label' => __('sTask::global.active')", 'WorkersTableData filter groups must return EvoUI label keys.');
$notContains($workersTableData, "'name' => __('sTask::global.active')", 'WorkersTableData filter groups must not use stale name keys.');
$contains($workersTableData, 'selectedFilterIds', 'WorkersTableData must read numeric multi-select filter ids.');
$contains($workersTableData, "route('sTask.worker.settings'", 'WorkersTableData rows must link to the existing worker settings route.');
$contains($workersTableData, "\$settings['schedule'] = [", 'WorkersTableData edit modal must persist schedule settings.');
$contains($workersTableData, "'manual' => true", 'WorkersTableData run action must create manual tasks.');
$contains($workersTableData, 'launchTaskWorker', 'WorkersTableData run action must trigger the existing worker processor path.');
$contains($workersTableData, 'lastTasksFor', 'WorkersTableData must expose last task status data.');
$contains($workersTableData, 'statusColor', 'WorkersTableData must map last task statuses to badge colors.');

$routes = $read('src/Http/routes.php');
$contains($routes, "Route::middleware(['mgr'])", 'Routes must stay protected by mgr middleware.');
$contains($routes, "Route::prefix('stask')->name('sTask.')", 'Routes must keep stask prefix and sTask route names.');
$contains($routes, "->name('index')", 'Routes must expose dashboard index.');
$contains($routes, "->name('worker.task.run')", 'Routes must expose worker task run API.');
$contains($routes, "->name('task.progress')", 'Routes must expose task progress API.');
$contains($routes, "->name('task.download')", 'Routes must expose task download API.');
$contains($routes, "->name('task.upload')", 'Routes must expose task upload API.');
$contains($routes, "->name('worker.settings')", 'Routes must expose worker settings API.');
$contains($routes, "->name('performance.summary')", 'Routes must expose performance summary API.');
$notContains($routes, 'abort(', 'Routes must not use Laravel abort fallback.');

$tablesMigration = $read('src/Database/Migrations/2025_10_15_000000_create_task_tables.php');
$contains($tablesMigration, "Schema::create('s_workers'", 'Install gate must create s_workers table.');
$contains($tablesMigration, "Schema::create('s_tasks'", 'Install gate must create s_tasks table.');
$contains($tablesMigration, "\$table->json('settings')", 'Workers table must keep JSON settings contract.');
$contains($tablesMigration, "\$table->integer('progress')->default(0)", 'Tasks table must keep progress column contract.');

$permissionMigration = $read('src/Database/Migrations/2025_10_15_000001_add_stask_permissions.php');
$contains($permissionMigration, 'public $withinTransaction = false;', 'Permission migration must run outside Laravel transaction wrapper.');
$contains($permissionMigration, "Schema::hasTable('permissions_groups')", 'Permission migration must guard missing permissions_groups table.');
$contains($permissionMigration, "Schema::hasTable('permissions')", 'Permission migration must guard missing permissions table.');
$contains($permissionMigration, "'name' => 'sTask'", 'Permission group must stay sTask.');
$contains($permissionMigration, "'key', 'stask'", 'Permission lookup must use stask key.');
$contains($permissionMigration, "'key' => 'stask'", 'Permission insert must use stask key.');
$contains($permissionMigration, "sTask::global.permission_access", 'Permission must use localized lang key.');
$contains($permissionMigration, "where('role_id', 1)", 'Permission migration must keep admin assignment compatibility.');

$taskWorker = $read('src/Console/TaskWorker.php');
$contains($taskWorker, 'use Seiger\\sTask\\Models\\sTaskModel;', 'TaskWorker must import sTaskModel.');
$contains($taskWorker, 'use Seiger\\sTask\\Models\\sWorker;', 'TaskWorker must import lowercase-s sWorker model.');
$notContains($taskWorker, 'use Seiger\\sTask\\Models\\Worker;', 'TaskWorker must not import non-existent Worker model.');
$contains($taskWorker, "protected \$signature = 'stask:worker';", 'TaskWorker command signature must stay stask:worker.');

foreach (['en', 'uk', 'fr', 'ru', 'de', 'pl'] as $locale) {
    $lang = $read("lang/{$locale}/global.php");
    $labels = require $root . "/lang/{$locale}/global.php";

    foreach ([
        'module_title',
        'module_description',
        'module_icon',
        'tasks',
        'logs',
        'active_workers',
        'recent_error_logs',
        'search_tasks',
        'search_logs',
        'created_range',
        'task_details',
        'search_workers',
        'priority_low',
        'priority_normal',
        'priority_high',
        'available',
        'missing',
        'visible',
        'hidden',
        'visibility',
        'class_exists',
        'worker_status',
        'last_task',
        'edit_worker',
        'full_worker_settings',
        'permissions_group',
        'permission_access',
    ] as $key) {
        $assert(array_key_exists($key, $labels), "{$locale} lang must define {$key}.");
    }
}

foreach (['en', 'uk', 'de', 'fr', 'pl'] as $locale) {
    $lang = require $root . "/lang/{$locale}/global.php";
    $assert(trim((string)($lang['module_description'] ?? '')) !== '', "{$locale} module_description must not be empty.");
    $assert(mb_strlen((string)$lang['module_description']) <= 140, "{$locale} module_description must fit dDocs source cards.");
    $assert(is_file($root . "/docs/{$locale}/README.md"), "{$locale} public dDocs README must exist.");
}

$phpFiles = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        static function (SplFileInfo $file): bool {
            $path = $file->getPathname();

            if ($file->isDir()) {
                return !str_contains($path, '/vendor/') && !str_contains($path, '/.git/');
            }

            return $file->getExtension() === 'php';
        }
    )
);

foreach ($phpFiles as $file) {
    $relative = str_replace($root . '/', '', $file->getPathname());
    $content = (string) file_get_contents($file->getPathname());

    $notContains($content, 'Seiger\\sTask\\Models\\Worker;', "{$relative} must not reference non-existent Worker model.");
}

if ($failures !== []) {
    fwrite(STDERR, "sTask smoke FAILED\n");

    foreach ($failures as $failure) {
        fwrite(STDERR, "- {$failure}\n");
    }

    exit(1);
}

echo "sTask smoke OK ({$tests} assertions)\n";
