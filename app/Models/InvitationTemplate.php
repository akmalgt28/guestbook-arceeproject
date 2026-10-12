<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'name',
    'slug',
    'description',
    'schema',
    'is_active',
])]
class InvitationTemplate extends Model
{
    public const SCHEMA_VERSION = 1;

    /** @var array<string, array{label: string, required: bool, type: string, min: int, max: int}> */
    public const SECTION_DEFINITIONS = [
        'hero' => ['label' => 'Hero', 'required' => true, 'type' => 'hero', 'min' => 1, 'max' => 1],
        'event_info' => ['label' => 'Event info', 'required' => true, 'type' => 'event_info', 'min' => 1, 'max' => 1],
        'location' => ['label' => 'Lokasi', 'required' => false, 'type' => 'location', 'min' => 0, 'max' => 1],
        'gallery' => ['label' => 'Galeri', 'required' => false, 'type' => 'gallery', 'min' => 0, 'max' => 1],
        'speakers' => ['label' => 'Narasumber', 'required' => false, 'type' => 'speakers', 'min' => 0, 'max' => 1],
        'rsvp' => ['label' => 'RSVP', 'required' => true, 'type' => 'rsvp', 'min' => 1, 'max' => 1],
        'qr' => ['label' => 'QR check-in', 'required' => false, 'type' => 'qr', 'min' => 0, 'max' => 1],
        'guestbook' => ['label' => 'Guestbook', 'required' => false, 'type' => 'guestbook', 'min' => 0, 'max' => 1],
        'contact' => ['label' => 'Kontak', 'required' => false, 'type' => 'contact', 'min' => 0, 'max' => 1],
    ];

    /** @return list<string> */
    public static function defaultSections(): array
    {
        return ['hero', 'event_info', 'location', 'rsvp', 'qr', 'guestbook'];
    }

