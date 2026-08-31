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

$appearsBefore = static function (string $haystack, string $first, string $second, string $message) use ($assert): void {
    $firstPosition = strpos($haystack, $first);
    $secondPosition = strpos($haystack, $second);

    $assert($firstPosition !== false && $secondPosition !== false && $firstPosition < $secondPosition, $message);
};

$composer = json_decode($read('composer.json'), true);
$assert(is_array($composer), 'composer.json must be valid JSON.');
$assert(($composer['name'] ?? null) === 'seiger/stask', 'composer package name must stay seiger/stask.');
$assert(($composer['require']['evolution-cms/evolution'] ?? null) === '^3.5.7', 'sTask must require the Evolution CMS 3.5.7 baseline.');
$assert(($composer['require']['evolution-cms/evo-ui'] ?? null) === '^1.2', 'sTask must require the EvoUI modal field runtime boundary.');
$assert(($composer['scripts']['test'] ?? null) === 'php tests/run.php', 'composer test must run the package smoke suite.');
$assert(
    in_array('Seiger\\sTask\\sTaskServiceProvider', $composer['extra']['laravel']['providers'] ?? [], true),
    'Laravel provider must stay registered in composer extra.'
);

$docsRoot = $root . '/docs';
$docsReadme = $read('docs/README.md');
$ukrainianDocs = [
    'README.md',
    '01-getting-started/installation.md',
    '01-getting-started/quick-start.md',
    '02-concepts/architecture-and-lifecycle.md',
    '02-concepts/schedules.md',
    '02-concepts/supervisor.md',
    '03-manager/interface.md',
    '04-development/public-api.md',
    '04-development/custom-worker.md',
    '04-development/routes-and-progress.md',
    '05-operations/production.md',
    '05-operations/troubleshooting.md',
    '05-operations/upgrade-1-to-2.md',
    '06-reference/configuration.md',
    '06-reference/database.md',
    '06-reference/cli-statuses-routes.md',
    '06-reference/faq.md',
];
$documentationScreenshots = [
    'docs/assets/screenshots/stask-dashboard.png',
    'docs/assets/screenshots/stask-tasks.png',
    'docs/assets/screenshots/stask-workers.png',
    'docs/assets/screenshots/stask-logs.png',
    'docs/assets/screenshots/stask-statistics.png',
];

foreach ($documentationScreenshots as $screenshot) {
    $fullPath = $root . '/' . $screenshot;
    $assert(is_file($fullPath), "Documentation screenshot {$screenshot} must exist.");
    $assert(is_file($fullPath) && filesize($fullPath) > 1024, "Documentation screenshot {$screenshot} must not be empty.");
}

foreach (['en', 'uk', 'ru', 'pl', 'de', 'fr'] as $locale) {
    $contains($docsReadme, "({$locale}/README.md)", "Root docs README must link {$locale} docs.");

    $actualLocaleDocs = [];
    $localeIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$docsRoot}/{$locale}"));
    foreach ($localeIterator as $fileInfo) {
        if ($fileInfo->isFile() && $fileInfo->getExtension() === 'md') {
            $actualLocaleDocs[] = ltrim(str_replace('\\', '/', substr($fileInfo->getPathname(), strlen("{$docsRoot}/{$locale}"))), '/');
        }
    }
    $expectedLocaleDocs = $ukrainianDocs;
    sort($actualLocaleDocs);
    sort($expectedLocaleDocs);
    $assert($actualLocaleDocs === $expectedLocaleDocs, "{$locale} documentation tree must match the canonical Ukrainian tree.");

    foreach ($ukrainianDocs as $file) {
        $path = "docs/{$locale}/{$file}";
        $content = $read($path);
        $assert(trim($content) !== '', "{$path} must not be empty.");

        $canonicalContent = $read("docs/uk/{$file}");
        $assert(
            substr_count($content, '```') === substr_count($canonicalContent, '```'),
            "{$path} must preserve the canonical code fence structure."
        );
        preg_match_all('/^#{1,6}\s+/m', $content, $localizedHeadings);
        preg_match_all('/^#{1,6}\s+/m', $canonicalContent, $canonicalHeadings);
        $assert(
            count($localizedHeadings[0]) === count($canonicalHeadings[0]),
            "{$path} must preserve the canonical heading structure."
        );
        preg_match_all('/!?\[[^\]]*\]\([^)]+\)/', $content, $localizedLinks);
        preg_match_all('/!?\[[^\]]*\]\([^)]+\)/', $canonicalContent, $canonicalLinks);
        $assert(
            count($localizedLinks[0]) === count($canonicalLinks[0]),
            "{$path} must preserve the canonical Markdown link and image structure."
        );
        $notContains($content, '! [', "{$path} must not contain a detached Markdown image marker.");
    }

    $localeReadme = $read("docs/{$locale}/README.md");
    foreach (array_slice($ukrainianDocs, 1) as $file) {
        $contains($localeReadme, "({$file})", "{$locale} README must link {$file}.");
    }
}

foreach (['pages', 'i18n'] as $legacyTree) {
    $assert(!is_dir("{$docsRoot}/{$legacyTree}"), "Historical Docusaurus {$legacyTree} must stay outside the dDocs source tree.");
}

$docsManifest = json_decode($read('docs/docs.json'), true);
$assert(is_array($docsManifest), 'docs/docs.json must be valid JSON.');
$assert(($docsManifest['schemaVersion'] ?? null) === 1, 'Docs manifest must use schema version 1.');
$assert(($docsManifest['package'] ?? null) === 'seiger/stask', 'Docs manifest must identify seiger/stask.');
$assert(($docsManifest['title'] ?? null) === 'sTask', 'Docs manifest must expose the canonical sTask title.');
$assert(($docsManifest['icon'] ?? null) === 'tabler-progress-check', 'Docs manifest must expose the thematic Tabler icon.');
$assert(($docsManifest['canonicalLocale'] ?? null) === 'uk', 'Docs manifest must identify Ukrainian as canonical locale.');
foreach (['uk', 'en', 'ru', 'pl', 'de', 'fr'] as $locale) {
    $assert(($docsManifest['locales'][$locale] ?? null) === 'complete', "Docs manifest must mark {$locale} documentation complete.");
}

foreach (($docsManifest['entrypoints'] ?? []) as $name => $entrypoint) {
    $path = (string)($entrypoint['path'] ?? '');
    $assert($path !== '' && is_file($docsRoot . '/' . $path), "Docs manifest entrypoint {$name} must resolve.");
}

