<?php namespace Seiger\sTask\Contracts;

/**
 * TaskInterface - Contract for sTask worker implementations
 *
 * This interface defines the standard contract that all sTask workers
 * must implement. It ensures consistency across different worker types
 * and provides a unified API for task execution, identification,
 * and configuration management.
 *
 * Key Responsibilities:
 * - Worker identification and metadata (identifier, scope, icon, title, description)
 * - Multiple task actions with dynamic method resolution (taskImport, taskExport, etc.)
 * - Widget rendering for administrative interface
 * - Worker configuration and settings management
 *
 * Worker Lifecycle:
 * 1. Worker registration with unique identifier and scope
 * 2. Widget rendering in administrative interface through renderWidget()
 * 3. Task creation with action specification (e.g., 'import', 'export')
 * 4. Action method resolution (e.g., 'import' -> taskImport())
 * 5. Task execution through action methods (taskImport, taskExport, etc.)
 * 6. Settings management through settings()
 *
 * Action Methods Convention:
 * - All task action methods must start with 'task' prefix
 * - Method names follow camelCase after prefix (e.g., taskImport, taskExport)
 * - Action name in snake_case maps to camelCase method (e.g., 'sync_stock' -> taskSyncStock)
 * - Each worker must implement at least one task action method
 * - Action methods signature: public function taskActionName(sTaskModel $task, array $options = []): void
 *
 * All concrete worker classes must implement this interface to ensure
 * compatibility with the sTask system and provide consistent behavior
 * across different task types and execution scenarios.
 *
 * @package Seiger\sTask
 * @author Seiger IT Team
 * @since 1.0.0
 */
interface TaskInterface
{
    /**
     * Get the unique identifier for this worker.
     *
     * This method returns a unique string identifier that distinguishes this
     * worker from all others in the system. The identifier is used for:
     * - Worker registration and discovery
     * - Task creation and assignment
     * - Database lookups and storage
     * - URL routing and API endpoints
     * - Configuration and settings management
     * - Integration with external systems
     *
     * The identifier should be:
     * - Unique across all workers in the system
     * - URL-safe (lowercase, alphanumeric, underscores allowed)
     * - Short and memorable (3-10 characters recommended)
     * - Consistent and immutable across versions
     * - Follow naming conventions (e.g., "prod_sync", "email_send")
     *
     * Examples of good identifiers:
     * - "prod_sync" - for product synchronization workers
     * - "email_send" - for email sending workers
     * - "report_gen" - for report generation workers
     * - "backup" - for backup operation workers
     * - "cache_clr" - for cache clearing workers
     *
     * @return string The unique identifier for this worker
     */
    public function identifier(): string;

    /**
     * Get the scope identifier for this worker.
     *
     * This method returns the module or package scope that this worker belongs to.
     * The scope is used for:
     * - Filtering workers by module in administrative interfaces
     * - Organizing workers by functionality
     * - Module-specific worker displays
     * - Access control and permissions
     * - Worker categorization and grouping
     *
     * The scope should be:
     * - Module or package identifier (e.g., "scommerce", "sarticles")
     * - Lowercase with underscores for multi-word names
     * - Match the package namespace or module name
     * - Consistent across all workers in the same module
     *
     * Examples of scope identifiers:
     * - "scommerce" - for sCommerce package workers
     * - "sarticles" - for sArticles package workers
     * - "sgallery" - for sGallery package workers
     * - "stask" - for sTask core workers
     * - "custom" - for custom application workers
     *
     * @return string The scope identifier (module/package name)
     */
    public function scope(): string;

    /**
     * Get the admin display icon for this worker.
     *
     * This method returns an HTML string containing the icon that represents
     * this worker in the administrative interface. The icon is used for:
     * - Visual identification in worker lists and selection
     * - User interface elements and buttons
     * - Navigation and menu items
     * - Status indicators and progress displays
     * - Task creation and management interfaces
     *
     * The returned string should contain:
     * - Valid HTML markup (typically <i> tags with CSS classes)
     * - Font Awesome or similar icon library classes
     * - Appropriate styling for the admin theme
     * - Consistent sizing and appearance
     * - Meaningful representation of the worker's function
     *
     * Common icon examples:
     * - "<i class='fa fa-upload'></i>" for import workers
     * - "<i class='fa fa-download'></i>" for export workers
     * - "<i class='fa fa-envelope'></i>" for email workers
     * - "<i class='fa fa-file-text'></i>" for report workers
     * - "<i class='fa fa-database'></i>" for database workers
     *
     * @return string HTML string containing the worker icon
     */
    public function icon(): string;

    /**
     * Get the human-readable title for this worker.
     *
     * This method returns a localized, human-readable title that represents
     * the worker's name in the administrative interface. The title is used for:
     * - Display in worker lists and tables
     * - Page headers and breadcrumbs
     * - User interface labels and buttons
     * - Selection menus and dropdowns
     * - Navigation and menu items
     *
     * The title should be:
     * - Short and concise (2-5 words recommended)
     * - Localized for the current language when possible
     * - Professional and consistent with system terminology
     * - Clear about the worker's primary function
     * - User-friendly and easily recognizable
     *
     * Examples of good titles:
     * - "Product Synchronization"
     * - "Bulk Email Sender"
     * - "Report Generator"
     * - "Database Backup"
     * - "Cache Manager"
     *
     * @return string The human-readable title of this worker
     */
    public function title(): string;

    /**
     * Get the human-readable description of this worker.
     *
     * This method returns a localized, human-readable description that explains
     * the worker's purpose, functionality, and use cases. The description is
     * used for:
     * - Display in administrative interfaces
     * - User documentation and help text
     * - Worker selection and configuration
     * - Error messages and notifications
     * - Developer documentation and code comments
     *
     * The description should be:
     * - Clear and concise about the worker's primary function
     * - Localized for the current language when possible
     * - Descriptive enough for users to understand its purpose
     * - Professional and consistent with system terminology
     * - Helpful for troubleshooting and debugging
     *
     * Examples of good descriptions:
     * - "Import products from external systems and synchronize inventory"
     * - "Send bulk email campaigns to customer lists"
     * - "Generate and export sales reports in various formats"
     * - "Create automated backups of database and files"
     *
     * @return string The human-readable description of this worker
     */
    public function description(): string;

    /**
     * Render the worker widget for the administrative interface.
     *
     * This method generates the HTML content for the worker's main widget
     * that is displayed in the administrative interface. The widget typically
     * contains the worker's primary functionality, controls, and status
     * information.
     *
     * The widget should include:
     * - Worker status and configuration
     * - Action buttons and controls for each available task action
     * - Progress indicators and logs
     * - Settings and options interface
     * - Error messages and notifications
     * - Available actions list (import, export, etc.)
     *
     * The returned HTML should be:
     * - Well-formed and valid HTML
     * - Styled appropriately for the admin theme
     * - Responsive and accessible
     * - Interactive with proper JavaScript integration
     * - Include controls for all available task actions
     *
     * @return string HTML content for the worker widget
     */
    public function renderWidget(): string;

    /**
     * Get the configuration settings for this worker.
     *
     * This method returns an associative array containing all configurable
     * settings for this worker. These settings control the worker's behavior,
     * connection parameters, and operational preferences.
     *
     * The settings should include:
     * - Connection parameters and credentials
     * - Processing options and preferences
     * - Performance and resource limits
     * - Error handling and retry policies
     * - Output formatting and destination options
     *
     * @return array Associative array of worker configuration settings
     */
    public function settings(): array;
}
