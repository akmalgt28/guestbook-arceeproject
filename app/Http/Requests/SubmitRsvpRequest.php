<?php

namespace App\Http\Requests;

use App\Models\Invitation;
use App\Models\InvitationTemplate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitRsvpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $fields = $this->rsvpFields();

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => [$this->fieldRule($fields, 'phone'), 'string', 'max:50'],
            'email' => [$this->fieldRule($fields, 'email'), 'email', 'max:255'],
            'status' => ['required', Rule::in(['attending', 'not_attending'])],
            'attending_count' => ['required', 'integer', 'min:0', 'max:100'],
            'family_member_ids' => ['nullable', 'array'],
            'family_member_ids.*' => ['integer'],
            'notes' => [$this->fieldRule($fields, 'notes'), 'string', 'max:1000'],
            'website' => ['nullable', 'max:0'],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function rsvpFields(): array
    {
        $token = $this->route('token');
        if (! is_string($token)) {
            return [];
        }

        $invitation = Invitation::query()
            ->with(['event.templates' => fn ($query) => $query->active()])
            ->forPublicLink($token)
            ->first();
        $schema = InvitationTemplate::normalizeSchema($invitation?->event?->templates?->first()?->schema ?? []);

        return collect($schema['rsvp']['fields'] ?? [])->keyBy('key')->all();
    }

    /** @param array<string, array<string, mixed>> $fields */
    private function fieldRule(array $fields, string $key): string
    {
        $field = $fields[$key] ?? [];

        return ($field['required'] ?? false) && ($field['visible'] ?? true) ? 'required' : 'nullable';
    }
}