$tasksUiDocs = $read('docs/uk/03-manager/interface.md');
$contains($tasksUiDocs, 'Priority та Attempts не є актуальними колонками або фільтрами', 'Manager docs must explicitly reject removed Priority/Attempts UI controls.');
$contains($tasksUiDocs, '`player-eject`', 'Manager docs must describe the emergency stop icon.');
$contains($tasksUiDocs, '`system`', 'Manager docs must describe the system user filter.');
$contains($tasksUiDocs, 'HTTP polling', 'Manager docs must describe the real live progress transport.');
$contains($tasksUiDocs, 'подвійний клік у всіх рядкових представленнях', 'Manager docs must guarantee double-click behavior for every row-based surface.');
$notContains($tasksUiDocs, 'SSE', 'Manager docs must not present SSE as the live transport.');
$notContains($tasksUiDocs, 'WebSocket', 'Manager docs must not present WebSocket as the live transport.');

$installationDocs = $read('docs/uk/01-getting-started/installation.md');
$notContains($installationDocs, '`down()` міграції supervisor', 'Installation rollback guidance must not depend on a split migration layout.');
$notContains($installationDocs, '`^', 'Installation docs must not expose caret constraints that dDocs renders as superscript markup.');
$contains($installationDocs, '<code>&#94;3.5.7</code>', 'Installation docs must render the exact Evolution CMS Composer constraint safely.');

$russianQuickStart = $read('docs/ru/01-getting-started/quick-start.md');
$notContains($russianQuickStart, '`^', 'Russian quick start must not expose caret constraints that dDocs renders as superscript markup.');

$faqDocs = $read('docs/uk/06-reference/faq.md');
foreach ([
    'Чому Priority/Attempts не видно?',
    'Чому Статистика показує 0 для duration/memory?',
    'Чому sTask docs не видно в dDocs, хоча вони є на GitHub?',
    'Чи треба додавати sTask у `extra_docs_roots`?',
] as $removedQuestion) {
    $notContains($faqDocs, $removedQuestion, "FAQ must not restore removed question: {$removedQuestion}");
}

$docsIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($docsRoot));
foreach ($docsIterator as $fileInfo) {
    if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'md') {
        continue;
    }

    $relativePath = 'docs/' . ltrim(str_replace($docsRoot, '', $fileInfo->getPathname()), '/');
    $content = (string) file_get_contents($fileInfo->getPathname());
    $assert(substr_count($content, '```') % 2 === 0, "Markdown code fences in {$relativePath} must be balanced.");
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

    preg_match_all('/!\[[^\]]*\]\(([^)]+\.(?:png|jpe?g|webp|svg))\)/i', $content, $imageMatches);

    foreach ($imageMatches[1] as $target) {
        $targetPath = realpath(dirname($fileInfo->getPathname()) . '/' . $target);
        $assert(
            $targetPath !== false && str_starts_with($targetPath, $docsRoot) && is_file($targetPath),
            "Markdown image {$target} in {$relativePath} must resolve inside docs."
        );
    }
}

$provider = $read('src/sTaskServiceProvider.php');
$contains($provider, "mergeConfigFrom(dirname(__DIR__) . '/config/sTaskCheck.php', 'cms.settings')", 'Provider must merge sTask CMS settings.');
$contains($provider, "loadMigrationsFrom(dirname(__DIR__) . '/database/migrations')", 'Provider must load package migrations from the standard package path.');
$contains($provider, "loadTranslationsFrom(dirname(__DIR__) . '/lang', 'sTask')", 'Provider must load sTask translations namespace.');
$contains($provider, "registerFormField('worker-settings', 'sTask::fields.worker-settings')", 'Provider must register the worker settings modal field.');
$contains($provider, "registerFormField('worker-files', 'sTask::fields.worker-files')", 'Provider must register the generated files modal field.');
$contains($provider, "loadViewsFrom(dirname(__DIR__) . '/views', 'sTask')", 'Provider must load sTask views namespace.');
$contains($provider, 'registerManagerPermissionLexicon()', 'Provider must bridge package permission labels into the manager lexicon.');
$contains($provider, "afterResolving('ManagerTheme'", 'Provider must defer manager lexicon registration until ManagerTheme is resolved.');
$notContains($provider, "make('ManagerTheme')", 'Provider must not resolve ManagerTheme before system settings are loaded.');
$contains($provider, "setLexicon('seiger_packages'", 'Provider must register the shared Seiger packages manager label.');
$contains($provider, "setLexicon('sTask::global.permission_access'", 'Provider must register the localized sTask permission label.');
$contains($provider, "mergeConfigFrom(dirname(__DIR__) . '/config/tasks/table.php', 'stask.tasks.table')", 'Provider must merge the sTask EvoUI tasks table preset.');
$contains($provider, "mergeConfigFrom(dirname(__DIR__) . '/config/workers/table.php', 'stask.workers.table')", 'Provider must merge the sTask EvoUI workers table preset.');
$contains($provider, "mergeConfigFrom(dirname(__DIR__) . '/config/logs/table.php', 'stask.logs.table')", 'Provider must merge the sTask EvoUI logs table preset.');
$contains($provider, 'use EvoUI\\EvoUI;', 'Provider must depend on EvoUI instead of the underlying reactive runtime.');
$contains($provider, '$this->registerEvoUIComponents();', 'Provider must declare the sTask component through EvoUI.');
$contains($provider, "'stask.module-panel'", 'Provider must keep the public sTask component name.');
$contains($provider, '\\Seiger\\sTask\\Components\\ModulePanel::class', 'Provider must register the EvoUI-owned component implementation.');
$contains($provider, 'if (!$this->app->bound(EvoUI::class))', 'Provider must allow first package discovery to finish before EvoUI is registered.');
$notContains($provider, 'Livewire\\', 'Provider must not depend directly on Livewire classes.');
$notContains($provider, 'Livewire::', 'Provider must not call the Livewire runtime directly.');
$notContains($provider, 'discoverWorkers', 'Provider must not discover workers during every boot.');
$notContains($provider, 'WorkerDiscovery::class', 'Provider must keep worker discovery behind an explicit workers-table action.');
$contains($provider, '$this->loadRoutes();', 'Provider must load manager routes.');
$contains($provider, '$this->loadPluginsFrom(dirname(__DIR__) . \'/plugins/\');', 'Provider must load Evolution plugin bridge.');
$contains($provider, '$this->app->registerModule(', 'Provider must register the Evolution manager module.');
$contains($provider, "module_title", 'Manager registration must use localized module_title.');
$contains($provider, "module_icon", 'Manager registration must use localized module_icon.');
$contains($provider, 'TaskWorker::class', 'Provider must register/schedule TaskWorker.');
$notContains($provider, 'abort(', 'Provider must not use Laravel abort fallback in manager boot path.');

if (!class_exists('EvolutionCMS\\ServiceProvider', false)) {
    eval('namespace EvolutionCMS; class ServiceProvider { public function __construct(public object $app) {} }');
}

