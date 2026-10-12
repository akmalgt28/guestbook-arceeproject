<?php

use App\Models\Event;
use App\Models\InvitationTemplate;
use App\Models\MediaFile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public ?int $templateId = null;

    public string $name = '';

    public string $slug = '';

    public string $description = '';

    public bool $isActive = false;

    public string $status = 'draft';

    public bool $hasSnapshot = false;

    public string $mode = 'edit';

    public string $previewDevice = 'mobile';

    public string $primaryColor = '#1F2937';

    public string $secondaryColor = '#F3F4F6';

    public string $fontFamily = 'Inter';

    public string $headingFontFamily = 'Inter';

    public string $bodyFontFamily = 'Inter';

    public string $textColor = '#1F2937';

    public string $backgroundColor = '#F3F4F6';

    public string $desktopBackgroundColor = '#111827';

    public string $navBackgroundColor = '#90CAF9';

    public string $navActiveColor = '#0B4AA2';

    public string $navTextColor = '#5B5560';

    public string $navIconColor = '#FFFFFF';

    public string $headingSize = 'lg';

    public string $bodySize = 'base';

    public string $radius = 'xl';

    public string $buttonStyle = 'rounded';



    public string $cardStyle = 'bordered';

    public array $sections = [];

    public array $sectionVisibility = [];

    public array $sectionSettings = [];

    public array $eventConfig = [];

    public array $buttonConfig = [];

    public array $rsvpConfig = [];

    public array $qrConfig = [];

    public string $sectionToAdd = '';

    public string $eventId = '';

    public string $heroMediaId = '';

    public string $heroMediaAlt = '';

    public string $heroMediaPosition = 'center';

    /** @var list<int> */
    public array $galleryMediaIds = [];

    /** @var list<array<string, mixed>> */
    public array $backgroundElements = [];

    /** @var list<array{id: string, name: string, color: string, elements: list<array<string, mixed>>}> */
    public array $backgrounds = [];

    public string $activeBackgroundId = '';

    public string $backgroundName = 'Background 1';

    public bool $decorationsEnabled = true;

    public string $circlePrimary = '#0B4AA2';

    public string $circleBlue = '#168DE2';

    public string $circleLight = '#8BC4F0';

    /** @var array<string, list<array{url: string, position: string, size: string, opacity: int}>> */
    public array $floatingImages = [];

    public string $floatingImagePage = 'hero';

    public string $floatingImageUrl = '';

    public string $floatingImagePosition = 'top-right';

    public string $floatingImageSize = 'md';

    public int $floatingImageOpacity = 100;

    public function mount(?int $templateId = null): void
    {
        $this->templateId = $templateId;
        $this->sections = InvitationTemplate::defaultSections();
        abort_unless(auth()->user()?->can($templateId ? 'templates.update' : 'templates.create'), 403);

        if (! $templateId) {
            $defaultSchema = InvitationTemplate::defaultSchema();
            $this->eventConfig = $defaultSchema['event'];
            $this->buttonConfig = $defaultSchema['buttons'];
            $this->rsvpConfig = $defaultSchema['rsvp'];
            $this->qrConfig = $defaultSchema['qr'];
            $this->loadTheme($defaultSchema['theme']);
            $this->loadNavigation($defaultSchema['navigation'] ?? []);
            $this->heroMediaAlt = $defaultSchema['media']['hero']['alt'];
            $this->heroMediaPosition = $defaultSchema['media']['hero']['position'];
            $this->galleryMediaIds = array_values(array_map('intval', $defaultSchema['media']['gallery'] ?? []));
            $this->loadDecorations($defaultSchema['decorations'] ?? []);
            $this->backgroundElements = $defaultSchema['background_elements'] ?? [];
            $this->backgrounds = $defaultSchema['backgrounds'] ?? [$this->makeBackground('Background 1', $this->backgroundColor, $this->backgroundElements)];
            $this->activateBackground($this->backgrounds[0]['id']);
            $this->floatingImages = $defaultSchema['floating_images'] ?? [];

            foreach ($this->sections as $section) {
                $this->sectionVisibility[$section] = true;
                $this->sectionSettings[$section] = InvitationTemplate::defaultSectionSettings($section);
            }

            return;
        }

        $template = InvitationTemplate::query()->with('events')->findOrFail($templateId);
        $rawSchema = $template->schema ?? [];
        $schema = InvitationTemplate::normalizeSchema($rawSchema);
        $this->name = $template->name;
        $this->slug = $template->slug;
        $this->description = $template->description ?? '';
        $this->isActive = $template->is_active;
        $this->status = data_get($schema, 'status', $template->is_active ? 'published' : 'draft');
        $this->hasSnapshot = is_array(data_get($schema, 'snapshot.schema'));
        $this->eventConfig = $schema['event'];
        $this->buttonConfig = $schema['buttons'];
        $this->rsvpConfig = $schema['rsvp'];
        $this->qrConfig = $schema['qr'];
        $this->heroMediaId = (string) ($schema['media']['hero']['media_id'] ?? '');
        $this->heroMediaAlt = (string) ($schema['media']['hero']['alt'] ?? '');
        $this->heroMediaPosition = (string) ($schema['media']['hero']['position'] ?? 'center');
        $this->galleryMediaIds = array_values(array_filter(array_map('intval', $schema['media']['gallery'] ?? []), fn (int $mediaId): bool => $mediaId > 0));
        $this->loadDecorations($schema['decorations'] ?? []);
        $this->backgroundElements = $schema['background_elements'] ?? [];
        $this->backgrounds = array_key_exists('backgrounds', $rawSchema) ? ($schema['backgrounds'] ?? []) : [];
        $this->floatingImages = $schema['floating_images'] ?? [];
        $this->loadTheme($schema['theme']);
        $this->loadNavigation($schema['navigation'] ?? []);
        if ($this->backgrounds === []) {
            $this->backgrounds = [$this->makeBackground('Background 1', $this->backgroundColor, $this->backgroundElements)];
        }
        $this->activateBackground((string) ($schema['active_background_id'] ?? $this->backgrounds[0]['id']));
        $this->primaryColor = $schema['theme']['primary_color'];
        $this->secondaryColor = $schema['theme']['secondary_color'];
        $this->fontFamily = $schema['theme']['font_family'];
        $this->sections = [];

        foreach ($schema['sections'] as $section) {
            $key = $section['key'];
            $this->sections[] = $key;
            $this->sectionVisibility[$key] = (bool) $section['visible'];
            $this->sectionSettings[$key] = $section['settings'];
        }

        $this->eventId = (string) ($template->events->first()?->id ?? '');
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        return [
            'events' => $this->accessibleEvents()->orderBy('event_date')->pluck('name', 'id'),
            'previewEvent' => $this->eventId !== '' ? $this->accessibleEvents()->find($this->eventId) : null,
            'mediaFiles' => $this->eventMedia()->get(),
        ];
    }

    public function applyBrandPreset(): void
    {
        $this->loadTheme(InvitationTemplate::defaultSchema()['theme']);
    }

    public function resetVisuals(): void
    {
        $this->loadTheme(InvitationTemplate::defaultSchema()['theme']);
    }

    /** @param array<string, mixed> $theme */
    private function loadTheme(array $theme): void
    {
        $this->primaryColor = $theme['primary_color'];
        $this->secondaryColor = $theme['secondary_color'];
        $this->fontFamily = $theme['font_family'];
        $this->headingFontFamily = $theme['heading_font_family'];
        $this->bodyFontFamily = $theme['body_font_family'];
        $this->textColor = $theme['text_color'];
        $this->backgroundColor = $theme['background_color'];
        $this->desktopBackgroundColor = $theme['desktop_background_color'] ?? '#111827';
        $this->headingSize = $theme['heading_size'];
        $this->bodySize = $theme['body_size'];
        $this->radius = $theme['radius'];
        $this->buttonStyle = $theme['button_style'];
        $this->cardStyle = $theme['card_style'];
    }

    /** @param array<string, mixed> $navigation */
    private function loadNavigation(array $navigation): void
    {
        $this->navBackgroundColor = (string) ($navigation['background_color'] ?? '#90CAF9');
        $this->navActiveColor = (string) ($navigation['active_color'] ?? '#0B4AA2');
        $this->navTextColor = (string) ($navigation['text_color'] ?? '#5B5560');
        $this->navIconColor = (string) ($navigation['icon_color'] ?? '#FFFFFF');
    }

    /** @param array<string, mixed> $decorations */
    private function loadDecorations(array $decorations): void
    {
        $this->decorationsEnabled = (bool) ($decorations['enabled'] ?? true);
        $colors = $decorations['colors'] ?? [];
        $this->circlePrimary = (string) ($colors['primary'] ?? '#0B4AA2');
        $this->circleBlue = (string) ($colors['blue'] ?? '#168DE2');
        $this->circleLight = (string) ($colors['light'] ?? '#8BC4F0');
    }

    public function addFloatingImage(): void
    {
        $data = $this->validate([
            'floatingImagePage' => ['required', 'in:hero,event_info,location,gallery,rsvp,guestbook'],
            'floatingImageUrl' => ['required', 'url', 'max:2000'],
            'floatingImagePosition' => ['required', 'in:top-left,top-right,middle-left,middle-right,bottom-left,bottom-right'],
            'floatingImageSize' => ['required', 'in:sm,md,lg'],
            'floatingImageOpacity' => ['required', 'integer', 'min:10', 'max:100'],
        ]);

        $this->floatingImages[$data['floatingImagePage']][] = [
            'url' => $data['floatingImageUrl'],
            'position' => $data['floatingImagePosition'],
            'size' => $data['floatingImageSize'],
            'opacity' => $data['floatingImageOpacity'],
        ];
        $this->floatingImageUrl = '';
    }

    public function removeFloatingImage(string $page, int $index): void
    {
        unset($this->floatingImages[$page][$index]);
        $this->floatingImages[$page] = array_values($this->floatingImages[$page] ?? []);
    }

    public function saveBackground(): void
    {
        $this->persistTemplate($this->status, $this->status === 'published');
    }

    public function createBackground(): void
    {
        $this->syncActiveBackground();
        $number = count($this->backgrounds) + 1;
        $background = $this->makeBackground('Background '.$number, $this->backgroundColor, []);
        $this->backgrounds[] = $background;
        $this->activateBackground($background['id']);
    }

    public function selectBackground(string $backgroundId): void
    {
        $this->syncActiveBackground();
        $this->activateBackground($backgroundId);
    }

    public function deleteBackground(): void
    {
        if (count($this->backgrounds) <= 1) {
            return;
        }
        $this->syncActiveBackground();
        $this->backgrounds = array_values(array_filter($this->backgrounds, fn (array $background): bool => $background['id'] !== $this->activeBackgroundId));
        $this->activateBackground($this->backgrounds[0]['id']);
    }

    public function moveBackground(int $index, int $direction): void
    {
        $this->syncActiveBackground();
        $target = $index + $direction;
        if (! isset($this->backgrounds[$index], $this->backgrounds[$target])) {
            return;
        }
        [$this->backgrounds[$index], $this->backgrounds[$target]] = [$this->backgrounds[$target], $this->backgrounds[$index]];
    }

    /** @return array{id: string, name: string, color: string, elements: list<array<string, mixed>>} */
    private function makeBackground(string $name, string $color, array $elements): array
    {
        return [
            'id' => 'background-'.Str::lower(Str::random(12)),
            'name' => $name,
            'color' => $color,
            'elements' => $elements,
        ];
    }

    private function syncActiveBackground(): void
    {
        foreach ($this->backgrounds as $index => $background) {
            if ($background['id'] !== $this->activeBackgroundId) {
                continue;
            }
            $this->backgrounds[$index] = [
                'id' => $background['id'],
                'name' => $this->backgroundName,
                'color' => $this->backgroundColor,
                'elements' => $this->normalizeCircleElements($this->backgroundElements),
            ];
        }
    }

    /** @param list<array<string, mixed>> $elements */
    private function normalizeCircleElements(array $elements): array
    {
        foreach ($elements as $index => $element) {
            if (($element['type'] ?? null) !== 'ellipse') {
                continue;
            }
            $size = min((float) ($element['width'] ?? 1), (float) ($element['height'] ?? 1));
            $elements[$index]['width'] = $size;
            $elements[$index]['height'] = $size;
        }

        return $elements;
    }
    private function activateBackground(string $backgroundId): void
    {
        $background = collect($this->backgrounds)->firstWhere('id', $backgroundId);
        if (! is_array($background)) {
            return;
        }
        $this->activeBackgroundId = $background['id'];
        $this->backgroundName = $background['name'];
        $this->backgroundColor = $background['color'];
        $this->backgroundElements = $background['elements'];
    }
    public function setMode(string $mode): void
    {
        abort_unless(in_array($mode, ['edit', 'preview'], true), 404);
        $this->mode = $mode;
    }

    public function setPreviewDevice(string $device): void
    {
        abort_unless(in_array($device, ['mobile', 'desktop'], true), 404);
        $this->previewDevice = $device;
    }

    public function addSection(): void
    {
        abort_unless(array_key_exists($this->sectionToAdd, InvitationTemplate::SECTION_DEFINITIONS), 422);

        if (in_array($this->sectionToAdd, $this->sections, true)) {
            return;
        }

        $this->sections[] = $this->sectionToAdd;
        $this->sectionVisibility[$this->sectionToAdd] = true;
        $this->sectionSettings[$this->sectionToAdd] = InvitationTemplate::defaultSectionSettings($this->sectionToAdd);
        $this->sectionToAdd = '';
    }

    public function removeSection(string $section): void
    {
        abort_unless(array_key_exists($section, InvitationTemplate::SECTION_DEFINITIONS), 422);
        abort_if(InvitationTemplate::SECTION_DEFINITIONS[$section]['required'], 422);
        $this->sections = array_values(array_filter($this->sections, fn (string $key): bool => $key !== $section));
        unset($this->sectionVisibility[$section], $this->sectionSettings[$section]);
    }

    public function toggleSection(string $section): void
    {
        abort_unless(array_key_exists($section, InvitationTemplate::SECTION_DEFINITIONS), 422);
        abort_if(InvitationTemplate::SECTION_DEFINITIONS[$section]['required'], 422);
        $this->sectionVisibility[$section] = ! ($this->sectionVisibility[$section] ?? true);
    }

    public function moveSection(int $index, int $direction): void
    {
        $target = $index + $direction;
        if (! isset($this->sections[$index], $this->sections[$target])) {
            return;
        }

        [$this->sections[$index], $this->sections[$target]] = [$this->sections[$target], $this->sections[$index]];
    }

    public function resetSection(string $section): void
    {
        abort_unless(array_key_exists($section, InvitationTemplate::SECTION_DEFINITIONS), 422);
        $this->sectionSettings[$section] = InvitationTemplate::defaultSectionSettings($section);
    }

    public function save(): void
    {
        $this->persistTemplate($this->status, $this->status === 'published');
    }

    public function saveDraft(): void
    {
        $this->status = 'draft';
        $this->persistTemplate('draft', false);
    }

    public function publish(): void
    {
        foreach (InvitationTemplate::SECTION_DEFINITIONS as $key => $definition) {
            if ($definition['required'] && (! in_array($key, $this->sections, true) || ! ($this->sectionVisibility[$key] ?? false))) {
                $this->addError('sections', 'Section wajib harus aktif sebelum template dipublish.');
                return;
            }
        }

        $this->status = 'published';
        $this->persistTemplate('published', true);
    }

    public function rollback(): void
    {
        abort_unless(auth()->user()?->can('templates.update'), 403);
        $template = InvitationTemplate::query()->findOrFail($this->templateId);
        $snapshot = data_get($template->schema, 'snapshot.schema');
        if (! is_array($snapshot)) {
            $this->addError('sections', 'Belum ada snapshot yang dapat dipulihkan.');
            return;
        }
        $template->update(['schema' => $snapshot, 'is_active' => false]);
        session()->flash('success', 'Snapshot template berhasil dipulihkan sebagai draft.');
        $this->redirectRoute('admin.invitation-templates.edit', ['template' => $template->getKey()]);
    }

    public function unpublish(): void
    {
        $this->status = 'archived';
        $this->persistTemplate('archived', false);
    }

    private function persistTemplate(string $status, bool $isActive): void
    {
        $this->backgroundElements = $this->normalizeCircleElements($this->backgroundElements);
        $this->syncActiveBackground();
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('invitation_templates', 'slug')->ignore($this->templateId)],
            'description' => ['nullable', 'string', 'max:5000'],
            'primaryColor' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondaryColor' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'textColor' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'backgroundColor' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'desktopBackgroundColor' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'navBackgroundColor' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'navActiveColor' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'navTextColor' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'navIconColor' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'backgroundName' => ['required', 'string', 'max:100'],
            'fontFamily' => ['required', Rule::in(['Inter', 'Poppins', 'Playfair Display', 'Cormorant Garamond', 'Merriweather'])],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*' => ['in:hero,event_info,location,gallery,speakers,rsvp,qr,guestbook,contact'],
            'eventId' => ['nullable', 'integer'],
            'heroMediaId' => ['nullable', 'integer'],
            'heroMediaAlt' => ['nullable', 'string', 'max:255'],
            'heroMediaPosition' => ['required', Rule::in(['top', 'center', 'bottom'])],
            'galleryMediaIds' => ['array'],
            'galleryMediaIds.*' => ['integer'],
            'circlePrimary' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'circleBlue' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'circleLight' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'backgroundElements' => ['array'],
            'backgroundElements.*.id' => ['required', 'string', 'max:64'],
            'backgroundElements.*.type' => ['required', 'in:rectangle,line,arrow,ellipse,polygon,star,image'],
            'backgroundElements.*.x' => ['required', 'numeric', 'min:-30', 'max:100'],
            'backgroundElements.*.y' => ['required', 'numeric', 'min:-30', 'max:100'],
            'backgroundElements.*.width' => ['required', 'numeric', 'min:1', 'max:100'],
            'backgroundElements.*.height' => ['required', 'numeric', 'min:1', 'max:100'],
            'backgroundElements.*.rotation' => ['required', 'numeric', 'min:-360', 'max:360'],
            'backgroundElements.*.fill' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'backgroundElements.*.opacity' => ['required', 'numeric', 'min:0', 'max:100'],
            'backgroundElements.*.radius' => ['required', 'numeric', 'min:0', 'max:999'],
            'backgroundElements.*.url' => ['nullable', 'url', 'max:2000'],
            'backgrounds' => ['array', 'min:1'],
            'backgrounds.*.id' => ['required', 'string', 'max:64'],
            'backgrounds.*.name' => ['required', 'string', 'max:100'],
            'backgrounds.*.color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'backgrounds.*.elements' => ['array'],
            'backgrounds.*.elements.*.id' => ['required', 'string', 'max:64'],
            'backgrounds.*.elements.*.type' => ['required', 'in:rectangle,line,arrow,ellipse,polygon,star,image'],
            'backgrounds.*.elements.*.x' => ['required', 'numeric', 'min:-30', 'max:100'],
            'backgrounds.*.elements.*.y' => ['required', 'numeric', 'min:-30', 'max:100'],
            'backgrounds.*.elements.*.fill' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'floatingImages' => ['array'],
            'floatingImages.*' => ['array'],
            'floatingImages.*.*.url' => ['required', 'url', 'max:2000'],
            'floatingImages.*.*.position' => ['required', 'in:top-left,top-right,middle-left,middle-right,bottom-left,bottom-right'],
            'floatingImages.*.*.size' => ['required', 'in:sm,md,lg'],
            'floatingImages.*.*.opacity' => ['required', 'integer', 'min:10', 'max:100'],
        ]);

        $galleryMediaIds = array_values(array_unique(array_map('intval', $data['galleryMediaIds'] ?? [])));
        if ($galleryMediaIds !== []) {
            abort_unless(filled($data['eventId']), 422);
            abort_unless($this->eventMedia()->whereIn('id', $galleryMediaIds)->count() === count($galleryMediaIds), 422);
        }

        $heroMedia = null;
        if (filled($data['heroMediaId'])) {
            abort_unless(filled($data['eventId']), 422);
            $heroMedia = $this->eventMedia()->whereKey($data['heroMediaId'])->firstOrFail();
        }

        if (!$this->hasReadableContrast($data['textColor'], $data['backgroundColor'])) {             $this->addError('textColor', 'Warna teks dan background terlalu rendah kontrasnya.');             return;         } 
        $existingSchema = $this->templateId ? InvitationTemplate::query()->find($this->templateId)?->schema : null;
        $snapshot = is_array($existingSchema) ? $existingSchema : null;
        if (is_array($snapshot)) {
            unset($snapshot['snapshot']);
        }

        $schema = InvitationTemplate::normalizeSchema([
            'version' => InvitationTemplate::SCHEMA_VERSION,
            'status' => $status,
            'event' => $this->eventConfig,
            'buttons' => $this->buttonConfig,
            'rsvp' => $this->rsvpConfig,
            'qr' => $this->qrConfig,
            'decorations' => [
                'enabled' => $this->decorationsEnabled,
                'style' => 'blue-circles',
                'colors' => [
                    'primary' => $data['circlePrimary'],
                    'blue' => $data['circleBlue'],
                    'light' => $data['circleLight'],
                ],
            ],
            'floating_images' => $this->floatingImages,
            'background_elements' => $this->backgroundElements,
            'backgrounds' => $this->backgrounds,
            'active_background_id' => $this->activeBackgroundId,
            'media' => [
                'hero' => [
                    'media_id' => $heroMedia?->getKey(),
                    'url' => $heroMedia?->url,
                    'alt' => $data['heroMediaAlt'] ?: ($heroMedia?->name ?? ''),
                    'position' => $data['heroMediaPosition'],
                ],
                'gallery' => $galleryMediaIds,
            ],
            'navigation' => [
                'background_color' => $data['navBackgroundColor'],
                'active_color' => $data['navActiveColor'],
                'text_color' => $data['navTextColor'],
                'icon_color' => $data['navIconColor'],
            ],
            'theme' => [
                'heading_font_family' => $this->headingFontFamily,
                'body_font_family' => $this->bodyFontFamily,
                'text_color' => $this->textColor,
                'background_color' => $this->backgroundColor,
                'desktop_background_color' => $data['desktopBackgroundColor'],
                'heading_size' => $this->headingSize,
                'body_size' => $this->bodySize,
                'radius' => $this->radius,
                'button_style' => $this->buttonStyle,
                'card_style' => $this->cardStyle,
                'primary_color' => $data['primaryColor'],
                'secondary_color' => $data['secondaryColor'],
                'font_family' => $data['fontFamily'],
            ],
            'sections' => array_map(fn (string $section): array => [
                'key' => $section,
                'visible' => $this->sectionVisibility[$section] ?? true,
                'settings' => $this->sectionSettings[$section] ?? InvitationTemplate::defaultSectionSettings($section),
            ], InvitationTemplate::normalizeSections($data['sections'])),
        ]);

        $template = InvitationTemplate::query()->updateOrCreate(
            ['id' => $this->templateId],
            [
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?: null,
                'is_active' => $isActive,
                'schema' => $schema,
            ],
        );

        if (filled($data['eventId'])) {
            abort_unless($this->accessibleEvents()->whereKey($data['eventId'])->exists(), 403);
            $template->events()->sync([$data['eventId']]);
        } else {
            $template->events()->detach();
        }

        $this->templateId = $template->getKey();
        $this->status = $status;
        session()->flash('success', $status === 'published' ? 'Template berhasil dipublish.' : 'Draft template berhasil disimpan.');
    }

    private function hasReadableContrast(string $foreground, string $background): bool
    {
        $foregroundRgb = array_map(fn (string $value): int => hexdec($value), str_split(substr($foreground, 1), 2));
        $backgroundRgb = array_map(fn (string $value): int => hexdec($value), str_split(substr($background, 1), 2));
        $luminance = static function (array $rgb): float {
            $channels = array_map(fn (int $channel): float => ($channel / 255) <= 0.03928 ? $channel / 255 / 12.92 : (($channel / 255 + 0.055) / 1.055) ** 2.4, $rgb);
            return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
        };
        $foregroundLuminance = $luminance($foregroundRgb);
        $backgroundLuminance = $luminance($backgroundRgb);
        $ratio = (max($foregroundLuminance, $backgroundLuminance) + 0.05) / (min($foregroundLuminance, $backgroundLuminance) + 0.05);
        return $ratio >= 4.5;
    }

    private function accessibleEvents(): Builder
    {
        $user = auth()->user();

        return Event::query()->when(
            ! $user->hasRole('Super Admin'),
            fn (Builder $query): Builder => $query->where(
                fn (Builder $query): Builder => $query
                    ->where('created_by', $user->getKey())
                    ->orWhereHas('admins', fn (Builder $query): Builder => $query->whereKey($user->getKey())),
            ),
        );
    }

    private function eventMedia(): Builder
    {
        return MediaFile::query()
            ->whereIn('event_id', $this->accessibleEvents()->select('id'))
            ->where('event_id', $this->eventId ?: 0)
            ->where('mime_type', 'like', 'image/%')
            ->whereNotNull('url')
            ->latest();
    }
};
?>
<style>
.template-shape--line,.template-shape--arrow{height:max(2px,.7%);border-radius:999px}.template-shape--ellipse{height:auto!important;aspect-ratio:1/1}.template-shape--arrow{transform-origin:left center}.template-shape--arrow::after{content:'';position:absolute;right:-1px;top:50%;width:9px;height:9px;border-top:2px solid currentColor;border-right:2px solid currentColor;transform:translateY(-50%) rotate(45deg)}.template-shape--polygon{clip-path:polygon(50% 0%,100% 100%,0% 100%)}.template-shape--star{clip-path:polygon(50% 0%,61% 35%,98% 35%,68% 57%,79% 100%,50% 74%,21% 100%,32% 57%,2% 35%,39% 35%)}
.tool-button{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;border:1px solid #e2e8f0;border-radius:.625rem;background:#fff;padding:.5rem .75rem;font-size:.75rem;font-weight:600;color:#334155}.tool-button:hover{border-color:#93c5fd;background:#eff6ff}.inspector-input{margin-top:.25rem;width:100%;border:1px solid #cbd5e1;border-radius:.5rem;padding:.5rem .75rem;font-size:.75rem}</style>
<div class="mx-auto w-full max-w-[1480px] space-y-6 pb-12" x-data="{ panel: 'style' }">
<header class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 pb-5"><div class="flex items-center gap-4"><a href="{{ route('admin.invitation-templates') }}" class="grid size-10 place-items-center rounded-xl border border-slate-200 bg-white text-slate-700 hover:border-brand-300 hover:bg-brand-50" aria-label="Kembali"><i data-lucide="arrow-left" class="size-5"></i></a><div><p class="text-xs font-semibold uppercase tracking-[0.18em] text-brand-600">Template studio</p><div class="mt-1 flex items-center gap-3"><h1 class="text-2xl font-semibold tracking-tight text-brand-950">{{ $templateId ? 'Edit template' : 'Template baru' }}</h1><span class="rounded-full border border-slate-200 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-500">{{ $status }}</span></div><p class="mt-1 text-sm text-slate-500">Bangun visual template dengan elemen yang bisa dipindah dan diwarnai langsung.</p></div></div><div class="flex items-center gap-3"><button type="button" wire:click="setMode('preview')" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:border-brand-300 hover:bg-brand-50"><i data-lucide="eye" class="size-4"></i>Preview</button><button type="button" wire:click="saveDraft" class="inline-flex items-center gap-2 rounded-xl bg-brand-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800"><i data-lucide="save" class="size-4"></i>Simpan draft</button></div></header>
@if (session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>@endif
@if ($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">Periksa pengaturan yang ditandai sebelum menyimpan.</div>@endif
@if ($mode === 'edit')
<div class="grid gap-6 xl:grid-cols-[230px_minmax(0,1fr)_300px] xl:items-start" x-data="templateCanvas($wire.entangle('backgroundElements').live)">
<aside class="space-y-3 xl:sticky xl:top-24"><div class="rounded-2xl border border-slate-200 bg-white p-3"><p class="px-3 pb-2 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Template tools</p><nav class="grid gap-1"><button type="button" @click="panel='style'" :class="panel==='style'?'bg-brand-950 text-white':'text-slate-600 hover:bg-slate-50'" class="flex items-center gap-3 rounded-xl px-3 py-3 text-left text-sm font-semibold"><i data-lucide="sliders-horizontal" class="size-4"></i>Identitas & style</button><button type="button" @click="panel='canvas'" :class="panel==='canvas'?'bg-brand-950 text-white':'text-slate-600 hover:bg-slate-50'" class="flex items-center gap-3 rounded-xl px-3 py-3 text-left text-sm font-semibold"><i data-lucide="mouse-pointer-2" class="size-4"></i>Visual editor</button></nav></div><div class="rounded-2xl border border-cyan-200 bg-cyan-50 p-4"><p class="text-xs font-semibold text-cyan-950">Blue Workshop</p><p class="mt-1 text-xs leading-5 text-cyan-800">Elemen visual disimpan di template dan bisa dipakai ulang.</p></div></aside>
<main class="min-w-0 space-y-5">
<section x-show="panel==='style'" x-cloak class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6"><div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand-600">01 · Identitas</p><h2 class="mt-1 text-xl font-semibold text-brand-950">Nama dan karakter template</h2></div><div class="grid gap-4 md:grid-cols-2"><label class="text-sm font-semibold text-slate-700">Nama template<input wire:model="name" class="mt-2 w-full rounded-xl border-slate-300 px-4 py-3" placeholder="Blue Workshop">@error('name')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror</label><label class="text-sm font-semibold text-slate-700">Slug<input wire:model="slug" class="mt-2 w-full rounded-xl border-slate-300 px-4 py-3" placeholder="blue-workshop">@error('slug')<span class="mt-1 block text-xs text-red-600">{{ $message }}</span>@enderror</label></div><label class="block text-sm font-semibold text-slate-700">Deskripsi<textarea wire:model="description" rows="3" class="mt-2 w-full rounded-xl border-slate-300 px-4 py-3"></textarea></label><div class="grid gap-4 md:grid-cols-2"><label class="text-sm font-semibold text-slate-700">Font heading<select wire:model="headingFontFamily" class="mt-2 w-full rounded-xl border-slate-300 px-4 py-3"><option>Inter</option><option>Poppins</option><option>Playfair Display</option><option>Cormorant Garamond</option><option>Merriweather</option></select></label><label class="text-sm font-semibold text-slate-700">Font body<select wire:model="bodyFontFamily" class="mt-2 w-full rounded-xl border-slate-300 px-4 py-3"><option>Inter</option><option>Poppins</option><option>Playfair Display</option><option>Cormorant Garamond</option><option>Merriweather</option></select></label></div><div class="grid gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 md:grid-cols-3"><label class="text-sm font-semibold text-slate-700">Warna utama<div class="mt-2 flex items-center gap-2"><input wire:model="primaryColor" type="color" class="size-10 rounded-lg border border-slate-300 bg-white p-1"><input wire:model="primaryColor" class="min-w-0 flex-1 rounded-lg border-slate-300 px-3 py-2 font-mono text-sm uppercase" maxlength="7"></div></label><label class="text-sm font-semibold text-slate-700">Background mobile<div class="mt-2 flex items-center gap-2"><input wire:model="backgroundColor" type="color" class="size-10 rounded-lg border border-slate-300 bg-white p-1"><input wire:model="backgroundColor" class="min-w-0 flex-1 rounded-lg border-slate-300 px-3 py-2 font-mono text-sm uppercase" maxlength="7"></div></label><label class="text-sm font-semibold text-slate-700">Background PC<div class="mt-2 flex items-center gap-2"><input wire:model="desktopBackgroundColor" type="color" class="size-10 rounded-lg border border-slate-300 bg-white p-1"><input wire:model="desktopBackgroundColor" class="min-w-0 flex-1 rounded-lg border-slate-300 px-3 py-2 font-mono text-sm uppercase" maxlength="7"></div><span class="mt-1 block text-xs font-normal text-slate-500">Warna area kiri-kanan saat dibuka di desktop.</span></label></div><div class="space-y-5 rounded-2xl border border-slate-200 bg-slate-50 p-4"><label class="block text-sm font-semibold text-slate-700">Nav background<div class="mt-2 flex items-center gap-2"><input wire:model="navBackgroundColor" type="color" class="size-10 rounded-lg border border-slate-300 bg-white p-1"><input wire:model="navBackgroundColor" class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-sm uppercase" maxlength="7" spellcheck="false"></div></label><label class="block text-sm font-semibold text-slate-700">Nav aktif<div class="mt-2 flex items-center gap-2"><input wire:model="navActiveColor" type="color" class="size-10 rounded-lg border border-slate-300 bg-white p-1"><input wire:model="navActiveColor" class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-sm uppercase" maxlength="7" spellcheck="false"></div></label><label class="block text-sm font-semibold text-slate-700">Warna teks<div class="mt-2 flex items-center gap-2"><input wire:model="navTextColor" type="color" class="size-10 rounded-lg border border-slate-300 bg-white p-1"><input wire:model="navTextColor" class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-sm uppercase" maxlength="7" spellcheck="false"></div></label><label class="block text-sm font-semibold text-slate-700">Warna icon<div class="mt-2 flex items-center gap-2"><input wire:model="navIconColor" type="color" class="size-10 rounded-lg border border-slate-300 bg-white p-1"><input wire:model="navIconColor" class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-sm uppercase" maxlength="7" spellcheck="false"></div></label></div><div class="flex gap-3 border-t border-slate-100 pt-5"><button type="button" wire:click="applyBrandPreset" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700">Pakai preset brand</button><button type="button" wire:click="resetVisuals" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700">Reset visual</button></div></section>
<section x-show="panel==='canvas'" x-cloak class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6"><div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand-600">02 · Visual editor</p><h2 class="mt-1 text-xl font-semibold text-brand-950">Susun background template</h2><p class="mt-1 text-sm text-slate-500">Tambahkan bentuk dasar, lalu drag langsung di canvas. Semua perubahan terlihat real-time.</p></div><div class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-4"><div class="flex flex-wrap items-center justify-between gap-3"><div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand-600">Background library</p><p class="mt-1 text-xs text-slate-500">Simpan beberapa background, lalu susun urutannya.</p></div><button type="button" wire:click="createBackground" class="tool-button"><i data-lucide="plus" class="size-4"></i>Background baru</button></div><div class="grid gap-2 sm:grid-cols-2">@foreach ($backgrounds as $index => $background)<div class="flex items-center gap-2 rounded-xl border {{ $activeBackgroundId === $background['id'] ? 'border-brand-500 bg-white' : 'border-slate-200 bg-white/70' }} p-2"><button type="button" wire:click="selectBackground('{{ $background['id'] }}')" class="min-w-0 flex-1 text-left"><span class="flex items-center gap-2"><span class="size-5 shrink-0 rounded-md border border-slate-200" style="background: {{ $background['color'] }}"></span><span class="truncate text-xs font-semibold text-brand-950">{{ $background['name'] }}</span></span></button><button type="button" wire:click="moveBackground({{ $index }}, -1)" class="grid size-7 place-items-center rounded-lg text-slate-500 hover:bg-slate-100" aria-label="Naikkan background"><i data-lucide="chevron-up" class="size-4"></i></button><button type="button" wire:click="moveBackground({{ $index }}, 1)" class="grid size-7 place-items-center rounded-lg text-slate-500 hover:bg-slate-100" aria-label="Turunkan background"><i data-lucide="chevron-down" class="size-4"></i></button></div>@endforeach</div></div><div class="flex flex-wrap gap-2 rounded-xl border border-slate-200 bg-slate-50 p-2"><button type="button" @click="add('rectangle')" class="tool-button"><i data-lucide="square" class="size-4"></i>Kotak</button><button type="button" @click="add('line')" class="tool-button"><i data-lucide="minus" class="size-4"></i>Garis</button><button type="button" @click="add('arrow')" class="tool-button"><i data-lucide="arrow-up-right" class="size-4"></i>Panah</button><button type="button" @click="add('ellipse')" class="tool-button"><i data-lucide="circle" class="size-4"></i>Bulat</button><button type="button" @click="add('polygon')" class="tool-button"><i data-lucide="triangle" class="size-4"></i>Segitiga</button><button type="button" @click="add('star')" class="tool-button"><i data-lucide="star" class="size-4"></i>Bintang</button><button type="button" @click="add('image')" class="tool-button"><i data-lucide="image" class="size-4"></i>Gambar</button></div><div class="relative overflow-hidden rounded-2xl border border-slate-300 bg-slate-100 p-5"><div data-template-canvas class="relative mx-auto aspect-[9/14] max-h-[680px] w-full max-w-[440px] overflow-hidden rounded-xl border border-slate-200" style="background: {{ $backgroundColor }}" @pointermove="drag($event)" @pointerup="stopDrag()" @pointerleave="stopDrag()" @click="selectedId=null"><template x-for="element in elements" :key="element.id"><div class="template-shape absolute cursor-move select-none" :class="shapeClass(element)" :style="elementStyle(element)" @pointerdown.stop="startDrag($event,element.id)" @click.stop="select(element.id)"><template x-if="element.type==='image' && element.url"><img :src="element.url" alt="" class="size-full object-cover"></template><template x-if="element.type==='image' && !element.url"><div class="grid size-full place-items-center border border-dashed border-brand-400 bg-white/60 text-brand-700"><i data-lucide="image" class="size-6"></i></div></template><span x-show="selectedId===element.id" class="pointer-events-none absolute -inset-1 rounded-md border-2 border-brand-600"></span></div></template><div class="pointer-events-none absolute inset-0 flex items-end justify-center p-6"><span class="rounded-full border border-current/20 bg-white/70 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Canvas background</span></div></div></div><p class="text-xs text-slate-500">Klik elemen untuk mengedit. Drag elemen untuk memindahkannya.</p></section>


</main>
<aside class="space-y-5 xl:sticky xl:top-24"><section class="overflow-hidden rounded-2xl border border-slate-200 bg-white"><div class="flex items-center justify-between border-b border-slate-200 px-4 py-4"><div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand-600">Inspector</p><h2 class="mt-1 text-lg font-semibold text-brand-950">Properti elemen</h2></div><i data-lucide="palette" class="size-5 text-brand-600"></i></div><div class="space-y-4 p-4" x-show="selected"><div class="grid grid-cols-2 gap-3"><label class="text-xs font-semibold text-slate-600">X (%)<input type="number" x-model.number="selected.x" class="inspector-input"></label><label class="text-xs font-semibold text-slate-600">Y (%)<input type="number" x-model.number="selected.y" class="inspector-input"></label><label class="text-xs font-semibold text-slate-600">Lebar (%)<input type="number" min="1" max="100" x-model.number="selected.width" @input="syncCircleSize('width')" class="inspector-input"></label><label class="text-xs font-semibold text-slate-600">Tinggi (%)<input type="number" min="1" max="100" x-model.number="selected.height" @input="syncCircleSize('height')" class="inspector-input"></label><label class="text-xs font-semibold text-slate-600">Rotasi<input type="number" x-model.number="selected.rotation" class="inspector-input"></label><label class="text-xs font-semibold text-slate-600">Radius<input type="number" x-model.number="selected.radius" class="inspector-input"></label></div><label class="block text-xs font-semibold text-slate-600">Warna HEX<div class="mt-1 flex items-center gap-2"><input type="color" x-model="selected.fill" class="size-10 rounded-lg border border-slate-300 bg-white p-1"><input type="text" x-model="selected.fill" maxlength="7" class="min-w-0 flex-1 rounded-lg border-slate-300 px-3 py-2 font-mono text-sm uppercase"></div></label><label class="block text-xs font-semibold text-slate-600">Opacity <span x-text="`${selected.opacity}%`"></span><input type="range" min="0" max="100" x-model.number="selected.opacity" class="mt-2 w-full accent-brand-600"></label><label x-show="selected.type==='image'" class="block text-xs font-semibold text-slate-600">URL gambar<input type="url" x-model="selected.url" class="inspector-input" placeholder="https://..."></label><div class="flex gap-2 border-t border-slate-100 pt-4"><button type="button" @click="duplicateSelected()" class="tool-button flex-1"><i data-lucide="copy" class="size-4"></i>Duplikat</button><button type="button" @click="deleteSelected()" class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50"><i data-lucide="trash-2" class="size-4"></i>Hapus</button></div></div><div x-show="!selected" class="p-8 text-center text-sm text-slate-500">Pilih bentuk di canvas untuk melihat propertinya.</div></section></aside>
</div>
@else
<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 px-5 py-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand-600">Preview template</p>
            <h2 class="mt-1 text-xl font-semibold text-brand-950">Tampilan undangan</h2>
            <p class="mt-1 text-sm text-slate-500">Preview memakai perubahan visual terbaru dari editor.</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" wire:click="setPreviewDevice('mobile')" class="inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-xs font-semibold {{ $previewDevice === 'mobile' ? 'border-brand-950 bg-brand-950 text-white' : 'border-slate-200 text-slate-600 hover:border-brand-300 hover:bg-brand-50' }}"><i data-lucide="smartphone" class="size-4"></i>Mobile</button>
            <button type="button" wire:click="setPreviewDevice('desktop')" class="inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-xs font-semibold {{ $previewDevice === 'desktop' ? 'border-brand-950 bg-brand-950 text-white' : 'border-slate-200 text-slate-600 hover:border-brand-300 hover:bg-brand-50' }}"><i data-lucide="monitor" class="size-4"></i>Desktop</button>
            <button type="button" wire:click="setMode('edit')" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:border-brand-300 hover:bg-brand-50"><i data-lucide="arrow-left" class="size-4"></i>Kembali edit</button>
        </div>
    </div>
    <div class="flex min-h-[720px] justify-center p-5 sm:p-8" style="background: {{ $previewDevice === 'desktop' ? $desktopBackgroundColor : '#f1f5f9' }}">
        <div class="w-full {{ $previewDevice === 'mobile' ? 'max-w-[390px]' : 'max-w-[560px]' }} overflow-hidden rounded-[2rem] border border-slate-300 bg-white">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 text-xs text-slate-500">
                <span class="font-semibold text-brand-950">{{ $name ?: 'Preview template' }}</span>
                <span class="rounded-full border border-slate-200 px-2 py-1">{{ $slug ?: 'template-preview' }}</span>
            </div>
            <div class="relative min-h-[650px] overflow-hidden px-7 pb-12 pt-8" style="background: {{ $backgroundColor }}; color: {{ $textColor }}">
                @foreach ($backgroundElements as $element)
                    @php
                        $elementType = $element['type'] ?? 'rectangle';
                        $elementStyle = implode(';', [
                            'left: '.(float) ($element['x'] ?? 0).'%',
                            'top: '.(float) ($element['y'] ?? 0).'%',
                            'width: '.(float) ($element['width'] ?? 10).'%',
                            'height: '.(float) ($element['height'] ?? 10).'%',
                            'background: '.($element['fill'] ?? '#168DE2'),
                            'opacity: '.((float) ($element['opacity'] ?? 100) / 100),
                            'border-radius: '.(float) ($element['radius'] ?? 0).'px',
                            'transform: rotate('.(float) ($element['rotation'] ?? 0).'deg)',
                        ]);
                    @endphp
                    @if ($elementType === 'image' && filled($element['url'] ?? null))
                        <img src="{{ $element['url'] }}" alt="" class="pointer-events-none absolute z-0 object-cover" style="{{ $elementStyle }}">
                    @else
                        <div class="pointer-events-none absolute z-0 template-shape--{{ $elementType }}" style="{{ $elementStyle }}"></div>
                    @endif
                @endforeach
                <div class="relative z-10 flex min-h-[590px] flex-col items-center text-center">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em]" style="color: {{ $primaryColor }}">{{ $eventConfig['recipient_label'] ?? 'Undangan untuk' }}</p>
                    <h3 class="mt-5 max-w-xl text-4xl font-semibold tracking-tight" style="color: {{ $primaryColor }}">{{ $previewEvent?->name ?: 'Nama event' }}</h3>
                    <p class="mt-4 max-w-md text-sm leading-6 opacity-75">{{ $previewEvent?->description ?: ($description ?: 'Detail undangan dan informasi event akan tampil di sini.') }}</p>
                    <div class="mt-auto grid w-full max-w-md gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl border border-current/15 bg-white/60 p-4 text-left">
                            <p class="text-[10px] font-bold uppercase tracking-[0.16em] opacity-55">{{ $eventConfig['date_label'] ?? 'Tanggal' }}</p>
                            <p class="mt-2 text-sm font-semibold">{{ $previewEvent?->event_date?->format('d M Y') ?: 'Belum diatur' }}</p>
                        </div>
                        <div class="rounded-2xl border border-current/15 bg-white/60 p-4 text-left">
                            <p class="text-[10px] font-bold uppercase tracking-[0.16em] opacity-55">{{ $eventConfig['time_label'] ?? 'Waktu' }}</p>
                            <p class="mt-2 text-sm font-semibold">{{ $previewEvent?->start_time?->format('H:i') ?: 'Belum diatur' }}</p>
                        </div>
                    </div>
                    <button type="button" class="mt-7 rounded-full px-6 py-3 text-sm font-semibold text-white" style="background: {{ $primaryColor }}">{{ $buttonConfig['rsvp']['label'] ?? 'Isi RSVP' }}</button>
                </div>
            </div>
        </div>
    </div>
</section>
@endif
</div>
