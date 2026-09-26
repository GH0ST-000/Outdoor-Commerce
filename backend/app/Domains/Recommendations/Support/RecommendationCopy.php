<?php

declare(strict_types=1);

namespace App\Domains\Recommendations\Support;

use App\Domains\Recommendations\Enums\ReasonCode;

final class RecommendationCopy
{
    /**
     * @return array<string, string>
     */
    public function bundle(string $locale): array
    {
        $locale = $locale === 'en' ? 'en' : 'ka';

        return $locale === 'en' ? $this->english() : $this->georgian();
    }

    public function line(string $locale, string $code): string
    {
        $bundle = $this->bundle($locale);

        return $bundle[$code] ?? $bundle[ReasonCode::ContextApplicable->value];
    }

    /**
     * @return array<string, string>
     */
    private function georgian(): array
    {
        return [
            ReasonCode::ActivityMatch->value => 'შეესაბამება არჩეულ აქტივობას.',
            ReasonCode::MethodMatch->value => 'თავსებადია არჩეულ მეთოდთან.',
            ReasonCode::SpeciesCategoryMatch->value => 'შეესაბამება არჩეულ სახეობის კატეგორიას.',
            ReasonCode::RequiredEquipmentMatch->value => 'შეესაბამება დადასტურებულ საჭირო აღჭურვილობის კატეგორიას.',
            ReasonCode::SeasonPhaseMatch->value => 'შეესაბამება არჩეულ სეზონის ფაზას.',
            ReasonCode::InStock->value => 'მარაგშია.',
            ReasonCode::ContextApplicable->value => 'შეესაბამება არჩეულ კონტექსტს.',
            ReasonCode::SpeciesRelatedUnlocated->value => 'სახეობასთან დაკავშირებული აღჭურვილობაა. მდებარეობა არ არის დადასტურებული.',
            ReasonCode::GeneralCatalogSuggestion->value => 'ზოგადი კატალოგის შეთავაზებაა და არ არის ადგილზე დამოწმებული.',
            ReasonCode::ConditionalRestriction->value => 'ეს შეთავაზებები საინფორმაციოა. გააგრძელებამდე გადაამოწმეთ მოთხოვნები.',
            ReasonCode::Promoted->value => 'პრომოირებული პროდუქტი.',
            ReasonCode::RegionMatch->value => 'შეესაბამება არჩეულ რეგიონს.',
            ReasonCode::ZoneTypeMatch->value => 'შეესაბამება არჩეულ ზონის ტიპს.',
            'gate_blocked' => 'ამ კონტექსტში აქტივობის აღჭურვილობა არ არის ნაჩვენები.',
            'gate_conflict' => 'სამართლებრივი კონტექსტი დაზუსტებას საჭიროებს. რეკომენდაციები დამალულია.',
            'gate_unknown' => 'საკმარისი დადასტურებული კონტექსტი არ არის. პროდუქტი არ ნიშნავს, რომ აქტივობა ნებადართულია.',
            'disclaimer' => 'პროდუქტის ჩვენება არ ცვლის სამართლებრივ შედეგს და არ არის ნებართვა.',
            'empty' => 'ამ კონტექსტისთვის თავსებადი პროდუქტი ვერ მოიძებნა.',
            'stale' => 'კონტექსტი შეიცვალა. წინა რეკომენდაცია აღარ არის მიმდინარე.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function english(): array
    {
        return [
            ReasonCode::ActivityMatch->value => 'Matches the selected activity.',
            ReasonCode::MethodMatch->value => 'Compatible with the selected method.',
            ReasonCode::SpeciesCategoryMatch->value => 'Relevant to the selected species category.',
            ReasonCode::RequiredEquipmentMatch->value => 'Matches a verified required equipment category.',
            ReasonCode::SeasonPhaseMatch->value => 'Suitable for the selected season phase.',
            ReasonCode::InStock->value => 'Available in stock.',
            ReasonCode::ContextApplicable->value => 'Applicable to the selected context.',
            ReasonCode::SpeciesRelatedUnlocated->value => 'Species-related gear. Location is not verified.',
            ReasonCode::GeneralCatalogSuggestion->value => 'A general catalog suggestion, not verified for this location.',
            ReasonCode::ConditionalRestriction->value => 'These suggestions are informational. Confirm the listed requirements before proceeding.',
            ReasonCode::Promoted->value => 'Promoted product.',
            ReasonCode::RegionMatch->value => 'Relevant to the selected region.',
            ReasonCode::ZoneTypeMatch->value => 'Relevant to the selected zone type.',
            'gate_blocked' => 'Activity-enabling equipment is not shown for this context.',
            'gate_conflict' => 'The legal context needs clarification. Contextual recommendations are hidden.',
            'gate_unknown' => 'Verified context is incomplete. A product is not evidence that the activity is allowed.',
            'disclaimer' => 'Showing a product does not change the legal result and is not permission.',
            'empty' => 'No compatible product was found for this context.',
            'stale' => 'The context changed. The previous recommendation is no longer current.',
        ];
    }
}