require_once $root . '/src/sTaskServiceProvider.php';

$registerComponents = new ReflectionMethod(\Seiger\sTask\sTaskServiceProvider::class, 'registerEvoUIComponents');

$discoveryApp = new class {
    public int $makeCalls = 0;

    public function bound(string $abstract): bool
    {
        return false;
    }

    public function make(string $abstract): object
    {
        $this->makeCalls++;

        throw new RuntimeException('EvoUI must not resolve during transitional discovery.');
    }
};

$registerComponents->invoke(new \Seiger\sTask\sTaskServiceProvider($discoveryApp));
$assert($discoveryApp->makeCalls === 0, 'Transitional package discovery must skip EvoUI resolution when its provider is not registered.');

$componentRegistry = new class {
    /** @var array<string, class-string> */
    public array $components = [];

    /** @var array<string, string> */
    public array $formFields = [];

    public function registerComponent(string $name, string $component): void
    {
        $this->components[$name] = $component;
    }

    public function registerFormField(string $type, string $view): void
    {
        $this->formFields[$type] = $view;
    }
};

$runtimeApp = new class($componentRegistry) {
    public function __construct(private object $componentRegistry)
    {
    }

    public function bound(string $abstract): bool
    {
        return $abstract === \EvoUI\EvoUI::class;
    }

    public function make(string $abstract): object
    {
        return $this->componentRegistry;
    }
};

$registerComponents->invoke(new \Seiger\sTask\sTaskServiceProvider($runtimeApp));
$assert(
    ($componentRegistry->components['stask.module-panel'] ?? null) === \Seiger\sTask\Components\ModulePanel::class,
    'sTask must declare its module panel through the registered EvoUI runtime.'
);
$assert(
    ($componentRegistry->formFields['worker-settings'] ?? null) === 'sTask::fields.worker-settings'
    && ($componentRegistry->formFields['worker-files'] ?? null) === 'sTask::fields.worker-files',
    'sTask must register its worker modal fields through the EvoUI runtime.'
);

$module = $read('module/sTaskModule.php');
$contains($module, 'IN_MANAGER_MODE', 'Module entry must keep manager-mode guard.');
$contains($module, "die('No access')", 'Module entry must keep Evolution-compatible no-access guard.');
$contains($module, 'app(sTaskController::class)->index()->render()', 'Module entry must render sTask controller index.');

$controller = $read('src/Controllers/sTaskController.php');
$contains($controller, "view('sTask::module.shell'", 'Controller index must render the EvoUI shell.');
$contains($controller, "view('sTask::module.task-detail'", 'Controller task detail must render the EvoUI task detail shell.');
$contains($controller, "'dashboard'", 'Controller must define dashboard module tab.');
$contains($controller, "'tasks'", 'Controller must define tasks module tab.');
$contains($controller, "'icon' => 'clipboard-list'", 'Tasks tab must use the supported thematic clipboard-list icon.');
$contains($controller, "'workers'", 'Controller must define workers module tab.');
$contains($controller, "'logs'", 'Controller must define logs module tab.');
$contains($controller, "'performance'", 'Controller must define performance module tab.');
$notContains($controller, 'use Seiger\\sTask\\Models\\sWorker as sWorker;', 'Controller must not import worker model just for removed legacy settings flow.');
$notContains($controller, 'use Seiger\\sTask\\Models\\Worker;', 'Controller must not import the non-existent Worker model.');
$contains($controller, "redirect()->route('sTask.index', ['get' => 'workers'])", 'Legacy workers route must redirect to the EvoUI workers tab.');
$notContains($controller, "view('sTask::workers'", 'Controller must not render the legacy workers page as an active surface.');
$notContains($controller, "view('sTask::workerSettings'", 'Controller must not render the legacy worker settings page as an active surface.');
$notContains($controller, 'function workerSettings', 'Controller must not expose the removed legacy worker settings page.');
$notContains($controller, 'function saveWorkerSettings', 'Controller must not expose the removed legacy worker settings form.');

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

$modulePanel = $read('src/Components/ModulePanel.php');
$contains($modulePanel, 'class ModulePanel extends Component', 'sTask ModulePanel must be an EvoUI component.');
$contains($modulePanel, 'use EvoUI\\Components\\Component;', 'sTask ModulePanel must extend the EvoUI component boundary.');
$notContains($modulePanel, 'Livewire\\', 'sTask ModulePanel must not depend directly on Livewire classes.');
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
$contains($dashboardData, "'value' => niceCount(\$value)", 'Dashboard cards must format task and worker totals with niceCount.');
$contains($dashboardData, 'public function recentTasks', 'DashboardData must expose recent task rows.');
$contains($dashboardData, 'public function recentErrors', 'DashboardData must expose recent failed task rows.');
$contains($dashboardData, 'statusColor', 'DashboardData must map status tones/colors.');
$contains($dashboardData, 'performanceCards', 'DashboardData must expose performance cards.');
$contains($dashboardData, "metricCard('average_duration', 'stopwatch'", 'Average-duration card must use the thematic stopwatch icon.');
$contains($dashboardData, 'performanceAlerts', 'DashboardData must expose performance alerts.');
$contains($dashboardData, 'cacheStats', 'DashboardData must expose cache stats.');
$contains($dashboardData, 'sTaskFacade::getPerformanceMetrics', 'DashboardData performance cards must use real metrics.');
$contains($dashboardData, 'sTaskFacade::getCacheStats', 'DashboardData performance cards must use real cache stats.');
$contains($dashboardData, "return \$progress . '% · ' . \$eta;", 'Dashboard progress labels must separate ETA with a readable middle dot.');

