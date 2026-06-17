<?php namespace Seiger\sTask\Livewire;

use Livewire\Component;
use Seiger\sTask\Facades\sTask as sTaskFacade;
use Seiger\sTask\Support\DashboardData;
use Seiger\sTask\Tables\LogsTableData;

class ModulePanel extends Component
{
    public array $rawTabs = [];
    public array $context = [];
    public string $activeTab = 'dashboard';
    public bool $detailModalOpen = false;
    public ?int $detailModalTaskId = null;
    public array $detailModalData = [];

    public function mount(array $tabs = [], string $activeTab = 'dashboard', array $context = []): void
    {
        $this->rawTabs = $tabs;
        $this->context = $context;
        $this->activeTab = $this->normalizeTab($activeTab);
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $this->normalizeTab($tab);
    }

    public function openTaskDetails(int $id): void
    {
        $data = app(LogsTableData::class)->modalData($id);

        if ($data === []) {
            return;
        }

        $this->detailModalTaskId = $id;
        $this->detailModalData = $data;
        $this->detailModalOpen = true;
    }

    public function closeModal(): void
    {
        $this->detailModalOpen = false;
        $this->detailModalTaskId = null;
        $this->detailModalData = [];
    }

    public function clearWorkerCache(): void
    {
        sTaskFacade::clearWorkerCache();
    }

    public function render()
    {
        $dashboard = app(DashboardData::class);

        return view('sTask::livewire.module-panel', [
            'tabs' => $this->navigationTabs(),
            'activeTab' => $this->activeTab,
            'dashboardCards' => $dashboard->cards(),
            'recentTaskRows' => $dashboard->recentTasks(),
            'recentErrorRows' => $dashboard->recentErrors(),
            'performanceCards' => $dashboard->performanceCards(),
            'performanceAlerts' => $dashboard->performanceAlerts(),
            'cacheStats' => $dashboard->cacheStats(),
            'detailModalTitle' => $this->detailModalTitle(),
            'detailModalMeta' => $this->detailModalMeta(),
        ]);
    }

    protected function detailModalTitle(): string
    {
        $id = (int)($this->detailModalTaskId ?: ($this->detailModalData['id'] ?? 0));

        return __('sTask::global.task') . ($id > 0 ? ' #' . $id : '');
    }

    protected function detailModalMeta(): array
    {
        return [
            ['label' => 'sTask::global.worker', 'value' => (string)($this->detailModalData['worker_title'] ?? ''), 'icon' => 'cpu'],
            ['label' => 'sTask::global.status', 'value' => (string)data_get($this->detailModalData, 'status_badge.label', ''), 'icon' => 'circle-check'],
        ];
    }

    protected function normalizeTab(string $tab): string
    {
        $tab = trim($tab);
        $allowed = collect($this->rawTabs)->pluck('key')->filter()->values()->all();

        return in_array($tab, $allowed, true) ? $tab : ($allowed[0] ?? 'dashboard');
    }

    protected function navigationTabs(): array
    {
        return collect($this->rawTabs)
            ->map(function (array $tab) {
                $key = (string)($tab['key'] ?? '');
                $tab['active'] = $key === $this->activeTab;
                $tab['type'] = 'wire';
                $tab['method'] = 'switchTab';
                $tab['argument'] = $key;
                unset($tab['href'], $tab['data']);

                return $tab;
            })
            ->values()
            ->all();
    }
}
