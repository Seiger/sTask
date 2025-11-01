<?php

return [
    'title' => 'Task Manager',
    
    // Menu
    'dashboard' => 'Dashboard',
    'workers' => 'Workers',
    'statistics' => 'Statistics',
    
    // Dashboard
    'pending_tasks' => 'Pending Tasks',
    'running_tasks' => 'Running Tasks',
    'completed_tasks' => 'Completed Tasks',
    'failed_tasks' => 'Failed Tasks',
    'total_tasks' => 'Total Tasks',
    'waiting_execution' => 'waiting for execution',
    'in_progress' => 'in progress',
    'successfully_finished' => 'successfully finished',
    'with_errors' => 'with errors',
    'all_time' => 'all time',
    'recent_tasks' => 'Recent Tasks',
    'view_all' => 'View All',
    'no_tasks_yet' => 'No tasks yet.',
    
    // Tasks
    'task' => 'Task',
    'worker' => 'Worker',
    'action' => 'Action',
    'status' => 'Status',
    'progress' => 'Progress',
    'created' => 'Created',
    'actions' => 'Actions',
    'details' => 'Details',
    
    // Statuses
    'pending' => 'Pending',
    'preparing' => 'Preparing',
    'running' => 'Running',
    'completed' => 'Completed',
    'failed' => 'Failed',
    'cancelled' => 'Cancelled',
    'unknown' => 'Unknown',
    
    // Workers
    'identifier' => 'Identifier',
    'class' => 'Class',
    'description' => 'Description',
    'position' => 'Position',
    'tasks_count' => 'Tasks',
    'active' => 'Active',
    'inactive' => 'Inactive',
    'activate' => 'Activate',
    'deactivate' => 'Deactivate',
    'discover_workers' => 'Discover Workers',
    'discover_workers_confirm' => 'Scan system for new workers?',
    'no_workers_found' => 'No workers found. Add your own worker or install a package with corresponding implementations.',
    'add_worker_or_install_package' => 'Add your own worker or install a package with corresponding implementations.',
    'worker_description' => 'Task worker for processing background jobs.',
    'worker_settings' => 'Worker Settings',
    'worker_statistics' => 'Worker Statistics',
    'worker_actions' => 'Worker Actions',
    'worker_class' => 'Worker Class',
    'back_to_workers' => 'Back to Workers',
    
    // Worker errors
    'worker_not_found_or_inactive' => 'Worker not found or inactive: :identifier',
    'worker_class_not_found' => 'Worker class not found: :className',
    'worker_must_implement_TaskInterface' => 'Worker must implement TaskInterface: :className',

    // Permissions
    'permissions_group' => 'sTask',
    'permission_access' => 'Access sTask Interface',
    
    // Widget
    'run_task' => 'Run Task',
    'settings' => 'Settings',
    'scope' => 'Scope',
    
    // Common
    'refresh' => 'Refresh',
    'error' => 'Error occurred',
    'done' => 'Done',
    'task_queued' => 'Task queued',
    'task_created' => 'Task created successfully',
    'process_tasks' => 'Process Tasks',
    'clean_old_tasks' => 'Clean Old Tasks',
    'clean_orphaned' => 'Clean Orphaned',
    
    // Composer Update Worker
    'composer_update' => 'Composer Update',
    'composer_update_desc' => 'Update all Composer dependencies to their latest versions',
    'task_preparing' => 'Task preparing',
    'task_running' => 'Task running',
    'composer_updating' => 'Updating Composer dependencies',
    'composer_updated_successfully' => 'Composer dependencies updated successfully',
    
    // File upload
    'file_too_large' => 'File is too large',
    'chunk_upload' => 'Chunked upload',
    'chunks' => 'chunks',
    'uploading_file' => 'Uploading',
    'upload_failed' => 'Upload failed',
    
    // Default widget
    'idle' => 'Idle',
    'default_widget_description' => 'This is a default widget. Override the renderWidget() method in your worker to create a custom interface.',
];