$modulePanelView = $read('views/components/module-panel.blade.php');
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
$notContains($baseWorker, "'progress' => 100", 'BaseWorker markFinished must not fake completed task progress.');
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
$contains($tasksTableConfig, "'default_sort' => 'id_label'", 'Tasks table config default sort must reference the sortable ID column.');
$contains($tasksTableConfig, 'sTask::global.search_tasks', 'Tasks table config must expose a localized search placeholder.');
$contains($tasksTableConfig, "'state' => 'worker_id'", 'Tasks table config must include a worker filter.');
$contains($tasksTableConfig, "'state' => 'action'", 'Tasks table config must include an action filter.');
$contains($tasksTableConfig, "'state' => 'status'", 'Tasks table config must include a status filter.');
$contains($tasksTableConfig, "'state' => 'started_by'", 'Tasks table config must include a user filter.');
$appearsBefore($tasksTableConfig, "'state' => 'started_by'", "'state' => 'created_at'", 'Tasks user filter must precede the created period filter.');
$notContains($tasksTableConfig, "'state' => 'priority'", 'Tasks table config must not restore the removed priority filter.');
$notContains($tasksTableConfig, "'state' => 'attempts'", 'Tasks table config must not restore the removed attempts filter.');
$contains($tasksTableConfig, "'state' => 'created_at'", 'Tasks table config must include a created date filter.');
$contains($tasksTableConfig, "'type' => 'date-range'", 'Tasks table config must include a created date-range filter.');
$contains($tasksTableConfig, "'type' => 'multi-select'", 'Tasks table filters must use standard EvoUI multi-select filters.');
$notContains($tasksTableConfig, "'type' => 'select'", 'Tasks table filters must not use single select filters.');
$notContains($tasksTableConfig, "'default' => 'all'", 'Tasks table filters must not use static all select defaults.');
$contains($tasksTableConfig, "'key' => 'id_label'", 'Tasks table config must include ID label column.');
$contains($tasksTableConfig, "'key' => 'worker_title'", 'Tasks table config must include worker column.');
$notContains($tasksTableConfig, "'key' => 'worker_identifier'", 'Tasks table must keep the worker identifier out of the visible columns.');
$contains($tasksTableConfig, "'key' => 'status_badge'", 'Tasks table config must include status badge column.');
$notContains($tasksTableConfig, "'key' => 'priority_badge'", 'Tasks table config must not restore the removed priority column.');
$notContains($tasksTableConfig, "'key' => 'attempts_label'", 'Tasks table config must not restore the removed attempts column.');
$contains($tasksTableConfig, "'key' => 'started_by'", 'Tasks table config must include started-by column.');
$contains($tasksTableConfig, "'key' => 'message_text'", 'Tasks table config must include the responsive markdown message column.');
$notContains($tasksTableConfig, "'key' => 'updated_at_label'", 'Tasks table config must not restore the removed updated column.');
$contains($tasksTableConfig, "'row_dblclick_action' => 'details'", 'Tasks table rows must open the details action modal on double-click.');
$contains($tasksTableConfig, "'method' => 'openActionModal'", 'Tasks table details must open an EvoUI action modal.');
$contains($tasksTableConfig, "'action_argument' => true", 'Tasks table details must pass action key and task id.');
$contains($tasksTableConfig, '$logDetailsModal', 'Tasks table detail modal must reuse the read-only logs detail modal.');
$contains($tasksTableConfig, "'modal' => \$logDetailsModal", 'Tasks table detail action must mount the shared logs modal payload.');
$contains($tasksTableConfig, "'key' => 'details'", 'Tasks table config must include details row action.');
$contains($tasksTableConfig, "'key' => 'emergency_stop'", 'Tasks table config must include an emergency stop row action.');
$contains($tasksTableConfig, "'provider' => 'emergencyStopTask'", 'Tasks emergency stop action must call the provider method.');
$contains($tasksTableConfig, "'disabled_field' => 'emergency_stop_disabled'", 'Tasks emergency stop action must be disabled for final tasks.');

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
$contains($tasksTableData, "whereIn('started_by'", 'TasksTableData must filter tasks by the selected users.');
$contains($tasksTableData, "'id' => -1", 'Tasks user filter must expose the system starter option.');
$contains($tasksTableData, "orWhereNull('started_by')", 'Tasks system filter must include tasks without a starter id.');
$contains($tasksTableData, "orWhere('started_by', '<=', 0)", 'Tasks system filter must include zero-valued starter ids.');
$contains($tasksTableData, "get(['id', 'identifier', 'class'])", 'Tasks worker filter must load the class required by the title accessor.');
$contains($tasksTableData, "trim((string)\$worker->title) !== ''", 'Tasks worker filter must prefer worker titles over identifiers.');
$contains($tasksTableData, "sortBy(fn (array \$option): string => mb_strtolower(\$option['label']))", 'Tasks worker filter must sort the resolved display titles in memory.');
$contains($tasksTableData, 'protected function userOptions(): array', 'TasksTableData must expose user filter options.');
$contains($tasksTableData, "whereIn('priority'", 'TasksTableData must apply multi-selected priorities.');
$contains($tasksTableData, "whereIn('attempts'", 'TasksTableData must apply attempts multi-select filter.');
$contains($tasksTableData, "where('created_at', '>='", 'TasksTableData must apply date range from bound.');
$contains($tasksTableData, "where('created_at', '<='", 'TasksTableData must apply date range to bound.');
$contains($tasksTableData, 'LogsTableData::class', 'TasksTableData detail modal must reuse LogsTableData payloads.');
$contains($tasksTableData, 'public function modalData(int $id): array', 'TasksTableData must expose modal data for the shared detail modal.');
$contains($tasksTableData, 'public function emergencyStopTask(int $id, array $action = []): bool', 'TasksTableData must expose emergency stop row action.');
$contains($tasksTableData, 'sTaskModel::activeStatuses()', 'TasksTableData emergency stop must only affect active tasks.');
$contains($tasksTableData, "__('sTask::global.task_emergency_stopped')", 'TasksTableData emergency stop must leave an auditable message.');
$contains($tasksTableData, 'priorityFilterValue', 'TasksTableData must map numeric multi-select priority ids.');
$contains($tasksTableData, "route('sTask.task.show'", 'TasksTableData rows must link to the existing task detail route.');
$contains($tasksTableData, 'statusColor', 'TasksTableData must map task statuses to badge colors.');
$contains($tasksTableData, 'priorityColor', 'TasksTableData must map task priorities to badge colors.');
$contains($tasksTableData, "'sort_field'", 'TasksTableData must use provider-safe sort_field values from config.');
$contains($tasksTableData, "return \$progress . '% · ' . \$eta;", 'Task table progress labels must separate ETA with a readable middle dot.');

