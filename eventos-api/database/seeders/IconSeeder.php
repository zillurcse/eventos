<?php

namespace Database\Seeders;

use App\Models\Icon;
use Illuminate\Database\Seeder;

/**
 * Global icon catalog (architecture note: keys must match the frontend Icon
 * registry in eventos-admin/modules/expouse-admin/core/runtime/utils/icons.ts —
 * this table only curates which keys are selectable, not how they're drawn).
 */
class IconSeeder extends Seeder
{
    public function run(): void
    {
        $icons = [
            ['key' => 'users', 'label' => 'Users', 'category' => 'people'],
            ['key' => 'briefcase', 'label' => 'Briefcase', 'category' => 'people'],
            ['key' => 'store', 'label' => 'Store', 'category' => 'commerce'],
            ['key' => 'box', 'label' => 'Box', 'category' => 'commerce'],
            ['key' => 'layers', 'label' => 'Layers', 'category' => 'general'],
            ['key' => 'pie', 'label' => 'Pie Chart', 'category' => 'general'],
            ['key' => 'help', 'label' => 'Help', 'category' => 'general'],
            ['key' => 'calendar', 'label' => 'Calendar', 'category' => 'general'],
            ['key' => 'grid', 'label' => 'Grid', 'category' => 'general'],
            ['key' => 'link', 'label' => 'Link', 'category' => 'general'],
            ['key' => 'cog', 'label' => 'Settings', 'category' => 'general'],
            ['key' => 'star', 'label' => 'Star', 'category' => 'recognition'],
            ['key' => 'award', 'label' => 'Award', 'category' => 'recognition'],
            ['key' => 'heart', 'label' => 'Heart', 'category' => 'recognition'],
            ['key' => 'shield', 'label' => 'Shield', 'category' => 'recognition'],
            ['key' => 'flag', 'label' => 'Flag', 'category' => 'recognition'],
            ['key' => 'gift', 'label' => 'Gift', 'category' => 'recognition'],
            ['key' => 'mic', 'label' => 'Microphone', 'category' => 'media'],
            ['key' => 'camera', 'label' => 'Camera', 'category' => 'media'],
            ['key' => 'globe', 'label' => 'Globe', 'category' => 'media'],
            ['key' => 'target', 'label' => 'Target', 'category' => 'general'],
            ['key' => 'home', 'label' => 'Home', 'category' => 'general'],
            ['key' => 'bell', 'label' => 'Bell', 'category' => 'general'],
            ['key' => 'tag', 'label' => 'Tag', 'category' => 'commerce'],
            ['key' => 'clipboard', 'label' => 'Clipboard', 'category' => 'general'],
            ['key' => 'search', 'label' => 'Search', 'category' => 'general'],
            ['key' => 'logout', 'label' => 'Logout', 'category' => 'general'],
            ['key' => 'plus', 'label' => 'Plus', 'category' => 'general'],
        ];

        foreach ($icons as $i => $icon) {
            Icon::updateOrCreate(
                ['key' => $icon['key']],
                [...$icon, 'sort_order' => $i],
            );
        }
    }
}
