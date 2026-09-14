<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiProviderConfig extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_EXHAUSTED = 'exhausted';

    protected $fillable = [
        'name',
        'provider',
        'api_key',
        'models',
        'selected_model',
        'enabled',
        'status',
        'status_message',
        'status_checked_at',
        'quota_exhausted_at',
    ];

    protected $hidden = ['api_key'];

    protected $casts = [
        'api_key' => 'encrypted',
        'models' => 'array',
        'enabled' => 'boolean',
        'status_checked_at' => 'datetime',
        'quota_exhausted_at' => 'datetime',
    ];

    public static function defaultProviders(): array
    {
        return [
            [
                'name' => 'Google Gemini',
                'provider' => 'gemini',
                'models' => ['gemini-3-flash-preview', 'gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-2.0-flash'],
                'selected_model' => config('services.gemini.model', 'gemini-3-flash-preview'),
            ],
            [
                'name' => 'OpenAI',
                'provider' => 'openai',
                'models' => ['gpt-4.1-mini', 'gpt-4.1', 'gpt-5-mini'],
                'selected_model' => 'gpt-4.1-mini',
            ],
            [
                'name' => 'Anthropic Claude',
                'provider' => 'anthropic',
                'models' => ['claude-3-5-haiku-latest', 'claude-3-5-sonnet-latest'],
                'selected_model' => 'claude-3-5-haiku-latest',
            ],
        ];
    }

    public static function ensureDefaults(): void
    {
        foreach (self::defaultProviders() as $provider) {
            $config = static::query()->firstOrCreate(
                ['provider' => $provider['provider']],
                array_merge($provider, [
                    'enabled' => $provider['provider'] === 'gemini',
                    'status' => self::STATUS_INACTIVE,
                ])
            );

            $models = array_values(array_unique(array_merge($config->models ?? [], $provider['models'])));

            $updates = [];

            if ($models !== ($config->models ?? [])) {
                $updates['models'] = $models;
            }

            if ($config->provider === 'gemini' && $config->selected_model === 'gemini-2.0-flash') {
                $updates['selected_model'] = 'gemini-3-flash-preview';
            }

            if ($updates !== []) {
                $config->update($updates);
            }
        }
    }

    public static function active(): ?self
    {
        self::ensureDefaults();

        return static::query()
            ->where('enabled', true)
            ->where('status', self::STATUS_ACTIVE)
            ->first()
            ?? static::query()->where('enabled', true)->first();
    }

    public function hasStoredOrEnvKey(): bool
    {
        return filled($this->api_key) || filled($this->envApiKey());
    }

    public function resolvedApiKey(): string
    {
        return trim($this->api_key ?: $this->envApiKey());
    }

    private function envApiKey(): string
    {
        return match ($this->provider) {
            'gemini' => config('services.gemini.key', ''),
            default => '',
        };
    }
}