$logsTableConfig = $read('config/logs/table.php');
$contains($logsTableConfig, "'key' => 'stask.logs'", 'Logs table config must use the stask.logs preset key.');
$contains($logsTableConfig, "\\Seiger\\sTask\\Tables\\LogsTableData::class", 'Logs table config must use the sTask logs provider.');
$contains($logsTableConfig, "'state' => 'worker_id'", 'Logs table config must include worker filter.');
$contains($logsTableConfig, "'state' => 'status'", 'Logs table config must include status filter.');
$contains($logsTableConfig, "'state' => 'started_by'", 'Logs table config must include user filter.');
$appearsBefore($logsTableConfig, "'state' => 'started_by'", "'state' => 'created_at'", 'Logs user filter must precede the created period filter.');
$contains($logsTableConfig, "'type' => 'date-range'", 'Logs table config must include created date-range filter.');
$appearsBefore($logsTableConfig, "'key' => 'worker_title'", "'key' => 'worker_identifier'", 'Logs table identifier column must follow the worker column.');
$appearsBefore($logsTableConfig, "'key' => 'worker_identifier'", "'key' => 'action'", 'Logs table identifier column must precede the action column.');
$appearsBefore($logsTableConfig, "'key' => 'progress_label'", "'key' => 'started_by'", 'Logs table started-by column must follow progress.');
$appearsBefore($logsTableConfig, "'key' => 'started_by'", "'key' => 'created_at_label'", 'Logs table started-by column must precede created time.');
$contains($logsTableConfig, "'key' => 'duration_label'", 'Logs table config must include execution duration.');
$contains($logsTableConfig, "'sort_field' => 'duration'", 'Logs execution duration must be sortable by its computed field.');
$appearsBefore($logsTableConfig, "'key' => 'updated_at_label'", "'key' => 'duration_label'", 'Logs execution duration must be the final data column before row actions.');
$contains($logsTableConfig, "'row_dblclick_action' => 'details'", 'Logs table rows must open the details action modal on double-click.');
$contains($logsTableConfig, "'method' => 'openActionModal'", 'Logs table details must open an EvoUI action modal.');
$contains($logsTableConfig, "'action_argument' => true", 'Logs table action modal must pass action key and task id.');
$contains($logsTableConfig, "'readonly' => true", 'Logs table detail modal must be read-only.');
$contains($logsTableConfig, "'submit' => false", 'Logs table detail modal must hide submit.');
$contains($logsTableConfig, "'type' => 'code'", 'Logs table detail modal must render log/meta/result code fields.');

$logsTableData = $read('src/Tables/LogsTableData.php');
$taskModel = $read('src/Models/sTaskModel.php');
$contains($logsTableData, 'class LogsTableData', 'LogsTableData provider must exist.');
$contains($logsTableData, 'public function total(): int', 'LogsTableData must expose total().');
$contains($logsTableData, 'public function rows(int $page, int $perPage): array', 'LogsTableData must expose rows().');
$contains($logsTableData, 'public function filterGroups(): array', 'LogsTableData must expose filterGroups().');
$contains($logsTableData, 'public function modalData(int $id): array', 'LogsTableData must expose task detail modal data.');
$contains($logsTableData, "sTaskModel::query()->with(['worker', 'user'])", 'LogsTableData must query real task rows with worker and user relations.');
$contains($logsTableData, "whereIn('identifier'", 'LogsTableData must apply worker multi-select filter through identifiers.');
$contains($logsTableData, "get(['id', 'identifier', 'class'])", 'Logs worker filter must load the class required by the title accessor.');
$contains($logsTableData, "trim((string)\$worker->title) !== ''", 'Logs worker filter must prefer worker titles over identifiers.');
$contains($logsTableData, "sortBy(fn (array \$option): string => mb_strtolower(\$option['label']))", 'Logs worker filter must sort the resolved display titles in memory.');
$contains($logsTableData, "whereIn('status'", 'LogsTableData must apply multi-selected statuses.');
$contains($logsTableData, "'id' => -1", 'Logs user filter must expose the system starter option.');
$contains($logsTableData, "whereIn('started_by'", 'LogsTableData must filter tasks by selected users.');
$contains($logsTableData, "orWhereNull('started_by')", 'Logs system filter must include tasks without a starter id.');
$contains($logsTableData, "niceEta((float)\$task->duration)", 'LogsTableData must format execution duration with niceEta().');
$contains($taskModel, "max(0, (int)\$this->start_at->diffInSeconds(\$end))", 'Task duration must be calculated forward from start and never become negative.');
$contains($logsTableData, 'protected function orderByDuration', 'LogsTableData must provide computed duration sorting.');
$contains($logsTableData, "'pgsql' => 'COALESCE(EXTRACT(EPOCH", 'Logs duration sorting must support PostgreSQL.');
$contains($logsTableData, "'mysql', 'mariadb' => 'COALESCE(TIMESTAMPDIFF", 'Logs duration sorting must support MySQL and MariaDB.');
$contains($logsTableData, "'sqlite' => 'COALESCE((julianday", 'Logs duration sorting must support SQLite.');
$contains($logsTableData, "where('created_at', '>='", 'LogsTableData must apply date range from bound.');
$contains($logsTableData, "where('created_at', '<='", 'LogsTableData must apply date range to bound.');
$contains($logsTableData, 'prettyPayload', 'LogsTableData must pretty-print modal meta/result payloads.');
$contains($logsTableData, 'statusColor', 'LogsTableData must map task statuses to badge colors.');

