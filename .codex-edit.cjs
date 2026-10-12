const fs = require('fs');
const path = 'resources/views/components/admin/⚡event-invitation-editor.blade.php';
let s = fs.readFileSync(path, 'utf8');
s = s.replace("            'sectionDraft.title' => ['nullable', 'string', 'max:255'],", "            'sectionDraft.title' => ['nullable', 'string', 'max:255'],\n            'sectionDraft.navigation_label' => ['required', 'string', 'max:30'],");
s = s.replace("        $pivotSettings['sections'] = array_values($sections);", `        $pivotSettings['sections'] = array_values($sections);
        $navigationKey = match ($data['editingSectionKey']) {
            'hero' => 'opening',
            'event_info' => 'event',
            'location' => 'maps',
            default => $data['editingSectionKey'],
        };
        $navigationItems = $schema['navigation']['items'] ?? InvitationTemplate::defaultNavigationItems();
        foreach ($navigationItems as $index => $item) {
            if (($item['key'] ?? null) === $navigationKey) {
                $navigationItems[$index]['label'] = trim($data['sectionDraft']['navigation_label']);
            }
        }
        $pivotSettings['navigation']['items'] = array_values($navigationItems);`);
s = s.replace("                            $sectionNavigationIcon = data_get(collect($schema['navigation']['items'] ?? [])->firstWhere('key', $navigationKey), 'icon', $sectionIcon);", "                            $sectionNavigationItem = collect($schema['navigation']['items'] ?? [])->firstWhere('key', $navigationKey);\n                            $sectionNavigationIcon = data_get($sectionNavigationItem, 'icon', $sectionIcon);\n                            $sectionNavigationLabel = data_get($sectionNavigationItem, 'label', $sectionLabel);");
s = s.replace("x-on:click=\"activeSection = @js($sectionKey); sectionDraft = JSON.parse(JSON.stringify($data.sectionData[@js($sectionKey)] || {})); \"", "x-on:click=\"activeSection = @js($sectionKey); sectionDraft = JSON.parse(JSON.stringify($data.sectionData[@js($sectionKey)] || {})); sectionDraft.navigation_label = @js($sectionNavigationLabel); \"");
s = s.replace('x-model="sectionDraft.title" class="min-w-0 flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm font-medium" placeholder="Nama section"', 'x-model="sectionDraft.navigation_label" class="min-w-0 flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm font-medium" placeholder="Nama menu navigasi"');
fs.writeFileSync(path, s);
