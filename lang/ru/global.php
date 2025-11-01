<?php

return [
    'title' => 'Менеджер Задач',
    
    // Menu
    'dashboard' => 'Панель',
    'workers' => 'Воркеры',
    'statistics' => 'Статистика',
    
    // Dashboard
    'pending_tasks' => 'Ожидают',
    'running_tasks' => 'Выполняются',
    'completed_tasks' => 'Завершены',
    'failed_tasks' => 'Ошибочные',
    'total_tasks' => 'Всего Задач',
    'waiting_execution' => 'ожидают выполнения',
    'in_progress' => 'в процессе',
    'successfully_finished' => 'успешно завершено',
    'with_errors' => 'с ошибками',
    'all_time' => 'за все время',
    'recent_tasks' => 'Последние Задачи',
    'view_all' => 'Смотреть Все',
    'no_tasks_yet' => 'Задач еще нет.',
    
    // Tasks
    'task' => 'Задача',
    'worker' => 'Воркер',
    'action' => 'Действие',
    'status' => 'Статус',
    'progress' => 'Прогресс',
    'created' => 'Создано',
    'actions' => 'Действия',
    'details' => 'Детали',
    
    // Statuses
    'pending' => 'Ожидает',
    'preparing' => 'Подготовка',
    'running' => 'Выполняется',
    'completed' => 'Завершено',
    'failed' => 'Ошибка',
    'cancelled' => 'Отменено',
    'unknown' => 'Неизвестно',
    
    // Workers
    'identifier' => 'Идентификатор',
    'class' => 'Класс',
    'description' => 'Описание',
    'position' => 'Позиция',
    'tasks_count' => 'Задач',
    'active' => 'Активный',
    'inactive' => 'Неактивный',
    'activate' => 'Активировать',
    'deactivate' => 'Деактивировать',
    'discover_workers' => 'Найти Воркеры',
    'discover_workers_confirm' => 'Просканировать систему на новые воркеры?',
    'no_workers_found' => 'Воркеры не найдены. Добавьте собственный воркер или установите пакет с соответствующими реализациями.',
    'add_worker_or_install_package' => 'Добавьте собственный воркер или установите пакет с соответствующими реализациями.',
    'worker_description' => 'Воркер задач для обработки фоновых операций.',
    'worker_settings' => 'Настройки Воркера',
    'worker_statistics' => 'Статистика Воркера',
    'worker_actions' => 'Действия Воркера',
    'worker_class' => 'Класс Воркера',
    'back_to_workers' => 'Назад к Воркерам',
    
    // Worker errors
    'worker_not_found_or_inactive' => 'Воркер не найден или неактивен: :identifier',
    'worker_class_not_found' => 'Класс воркера не найден: :className',
    'worker_must_implement_TaskInterface' => 'Воркер должен имплементировать TaskInterface: :className',

    // Permissions
    'permissions_group' => 'sTask',
    'permission_access' => 'Доступ к интерфейсу sTask',
    
    // Widget
    'run_task' => 'Запустить задание',
    'settings' => 'Настройки',
    'scope' => 'Область',
    
    // Common
    'refresh' => 'Обновить',
    'error' => 'Произошла ошибка',
    'done' => 'Готово',
    'task_queued' => 'Задача в очереди',
    'task_created' => 'Задача успешно создана',
    'process_tasks' => 'Обработать Задачи',
    'clean_old_tasks' => 'Очистить Старые Задачи',
    'clean_orphaned' => 'Очистить Осиротевшие',
    
    // Composer Update Worker
    'composer_update' => 'Обновление Composer',
    'composer_update_desc' => 'Обновить все зависимости Composer до последних версий',
    'task_preparing' => 'Подготовка задачи',
    'task_running' => 'Выполнение задачи',
    'composer_updating' => 'Обновление зависимостей Composer',
    'composer_updated_successfully' => 'Зависимости Composer успешно обновлены',
    'found_composer_json_in' => 'Найден composer.json в',
    'preparing_command_options' => 'Подготовка команды с опциями',
    'working_directory' => 'Рабочая директория',
    'checking_working_directory' => 'Проверка рабочей директории',
    'found_composer' => 'Найден Composer',
    'trying_direct_php_execution' => 'Попытка прямого выполнения PHP (exec функции отключены)',
    'composer_update_failed' => 'Обновление Composer не удалось (код ошибки',
    'finalizing_composer_update' => 'Завершение обновления Composer',
    'package_discovery_failed' => 'Обнаружение пакетов не удалось (не критично)',
    'preparing_direct_execution' => 'Подготовка прямого выполнения Composer',
    'using_composer_api' => 'Использование Composer API',
    'running_composer' => 'Выполнение',
    'executing_composer' => 'Выполнение Composer',
    'preparing_phar_execution' => 'Подготовка выполнения Composer PHAR',
    'loading_composer_phar' => 'Загрузка Composer PHAR',
    'executing' => 'Выполнение',
    'using_composer_from_vendor' => 'Использование Composer из vendor',
    'task_is_running' => 'Задача выполняется',
    'click_button_to_start' => 'Нажмите кнопку выше чтобы запустить задачу',
    'starting_task' => 'Запуск задачи',
    'please_wait' => 'Пожалуйста подождите',
    'error_starting_task' => 'Ошибка запуска задачи',
    
    // File upload
    'file_too_large' => 'Файл слишком большой',
    'chunk_upload' => 'Загрузка частями',
    'chunks' => 'частей',
    'uploading_file' => 'Загрузка',
    'upload_failed' => 'Ошибка загрузки',
    
    // Default widget
    'idle' => 'Ожидает',
    'default_widget_description' => 'Это виджет по умолчанию. Переопределите метод renderWidget() в вашем воркере для создания собственного интерфейса.',
];