$workersTableConfig = $read('config/workers/table.php');
$contains($workersTableConfig, "'key' => 'stask.workers'", 'Workers table config must use the stask.workers preset key.');
$contains($workersTableConfig, "\\Seiger\\sTask\\Tables\\WorkersTableData::class", 'Workers table config must use the sTask workers provider.');
$contains($workersTableConfig, "'default_sort' => 'position'", 'Workers table config default sort must reference a sortable column key.');
$contains($workersTableConfig, "'row_states' => [", 'Workers table config must expose row states.');
$contains($workersTableConfig, "'field' => 'active'", 'Workers table row states must key inactive styling by active flag.');
$contains($workersTableConfig, "'class' => 'is-dimmed'", 'Workers table row states must dim inactive workers.');
$contains($workersTableConfig, 'sTask::global.search_workers', 'Workers table config must expose a localized search placeholder.');
$contains($workersTableConfig, "'actions' => [", 'Workers table config must duplicate core row actions in the toolbar.');
$contains($workersTableConfig, "'provider' => 'refreshWorkerRegistry'", 'Workers toolbar must expose manual worker registry refresh.');
$contains($workersTableConfig, 'sTask::global.refresh_worker_registry', 'Workers toolbar registry refresh must use localized label.');
$contains($workersTableConfig, "color: var(--evo-ui-muted);", 'Workers toolbar registry refresh must stay visually muted.');
$contains($workersTableConfig, 'runTableAction', 'Workers table wire targets must allow provider-backed toolbar actions.');
$contains($workersTableConfig, 'toggleVisibility', 'Workers table wire targets must allow visibility toggles.');
$contains($workersTableConfig, "'provider' => 'runSelectedWorker'", 'Workers toolbar run action must call provider-backed selected run.');
$contains($workersTableConfig, "'attributes_provider' => 'runSelectedWorkerAttributes'", 'Workers toolbar run action must be disabled for inactive workers.');
$contains($workersTableConfig, "'provider' => 'toggleSelectedActive'", 'Workers toolbar toggle action must call provider-backed selected toggle.');
$contains($workersTableConfig, "'state' => 'active'", 'Workers table config must include an active filter.');
$contains($workersTableConfig, "'state' => 'class_exists'", 'Workers table config must include a class_exists filter.');
$contains($workersTableConfig, "'state' => 'hidden'", 'Workers table config must include a hidden filter.');
$contains($workersTableConfig, "'type' => 'multi-select'", 'Workers table filters must use standard EvoUI multi-select filters.');
$notContains($workersTableConfig, "'state' => 'active',\n            'type' => 'select'", 'Workers table active filter must not regress to a single select.');
$notContains($workersTableConfig, "'default' => 'all'", 'Workers table filters must not use static all select defaults.');
$contains($workersTableConfig, "'key' => 'worker_title'", 'Workers table config must include worker title column.');
$contains($workersTableConfig, "'key' => 'description_excerpt'", 'Workers table config must include description column.');
$contains($workersTableConfig, "'key' => 'schedule_display', 'type' => 'chips'", 'Workers table config must combine the schedule and Supervisor state in one column.');
$notContains($workersTableConfig, "'key' => 'supervisor_state_badge'", 'Workers table config must not expose a noisy standalone Supervisor state column.');
$contains($workersTableConfig, "'key' => 'last_action_label'", 'Workers table config must include last action column.');
$contains($workersTableConfig, "'key' => 'last_run_at_label'", 'Workers table config must include last run column.');
$contains($workersTableConfig, "sTask::global.default_position", 'Workers edit modal must label position as default position.');
$contains($workersTableConfig, "sTask::global.additional_settings", 'Workers edit modal must expose additional settings.');
$contains($workersTableConfig, "'visible_if_all' => [", 'Workers edit modal must support schedule fields that depend on multiple conditions.');
$contains($workersTableConfig, "'visible_if_any' => [", 'Workers edit modal must support schedule fields that depend on alternative schedule types.');
$contains($workersTableConfig, "'options_provider' => 'scheduleFrequencyOptions'", 'Workers edit modal must reuse one frequency field for periodic and regular schedules.');
$contains($workersTableConfig, "'options_provider' => 'scheduleTypeOptions'", 'Workers edit modal must load schedule types from the worker capability provider.');
$notContains($workersTableConfig, "['value' => 'supervisor', 'label' => 'sTask::global.schedule_supervisor']", 'Workers modal config must not offer Supervisor statically to every worker.');
$contains($workersTableConfig, "'name' => 'schedule_hourly_minute'", 'Workers hourly periodic schedule must render a dedicated minute field.');
$contains($workersTableConfig, "'addon_prefix' => '*:'", 'Workers hourly periodic minute field must render the cron-like hour add-on.');
$contains($workersTableConfig, "['field' => 'schedule_type', 'value' => 'once']", 'Workers once schedule must show only one-time datetime fields.');
$contains($workersTableConfig, "['field' => 'schedule_type', 'value' => 'periodic']", 'Workers periodic schedule must show periodic frequency/time fields.');
$contains($workersTableConfig, "['field' => 'schedule_type', 'value' => 'regular']", 'Workers regular schedule must show bounded frequency fields.');
$notContains($workersTableConfig, "'name' => 'schedule_interval'", 'Workers edit modal must not render a separate interval field.');
$contains($workersTableConfig, "'icon' => 'player-play'", 'Workers run action must use the player-play icon.');
$appearsBefore($workersTableConfig, "'key' => 'identifier'", "'key' => 'worker_title'", 'Workers table identifier column must be first.');
$appearsBefore($workersTableConfig, "'key' => 'run'", "'key' => 'edit'", 'Workers row action block must put run before edit.');
$notContains($workersTableConfig, "'key' => 'scope', 'type' => 'text'", 'Workers table must not show scope column.');
$notContains($workersTableConfig, "'key' => 'active_badge'", 'Workers table must not show active status column.');
$notContains($workersTableConfig, "'key' => 'class_exists_badge'", 'Workers table must not show class availability column.');
$notContains($workersTableConfig, "'key' => 'hidden_badge'", 'Workers table must not show visibility as a column.');
$notContains($workersTableConfig, "'key' => 'position', 'type' => 'text'", 'Workers table must not show position column.');
$notContains($workersTableConfig, "'key' => 'updated_at_label'", 'Workers table must replace updated column with last run.');
$contains($workersTableConfig, "'key' => 'toggle_hidden'", 'Workers row actions must expose visibility toggle.');
$contains($workersTableConfig, "'provider' => 'toggleVisibility'", 'Workers visibility action must call the provider through runRowAction.');
$contains($workersTableConfig, "'icon_true' => 'eye'", 'Workers visibility action must show the reveal icon for hidden workers.');
$contains($workersTableConfig, "'icon_false' => 'eye-off'", 'Workers visibility action must show the hide icon for visible workers.');
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
$contains($workersTableData, 'public function toggleVisibility(int $id): void', 'WorkersTableData must expose EvoUI visibility toggle hook.');
$contains($workersTableData, "['hidden' => (int)\$worker->hidden > 0 ? 0 : 1]", 'WorkersTableData must toggle hidden state.');
$contains($workersTableData, 'public function modalData(int $id): array', 'WorkersTableData must expose modal edit data.');
$contains($workersTableData, 'public function saveModal(array $data, ?int $id, string $mode): ?int', 'WorkersTableData must save worker edit modal data.');
$contains($workersTableData, 'public function runWorker(int $id, array $action = []): ?int', 'WorkersTableData must expose provider-backed run action.');
$contains($workersTableData, 'public function runSelectedWorker(array $action = [], ?int $id = null): ?int', 'WorkersTableData must expose toolbar run action.');
$contains($workersTableData, 'public function runSelectedWorkerAttributes(array $action = [], ?int $id = null): array', 'WorkersTableData must expose toolbar run button attributes.');
$contains($workersTableData, "return ['disabled' => true];", 'WorkersTableData must disable toolbar run action for non-runnable workers.');
$contains($workersTableData, 'settings_payload', 'WorkersTableData must expose additional settings payload.');
$contains($workersTableData, 'decodeSettingsPayload', 'WorkersTableData must decode additional settings payload.');
$contains($workersTableData, 'public function modalFields(array $fields, array $data, ?int $id = null): array', 'WorkersTableData must adapt modal fields to worker capabilities.');
$contains($workersTableData, "method_exists(\$instance, 'renderSettings')", 'WorkersTableData must support worker-owned settings forms.');
$contains($workersTableData, "method_exists(\$instance, 'getGeneratedFiles')", 'WorkersTableData must support generated file lists.');
$contains($workersTableData, "\$field['type'] = 'worker-settings'", 'WorkersTableData must expose the worker settings modal field type.');
$contains($workersTableData, "'type' => 'worker-files'", 'WorkersTableData must expose the generated files modal field type.');
$contains($workersTableData, "Arr::except", 'WorkersTableData must keep schedule out of the additional settings payload.');
$contains($workersTableData, "'minutely', 'every_5min', 'every_15min', 'every_30min', 'hourly', 'daily', 'weekly', 'monthly'", 'Workers periodic schedule must allow minute, hourly, daily, weekly and monthly frequencies.');
$contains($workersTableData, "'value' => 'monthly'", 'Workers periodic schedule frequency options must include monthly.');
$contains($workersTableData, 'public function toggleSelectedActive(array $action = [], ?int $id = null): ?int', 'WorkersTableData must expose toolbar active toggle action.');
$contains($workersTableData, 'public function refreshWorkerRegistry(array $action = [], ?int $id = null): ?int', 'WorkersTableData must expose manual worker registry refresh action.');
$contains($workersTableData, 'WorkerDiscovery::class', 'WorkersTableData registry refresh must reuse WorkerDiscovery.');
$contains($workersTableData, '->discover()', 'WorkersTableData registry refresh must discover new workers.');
$contains($workersTableData, '->rescan()', 'WorkersTableData registry refresh must rescan existing workers.');
$contains($workersTableData, '->cleanOrphaned()', 'WorkersTableData registry refresh must remove orphaned worker rows.');
$contains($workersTableData, 'clearCache()', 'WorkersTableData registry refresh must clear worker cache.');
$contains($workersTableData, "sWorker::query()->with('supervisorStates')->withCount('tasks')", 'WorkersTableData must query workers with live supervisor states and task counts.');
$contains($workersTableData, "'label' => __('sTask::global.active')", 'WorkersTableData filter groups must return EvoUI label keys.');
$notContains($workersTableData, "'name' => __('sTask::global.active')", 'WorkersTableData filter groups must not use stale name keys.');
$contains($workersTableData, 'selectedFilterIds', 'WorkersTableData must read numeric multi-select filter ids.');
$notContains($workersTableData, "route('sTask.worker.settings'", 'WorkersTableData rows must not link to the removed worker settings route.');
$contains($workersTableData, "\$settings['schedule'] = [", 'WorkersTableData edit modal must persist schedule settings.');
$contains($workersTableData, "'manual' => true", 'WorkersTableData run action must create manual tasks.');
$contains($workersTableData, 'launchTaskWorker', 'WorkersTableData run action must trigger the existing worker processor path.');
$contains($workersTableData, 'lastTasksFor', 'WorkersTableData must expose last task status data.');
$contains($workersTableData, 'last_action_label', 'WorkersTableData must expose last action data.');
$contains($workersTableData, 'last_run_at_label', 'WorkersTableData must expose last run timestamp data.');
$contains($workersTableData, 'schedule_label', 'WorkersTableData must expose schedule display text.');
$contains($workersTableData, 'protected function scheduleLabel', 'WorkersTableData must format schedule display text.');
$contains($workersTableData, 'protected function scheduleDisplay', 'WorkersTableData must build a combined schedule and Supervisor state cell.');
$contains($workersTableData, "'sTask::global.schedule_supervisor_short'", 'Workers table must use the compact Supervisor schedule label.');
$contains($workersTableData, "\$item['badge'] = \$badge['label'];", 'Workers table must place the Supervisor state badge beside its schedule.');
$contains($workersTableData, 'protected function supervisorScheduleBadge', 'Workers table must format its Supervisor badge separately from the modal state badge.');
$contains($workersTableData, "(string)\$state?->state === 'healthy'", 'Workers table must replace only the healthy Supervisor label with uptime.');
$contains($workersTableData, "\$badge['label'] = niceEta((float)\$state->uptime_seconds);", 'Workers table must display formatted Supervisor uptime instead of the healthy state word.');
$contains($workersTableData, 'statusColor', 'WorkersTableData must map last task statuses to badge colors.');