    /** @return array<string, mixed> */
    public static function defaultSchema(): array
    {
        return [
            'version' => self::SCHEMA_VERSION,
            'theme' => [
                'primary_color' => '#1F2937',
                'secondary_color' => '#F3F4F6',
                'font_family' => 'Inter',
                'heading_font_family' => 'Inter',
                'body_font_family' => 'Inter',
                'text_color' => '#1F2937',
                'background_color' => '#F3F4F6',
                'heading_size' => 'lg',
                'body_size' => 'base',
                'radius' => 'xl',
                'button_style' => 'rounded',
                'card_style' => 'bordered',
            ],
            'navigation' => [
                'background_color' => '#90CAF9',
                'active_color' => '#0B4AA2',
                'text_color' => '#5B5560',
                'icon_color' => '#FFFFFF',
            ],
            'event' => [
                'show_type' => true,
                'show_description' => true,
                'date_label' => 'Tanggal',
                'time_label' => 'Waktu',
            ],
            'media' => [
                'hero' => ['media_id' => null, 'url' => null, 'alt' => '', 'position' => 'center'],
                'gallery' => [],
            ],
            'buttons' => [
                'map' => ['label' => 'Buka peta', 'style' => 'primary', 'target' => '_blank'],
                'rsvp' => ['label' => 'Isi RSVP', 'style' => 'primary', 'target' => '_self'],
                'qr_download' => ['label' => 'Download QR', 'style' => 'secondary', 'target' => '_self'],
            ],
            'rsvp' => [
                'title' => 'Konfirmasi kehadiran',
                'description' => 'Silakan konfirmasi kehadiran melalui formulir.',
                'submit_label' => 'Kirim konfirmasi',
                'success_message' => 'Terima kasih, konfirmasi kamu sudah tersimpan.',
                'fields' => [
                    ['key' => 'name', 'label' => 'Nama', 'type' => 'text', 'placeholder' => 'Masukkan nama', 'required' => true, 'visible' => true],
                    ['key' => 'phone', 'label' => 'Nomor telepon', 'type' => 'tel', 'placeholder' => 'Masukkan nomor telepon', 'required' => false, 'visible' => true],
                    ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'placeholder' => 'Masukkan email', 'required' => false, 'visible' => true],
                    ['key' => 'attending_count', 'label' => 'Jumlah tamu', 'type' => 'number', 'placeholder' => 'Jumlah tamu yang hadir', 'required' => true, 'visible' => true],
                    ['key' => 'notes', 'label' => 'Catatan', 'type' => 'textarea', 'placeholder' => 'Tambahkan catatan', 'required' => false, 'visible' => true],
                ],
            ],
            'qr' => [
                'title' => 'QR Check-in',
                'description' => 'Tunjukkan QR ini saat datang ke acara.',
                'display_after_rsvp' => true,
                'allow_download' => true,
            ],
            'decorations' => [
                'enabled' => true,
                'style' => 'blue-circles',
                'colors' => [
                    'primary' => '#0B4AA2',
                    'blue' => '#168DE2',
                    'light' => '#8BC4F0',
                ],
            ],
            'floating_images' => [],
            'background_elements' => [],
            'backgrounds' => [
                [
                    'id' => 'background-1',
                    'name' => 'Background 1',
                    'color' => '#DCEEFF',
                    'elements' => [],
                ],
            ],
            'sections' => array_map(fn (string $key): array => [
                'key' => $key,
                'visible' => true,
                'settings' => self::defaultSectionSettings($key),
            ], self::defaultSections()),
        ];
    }

    /** @return array<string, mixed> */
    public static function defaultSectionSettings(string $key): array
    {
        return match ($key) {
            'hero' => ['title' => '', 'subtitle' => '', 'recipient_label' => 'Undangan untuk'],
            'event_info' => ['title' => '', 'show_type' => true, 'show_description' => true],
            'location' => ['title' => 'Lokasi', 'show_map_button' => true],
            'gallery' => ['title' => 'Galeri'],
            'speakers' => ['title' => 'Narasumber', 'items' => []],
            'rsvp' => ['title' => 'Konfirmasi kehadiran'],
            'qr' => ['title' => 'QR Check-in'],
            'guestbook' => ['title' => 'Guestbook'],
            'contact' => ['title' => 'Kontak'],
            default => [],
        };
    }

    /** @return array<string, mixed> */
    public static function mergedSchema(mixed $schema, mixed $overrides = []): array
    {
        $baseSchema = is_array($schema) ? $schema : [];
        $settings = is_array($overrides) ? $overrides : json_decode((string) $overrides, true);

        return self::normalizeSchema(array_replace_recursive($baseSchema, is_array($settings) ? $settings : []));
    }

    public static function normalizeSchema(array $schema): array
    {
        $defaults = self::defaultSchema();
        if (isset($schema['theme']['navigation']) && ! isset($schema['navigation'])) {
            $schema['navigation'] = $schema['theme']['navigation'];
            unset($schema['theme']['navigation']);
        }
        $normalized = array_replace_recursive($defaults, $schema);
        $theme = array_replace($defaults['theme'], $schema['theme'] ?? []);

        foreach (['primary_color', 'secondary_color', 'font_family'] as $key) {
            if (array_key_exists($key, $schema)) {
                $theme[$key] = $schema[$key];
            }
        }

        $sections = $schema['sections'] ?? self::defaultSections();
        if (! is_array($sections)) {
            $sections = self::defaultSections();
        }

        $normalized['sections'] = [];
        foreach ($sections as $section) {
            $key = is_string($section) ? $section : ($section['key'] ?? null);
            if (! is_string($key) || ! array_key_exists($key, self::SECTION_DEFINITIONS) || collect($normalized['sections'])->contains('key', $key)) {
                continue;
            }

            $normalized['sections'][] = [
                'key' => $key,
                'visible' => is_array($section) ? (bool) ($section['visible'] ?? true) : true,
                'settings' => array_replace(
                    self::defaultSectionSettings($key),
                    is_array($section) ? ($section['settings'] ?? []) : [],
                ),
            ];
        }

        foreach (self::SECTION_DEFINITIONS as $key => $definition) {
            if ($definition['required'] && ! collect($normalized['sections'])->contains('key', $key)) {
                $normalized['sections'][] = [
                    'key' => $key,
                    'visible' => true,
                    'settings' => self::defaultSectionSettings($key),
                ];
            }
        }

        $legacyNavigation = [
            'background_color' => '#F3C7D8',
            'active_color' => '#C58DA7',
        ];
        foreach ($legacyNavigation as $key => $legacyColor) {
            if (($normalized['navigation'][$key] ?? null) === $legacyColor) {
                $normalized['navigation'][$key] = $defaults['navigation'][$key];
            }
        }

        $normalized['version'] = self::SCHEMA_VERSION;
        $normalized['theme'] = $theme;
        $normalized['primary_color'] = $theme['primary_color'];
        $normalized['secondary_color'] = $theme['secondary_color'];
        $normalized['font_family'] = $theme['font_family'];
        $normalized['section_keys'] = array_values(array_map(
            fn (array $section): string => $section['key'],
            array_filter($normalized['sections'], fn (array $section): bool => $section['visible']),
        ));

        return $normalized;
    }

    /** @return list<string> */
    public static function normalizeSections(array $sections): array
    {
        return array_values(array_unique(array_filter($sections, fn (mixed $section): bool => is_string($section) && array_key_exists($section, self::SECTION_DEFINITIONS))));
    }

    use HasFactory;

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_templates', 'template_id', 'event_id')
            ->withPivot('settings')
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
