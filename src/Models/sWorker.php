<?php namespace Seiger\sTask\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class sWorker
 *
 * Model for task workers configuration
 *
 * @package Seiger\sTask\Models
 * @author Seiger IT Team
 * @since 1.0.0
 */
class sWorker extends Model
{
    protected $table = 's_workers';
    
    protected $fillable = [
        'uuid',
        'identifier',
        'class',
        'active',
        'position',
        'settings',
        'hidden',
    ];

    protected $casts = [
        'settings' => 'array',
        'active' => 'boolean',
    ];

    /**
     * Get tasks for this worker
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(sTaskModel::class, 'identifier', 'identifier');
    }

    /**
     * Scope for active workers
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope for visible workers
     */
    public function scopeVisible($query)
    {
        return $query->where('hidden', 0);
    }

    /**
     * Scope for ordered workers
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('position')->orderBy('identifier');
    }

    /**
     * Check if worker class exists
     */
    public function getClassExistsAttribute(): bool
    {
        return class_exists($this->class);
    }

    /**
     * Get worker instance
     */
    public function getInstance()
    {
        if (!$this->class_exists) {
            return null;
        }

        try {
            return app($this->class);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get worker description
     */
    public function getDescriptionAttribute(): string
    {
        $instance = $this->getInstance();
        
        if ($instance && method_exists($instance, 'getDescription')) {
            return $instance::getDescription();
        }

        return ucwords(str_replace(['_', '-'], ' ', $this->identifier));
    }

    /**
     * Get worker type
     */
    public function getTypeAttribute(): string
    {
        $instance = $this->getInstance();
        
        if ($instance && method_exists($instance, 'getType')) {
            return $instance::getType();
        }

        return $this->identifier;
    }

    /**
     * Check if worker can be used
     */
    public function canBeUsed(): bool
    {
        return $this->active && $this->class_exists;
    }

    /**
     * Get default settings
     */
    public function getDefaultSettings(): array
    {
        return $this->settings ?? [];
    }

    /**
     * Update settings
     */
    public function updateSettings(array $settings): void
    {
        $this->update(['settings' => array_merge($this->getDefaultSettings(), $settings)]);
    }
}