$routes = $read('src/Http/routes.php');
$contains($routes, "Route::middleware(['mgr'])", 'Routes must stay protected by mgr middleware.');
$contains($routes, "Route::prefix('stask')->name('sTask.')", 'Routes must keep stask prefix and sTask route names.');
$contains($routes, "->name('index')", 'Routes must expose dashboard index.');
$contains($routes, "->name('worker.task.run')", 'Routes must expose worker task run API.');
$contains($routes, "->name('task.progress')", 'Routes must expose task progress API.');
$contains($routes, "->name('task.download')", 'Routes must expose task download API.');
$contains($routes, "->name('task.upload')", 'Routes must expose task upload API.');
$notContains($routes, "->name('worker.settings')", 'Routes must not expose the removed legacy worker settings page.');
$contains($routes, "->name('performance.summary')", 'Routes must expose performance summary API.');
$notContains($routes, 'abort(', 'Routes must not use Laravel abort fallback.');

$tablesMigration = $read('database/migrations/2025_10_15_000000_create_task_tables.php');
$contains($tablesMigration, "Schema::create('s_workers'", 'Install gate must create s_workers table.');
$contains($tablesMigration, "Schema::create('s_tasks'", 'Install gate must create s_tasks table.');
$contains($tablesMigration, "\$table->json('settings')", 'Workers table must keep JSON settings contract.');
$contains($tablesMigration, "\$table->integer('progress')->default(0)", 'Tasks table must keep progress column contract.');

$permissionMigration = $read('database/migrations/2025_10_15_000001_add_stask_permissions.php');
$contains($permissionMigration, 'public $withinTransaction = false;', 'Permission migration must run outside Laravel transaction wrapper.');
$contains($permissionMigration, "Schema::hasTable('permissions_groups')", 'Permission migration must guard missing permissions_groups table.');
$contains($permissionMigration, "Schema::hasTable('permissions')", 'Permission migration must guard missing permissions table.');
$contains($permissionMigration, "'name' => 'Seiger packages'", 'Initial permission migration must use the shared Seiger packages group.');
$contains($permissionMigration, "'lang_key' => 'seiger_packages'", 'Initial permission migration must expose the shared manager lexicon key.');
$contains($permissionMigration, "'key', 'stask'", 'Permission lookup must use stask key.');
$contains($permissionMigration, "'key' => 'stask'", 'Permission insert must use stask key.');
$contains($permissionMigration, "sTask::global.permission_access", 'Permission must use localized lang key.');
$contains($permissionMigration, "where('role_id', 1)", 'Permission migration must keep admin assignment compatibility.');

