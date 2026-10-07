<?php

namespace App\Planner\Services\Import;

use App\Planner\Enums\MatchType;
use App\Planner\Enums\Source;
use App\Planner\Models\Court;
use App\Planner\Models\Venue;
use App\Planner\Models\VenueAlias;
use App\Planner\Support\VenueName;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Finds (or creates) the venue and court of an imported planning row.
 */
class ImportVenues
{
    public const METHOD_VENUE_CODE = 'venue_code';

    public const METHOD_ALIAS = 'alias';

    public const METHOD_EXACT = 'exact';

    public const METHOD_NORMALIZED = 'normalized';

    /** @param  Collection<int, Venue>  $venues */
    public function match(Collection $venues, string $name, string $city, string $venueCode = ''): ?Venue
    {
        return $this->matchDetail($venues, $name, $city, $venueCode)[0];
    }

    /**
     * The venue for a name plus how it was found (one of the METHOD_* constants).
     *
     * @param  Collection<int, Venue>  $venues
     * @return array{0: ?Venue, 1: ?string}
     */
    public function matchDetail(Collection $venues, string $name, string $city, string $venueCode = ''): array
    {
        $venueCode = Str::lower(trim($venueCode));
        if ($venueCode !== '' && ($venue = $venues->first(fn (Venue $venue): bool => Str::lower(trim((string) $venue->venue_code)) === $venueCode))) {
            return [$venue, self::METHOD_VENUE_CODE];
        }

        $lowerName = Str::lower(trim($name));
        $alias = VenueAlias::query()->whereIn('venue_id', $venues->pluck('id'))
            ->whereRaw('LOWER(name) = ?', [$lowerName])->first();
        if ($alias && ($venue = $venues->firstWhere('id', $alias->venue_id))) {
            return [$venue, self::METHOD_ALIAS];
        }

        if ($venue = $venues->first(fn (Venue $venue): bool => Str::lower(trim($venue->name)) === $lowerName
            && ($city === '' || Str::lower(trim((string) $venue->city)) === Str::lower(trim($city))))) {
            return [$venue, self::METHOD_EXACT];
        }

        $normalized = VenueName::key($name);
        if ($venue = $venues->first(fn (Venue $venue): bool => VenueName::key($venue->name) === $normalized)) {
            return [$venue, self::METHOD_NORMALIZED];
        }

        return [null, null];
    }

    /**
     * The venue of a home row: an existing one (completing its code and city)
     * or a new one marked as coming from the match import. Away rows get no
     * venue, so opponents' venues never end up in the venue table.
     *
     * @param  array<string, mixed>  $row
     * @param  Collection<int, Venue>  $venues
     */
    public function forRow(array $row, Collection $venues): ?Venue
    {
        if ($row['type'] === MatchType::Away->value) {
            return null;
        }

        $venue = $this->match($venues, $row['venue'], $row['city'], $row['venue_code']);
        if ($venue) {
            $venue->fill(array_filter([
                'venue_code' => $venue->venue_code ?: $row['venue_code'],
                'city' => $venue->city ?: $row['city'],
            ]))->save();

            return $venue;
        }

        return Venue::create([
            'name' => $row['venue'], 'city' => $row['city'] ?: null,
            'venue_code' => $row['venue_code'] ?: null, 'source' => Source::MatchImport, 'is_active' => true,
        ]);
    }

    /**
     * Within one import the same venue/court combination occurs dozens of
     * times, so courts are remembered in the given cache.
     *
     * @param  Collection<string, Court>  $courts
     */
    public function courtFor(Collection $courts, ?Venue $venue, ?string $name): ?Court
    {
        if (!$venue) {
            return null;
        }

        $key = $venue->id.'|'.$name;

        return $courts[$key] ??= Court::firstOrCreate(['venue_id' => $venue->id, 'name' => $name]);
    }
}
