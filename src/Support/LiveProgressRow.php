<?php namespace Seiger\sTask\Support;

use Seiger\sTask\Models\sTaskModel;

/**
 * Build presentation attributes for rows backed by an active task snapshot.
 *
 * @since 2.1.0
 */
class LiveProgressRow
{
    /**
     * Return inert row attributes consumed by EvoUI table and list renderers.
     *
     * @param sTaskModel|null $task Active task represented by the row
     * @return array<string, string>
     * @since 2.1.0
     */
    public static function attributes(?sTaskModel $task): array
    {
        if (!$task || !in_array((int)$task->status, sTaskModel::activeStatuses(), true)) {
            return [];
        }

        $progress = max(0, min(100, (int)$task->progress));

        return [
            'class' => 'stask-task-progress-row stask-task-progress-row--active',
            'style' => '--stask-task-progress: ' . $progress . '%;',
            'data-stask-progress-url' => route('sTask.task.progress', [
                'id' => $task->id,
                'include_log' => 0,
            ]),
        ];
    }
}
