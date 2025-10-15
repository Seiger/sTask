<?php

return [
    'title' => 'Менеджер Задач',
    
    // Menu
    'dashboard' => 'Панель',
    'workers' => 'Воркери',
    'statistics' => 'Статистика',
    
    // Dashboard
    'pending_tasks' => 'Очікують',
    'running_tasks' => 'Виконуються',
    'completed_tasks' => 'Завершені',
    'failed_tasks' => 'Помилкові',
    'total_tasks' => 'Всього Завдань',
    'waiting_execution' => 'очікують виконання',
    'in_progress' => 'в процесі',
    'successfully_finished' => 'успішно завершено',
    'with_errors' => 'з помилками',
    'all_time' => 'за весь час',
    'recent_tasks' => 'Останні Завдання',
    'view_all' => 'Дивитись Все',
    'no_tasks_yet' => 'Завдань ще немає.',
    
    // Tasks
    'task' => 'Завдання',
    'worker' => 'Воркер',
    'action' => 'Дія',
    'status' => 'Статус',
    'progress' => 'Прогрес',
    'created' => 'Створено',
    'actions' => 'Дії',
    'details' => 'Деталі',
    
    // Statuses
    'pending' => 'Очікує',
    'preparing' => 'Підготовка',
    'running' => 'Виконується',
    'completed' => 'Завершено',
    'failed' => 'Помилка',
    'cancelled' => 'Скасовано',
    'unknown' => 'Невідомо',
    
    // Workers
    'identifier' => 'Ідентифікатор',
    'class' => 'Клас',
    'description' => 'Опис',
    'position' => 'Позиція',
    'tasks_count' => 'Завдань',
    'active' => 'Активний',
    'inactive' => 'Неактивний',
    'activate' => 'Активувати',
    'deactivate' => 'Деактивувати',
    'discover_workers' => 'Знайти Воркери',
    'discover_workers_confirm' => 'Просканувати систему на нові воркери?',
    'no_workers_found' => 'Воркери не знайдені. Додайте власний воркер або інсталюйте пакет з відповідними реалізаціями.',
    'add_worker_or_install_package' => 'Додайте власний воркер або інсталюйте пакет з відповідними реалізаціями.',
    'worker_description' => 'Воркер завдань для обробки фонових операцій.',
    'worker_settings' => 'Налаштування Воркера',
    'worker_statistics' => 'Статистика Воркера',
    'worker_actions' => 'Дії Воркера',
    'worker_class' => 'Клас Воркера',
    'back_to_workers' => 'Назад до Воркерів',
    
    // Worker errors
    'worker_not_found_or_inactive' => 'Воркер не знайдено або неактивний: :identifier',
    'worker_class_not_found' => 'Клас воркера не знайдено: :className',
    'worker_must_implement_TaskInterface' => 'Воркер повинен імплементувати TaskInterface: :className',

    // Permissions
    'permissions_group' => 'sTask',
    'permission_access' => 'Доступ до інтерфейсу sTask',
    
    // Widget
    'run_task' => 'Запустити завдання',
    'settings' => 'Налаштування',
    'scope' => 'Область',
    
    // Common
    'refresh' => 'Оновити',
    'error' => 'Виникла помилка',
    'done' => 'Готово',
    'task_queued' => 'Завдання в черзі',
    'task_created' => 'Завдання успішно створено',
    'process_tasks' => 'Обробити Завдання',
    'clean_old_tasks' => 'Очистити Старі Завдання',
    'clean_orphaned' => 'Очистити Осиротілі',
    
    // File upload
    'file_too_large' => 'Файл занадто великий',
    'chunk_upload' => 'Завантаження частинами',
    'chunks' => 'частин',
    'uploading_file' => 'Завантаження',
    'upload_failed' => 'Помилка завантаження',
    
    // Default widget
    'idle' => 'Очікує',
    'default_widget_description' => 'Це віджет за замовчуванням. Перевизначте метод renderWidget() у вашому воркері для створення власного інтерфейсу.',
];