$taskWorker = $read('src/Console/TaskWorker.php');
$contains($taskWorker, 'use Seiger\\sTask\\Models\\sTaskModel;', 'TaskWorker must import sTaskModel.');
$contains($taskWorker, 'use Seiger\\sTask\\Models\\sWorker;', 'TaskWorker must import lowercase-s sWorker model.');
$notContains($taskWorker, 'use Seiger\\sTask\\Models\\Worker;', 'TaskWorker must not import non-existent Worker model.');
$contains($taskWorker, "protected \$signature = 'stask:worker';", 'TaskWorker command signature must stay stask:worker.');
$contains($taskWorker, "\$scheduleType === 'supervisor'", 'TaskWorker must route supervisor schedules through the supervisor.');
$contains($taskWorker, 'SupervisorWorkerInterface', 'TaskWorker must require the generic supervisor contract.');

$supervisorContract = $read('src/Contracts/SupervisorWorkerInterface.php');
$contains($supervisorContract, 'public function inspectSupervisor(): SupervisorStatus;', 'Supervisor contract must expose health inspection.');
$contains($supervisorContract, 'public function startSupervisor(): SupervisorStatus;', 'Supervisor contract must expose detached start.');
$contains($supervisorContract, 'public function restartSupervisor(): SupervisorStatus;', 'Supervisor contract must expose detached restart.');
$notContains($supervisorContract, 'Telegram', 'Generic supervisor contract must not couple sTask to Telegram.');

$supervisorService = $read('src/Services/SupervisorService.php');
$contains($supervisorService, "Cache::store('file')->lock", 'Supervisor must guard parallel scheduler launches.');
$contains($supervisorService, 'repeat_count', 'Supervisor must count repeated diagnostics.');
$contains($supervisorService, "'action' => 'supervisor'", 'Meaningful supervisor lifecycle events must use supervisor task rows.');
$contains($supervisorService, 'diagnostic_changed', 'Supervisor must record changed diagnostic fingerprints.');

$supervisorMigration = $read('database/migrations/2026_07_16_000000_create_supervisor_states_table.php');
$contains($supervisorMigration, "Schema::create('s_supervisor_states'", 'Supervisor live state must be stored separately from task history.');

$workersTableConfig = $read('config/workers/table.php');
$workersTableData = $read('src/Tables/WorkersTableData.php');
$contains($workersTableData, 'public function scheduleTypeOptions(', 'Workers UI must resolve schedule types through a capability provider.');
$contains($workersTableData, 'instanceof SupervisorWorkerInterface', 'Workers UI must expose Supervisor only for compatible worker classes.');
$contains($workersTableData, "throw new \\InvalidArgumentException(__('sTask::global.supervisor_schedule_unsupported'))", 'Worker settings must reject unsupported Supervisor schedules server-side.');
$contains($workersTableData, "['value' => 'supervisor', 'label' => 'sTask::global.schedule_supervisor']", 'Capability provider must expose the Supervisor schedule type for compatible workers.');
$contains($workersTableConfig, "'name' => 'supervisor_heartbeat_at'", 'Workers UI must expose live supervisor heartbeat.');

$dashboardData = $read('src/Support/DashboardData.php');
$contains($dashboardData, "\$progress = max(0, min(100, (int)\$task->progress));", 'Dashboard recent tasks must normalize stored task progress.');
$contains($dashboardData, "'progress' => \$progress", 'Dashboard recent tasks must show normalized task progress.');
$contains($dashboardData, "'progress_label' => \$this->progressLabel(\$task, \$progress)", 'Dashboard recent tasks must expose the readable progress and ETA label.');
$moduleJs = $read('js/module.js');
$contains($moduleJs, '`${progress}% · ${eta}`', 'Live progress polling must preserve the readable ETA separator.');
$contains($moduleJs, 'function initWorkerSettings(container, wire)', 'sTask runtime must activate worker-owned settings forms.');
$contains($moduleJs, "form.addEventListener('submit', () => syncWorkerSettings(form, wire), {capture: true})", 'Worker settings must synchronize before the EvoUI modal save handler.');
$assert(is_file($root . '/views/fields/worker-settings.blade.php'), 'Worker settings custom field view must exist.');
$assert(is_file($root . '/views/fields/worker-files.blade.php'), 'Generated files custom field view must exist.');

$logsTableData = $read('src/Tables/LogsTableData.php');
$contains($logsTableData, "\$progress = max(0, min(100, (int)\$task->progress));", 'Logs table must show stored task progress.');

$permissionGroupLabels = [
    'en' => 'Seiger packages',
    'uk' => 'Пакети Seiger',
    'fr' => 'Paquets Seiger',
    'ru' => 'Пакети Seiger',
    'de' => 'Seiger-Pakete',
    'pl' => 'Pakiety Seiger',
];

foreach (['en', 'uk', 'fr', 'ru', 'de', 'pl'] as $locale) {
    $lang = $read("lang/{$locale}/global.php");
    $labels = require $root . "/lang/{$locale}/global.php";

    $assert(($labels['module_title'] ?? null) === 'sTask', "{$locale} dDocs/module title must stay sTask.");
    $assert(($labels['module_icon'] ?? null) === 'tabler-progress-check', "{$locale} dDocs/module icon must stay tabler-progress-check.");
    $assert(($labels['permissions_group'] ?? null) === $permissionGroupLabels[$locale], "{$locale} permission group must use the localized Seiger packages label.");

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
        'user',
        'priority_normal',
        'priority_high',
        'available',
        'missing',
        'visible',
        'hidden',
        'show_worker',
        'hide_worker',
        'visibility',
        'class_exists',
        'worker_status',
        'last_task',
        'last_run',
        'default_position',
        'additional_settings',
        'additional_settings_help',
        'open_file',
        'refresh_worker_registry',
        'edit_worker',
        'permissions_group',
        'permission_access',
        'schedule_supervisor',
        'supervisor_key',
        'schedule_supervisor_short',
        'supervisor_state',
        'supervisor_pid',
        'supervisor_heartbeat',
        'supervisor_uptime',
        'supervisor_last_diagnostic',
        'supervisor_last_transition',
        'supervisor_state_healthy',
        'supervisor_state_starting',
        'supervisor_state_degraded',
        'supervisor_state_failed',
        'supervisor_state_stopped',
        'supervisor_contract_required',
        'supervisor_schedule_unsupported',
        'supervisor_empty_key',
        'supervisor_event_started',
        'supervisor_event_restarted',
        'supervisor_event_recovered',
        'supervisor_event_stopped',
        'supervisor_event_failed',
        'supervisor_event_degraded',
        'supervisor_event_diagnostic_changed',
    ] as $key) {
        $assert(array_key_exists($key, $labels), "{$locale} lang must define {$key}.");
    }
}

$ukLabels = require $root . '/lang/uk/global.php';
$ruLabels = require $root . '/lang/ru/global.php';
$assert($ruLabels === $ukLabels, 'Russian manager locale must fall back to the canonical Ukrainian labels.');

foreach (['en', 'uk', 'ru', 'de', 'fr', 'pl'] as $locale) {
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
