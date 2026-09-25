<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Hunting\Enums\HabitatCode;
use App\Domains\Hunting\Models\Habitat;
use App\Domains\Hunting\Services\SpeciesPublicCache;
use Illuminate\Database\Seeder;

/**
 * Controlled habitat vocabulary only. This is not Georgian species occurrence data.
 */
final class HabitatVocabularySeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            HabitatCode::Forest->value => ['ka' => 'ტყე', 'en' => 'Forest'],
            HabitatCode::Alpine->value => ['ka' => 'ალპური', 'en' => 'Alpine'],
            HabitatCode::Wetland->value => ['ka' => 'ჭაობი', 'en' => 'Wetland'],
            HabitatCode::Grassland->value => ['ka' => 'მდელო', 'en' => 'Grassland'],
            HabitatCode::AgriculturalLand->value => ['ka' => 'სასოფლო-სამეურნეო მიწა', 'en' => 'Agricultural land'],
            HabitatCode::River->value => ['ka' => 'მდინარე', 'en' => 'River'],
            HabitatCode::Stream->value => ['ka' => 'ნაკადული', 'en' => 'Stream'],
            HabitatCode::Lake->value => ['ka' => 'ტბა', 'en' => 'Lake'],
            HabitatCode::Reservoir->value => ['ka' => 'წყალსაცავი', 'en' => 'Reservoir'],
            HabitatCode::Coastal->value => ['ka' => 'სანაპირო', 'en' => 'Coastal'],
            HabitatCode::Marine->value => ['ka' => 'ზღვა', 'en' => 'Marine'],
            HabitatCode::Rocky->value => ['ka' => 'კლდოვანი', 'en' => 'Rocky'],
            HabitatCode::Mixed->value => ['ka' => 'შერეული', 'en' => 'Mixed'],
        ];

        foreach (HabitatCode::cases() as $index => $code) {
            $habitat = Habitat::query()->updateOrCreate(
                ['code' => $code->value],
                ['is_active' => true, 'sort_order' => $index],
            );
            foreach ($names[$code->value] as $locale => $name) {
                $habitat->translations()->updateOrCreate(
                    ['locale' => $locale],
                    ['name' => $name],
                );
            }
        }

        app(SpeciesPublicCache::class)->bump();
    }
}
