<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ModuleFile extends Model
{
    protected $fillable = ['module_id', 'file_name', 'file_path', 'file_type', 'file_size'];

    protected $appends = ['url', 'size_formatted'];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk()->url($this->file_path);
    }

    public function getSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes < 1024) return "{$bytes} B";
        if ($bytes < 1048576) return round($bytes / 1024, 1).' KB';
        return round($bytes / 1048576, 1).' MB';
    }

    public function isViewable(): bool
    {
        return in_array($this->file_type, ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp']);
    }
}
