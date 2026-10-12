<?php

namespace Tests\Unit;

use App\Models\InvitationTemplate;
use PHPUnit\Framework\TestCase;

class InvitationTemplateSchemaTest extends TestCase
{
    public function test_default_schema_contains_required_sections_and_config_blocks(): void
    {
        $schema = InvitationTemplate::defaultSchema();

        $this->assertSame(InvitationTemplate::SCHEMA_VERSION, $schema['version']);
        $this->assertSame(['primary_color', 'secondary_color', 'font_family', 'heading_font_family', 'body_font_family', 'text_color', 'background_color', 'heading_size', 'body_size', 'radius', 'button_style', 'card_style'], array_keys($schema['theme']));
        $this->assertArrayHasKey('event', $schema);
        $this->assertArrayHasKey('media', $schema);
        $this->assertArrayHasKey('buttons', $schema);
        $this->assertArrayHasKey('fields', $schema['rsvp']);
        $this->assertArrayHasKey('display_after_rsvp', $schema['qr']);
        $this->assertContains('hero', InvitationTemplate::defaultSections());
        $this->assertContains('event_info', InvitationTemplate::defaultSections());
        $this->assertContains('rsvp', InvitationTemplate::defaultSections());
        $this->assertContains('hero', array_column($schema['sections'], 'key'));
    }

    public function test_normalize_sections_removes_unknown_and_duplicate_sections(): void
    {
        $sections = InvitationTemplate::normalizeSections(['hero', 'unknown', 'hero', 'rsvp']);

        $this->assertSame(['hero', 'rsvp'], $sections);
    }

    public function test_normalize_schema_supports_legacy_flat_schema(): void
    {
        $schema = InvitationTemplate::normalizeSchema([
            'primary_color' => '#123456',
            'secondary_color' => '#654321',
            'font_family' => 'Poppins',
            'sections' => ['hero', 'rsvp'],
        ]);

        $this->assertSame('#123456', $schema['theme']['primary_color']);
        $this->assertSame('#654321', $schema['theme']['secondary_color']);
        $this->assertSame('Poppins', $schema['theme']['font_family']);
        $this->assertSame(['hero', 'rsvp', 'event_info'], $schema['section_keys']);
        $this->assertSame('Undangan untuk', $schema['sections'][0]['settings']['recipient_label']);
    }

    public function test_normalize_schema_restores_required_sections_and_hides_inactive_sections(): void
    {
        $schema = InvitationTemplate::normalizeSchema([
            'sections' => [
                ['key' => 'hero', 'visible' => false],
                ['key' => 'location', 'visible' => true, 'settings' => ['title' => 'Venue']],
            ],
        ]);

        $this->assertSame(['location', 'event_info', 'rsvp'], $schema['section_keys']);
        $this->assertSame('Venue', collect($schema['sections'])->firstWhere('key', 'location')['settings']['title']);
        $this->assertContains('hero', array_column($schema['sections'], 'key'));
    }
}
