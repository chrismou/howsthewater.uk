<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;

/**
 * A small "?" button that explains a term in a tooltip. The glossary lives
 * here so every page describes the data the same way.
 */
class Help extends Component
{
    /**
     * @var array<string, array{label: string, text: string}>
     */
    private const GLOSSARY = [
        'spill' => [
            'label' => 'What counts as a spill?',
            'text' => 'A spill is one period during which a storm overflow released untreated sewage, mixed with rainwater, into the river. The feed reports when each one started and ended, and we count every spill from an upstream monitor.',
        ],
        'discharging_now' => [
            'label' => 'What does discharging now mean?',
            'text' => 'A storm overflow is releasing untreated sewage mixed with rainwater at this moment. For a spot, it counts the overflows upstream of it: the spot itself is not discharging. The monitors report within about 15 minutes, so this is as live as the data gets.',
        ],
        'history' => [
            'label' => 'How is the 30 day history built?',
            'text' => 'We record every spill the live feed reports from the day a monitor was first seen. Before that, spills come from the water company\'s own published records, where it publishes them; the feed itself keeps no history.',
        ],
        'annual_return' => [
            'label' => 'What is the annual return?',
            'text' => 'Each year the water company reports every overflow\'s spills to the Environment Agency (Natural Resources Wales in Wales). It counts them in blocks: the first 12 hours of spilling count as one spill, and each 24 hours after that as one more, so its counts are lower than the number of separate spills we record. Total hours are the time spent spilling.',
        ],
        'uptime' => [
            'label' => 'What is monitor uptime?',
            'text' => 'The share of the time the overflow\'s monitor was working and reporting. While a monitor is offline, any spill it misses is not counted, so a low figure means the spill counts are likely to be too low.',
        ],
        'overflow_hours' => [
            'label' => 'How are these totals worked out?',
            'text' => 'From the water companies\' annual returns: each overflow\'s counted spills (in 12 and 24 hour blocks) and hours, added up for every overflow on the river and its tributaries, or in the region. Overflows spilling at the same time each add their own hours. Hours per overflow divide by the overflows with a return, so rivers with many overflows compare fairly with those with few.',
        ],
        'spill_hours' => [
            'label' => 'How are these hours counted?',
            'text' => 'Hours when at least one of these overflows was spilling. When several spill at once, those hours count once, so the total is never more than the time that passed. Each overflow\'s own hours are on its page.',
        ],
        'our_record' => [
            'label' => 'Where do these spills come from?',
            'text' => 'We read the water company\'s live feed every 15 minutes and keep every spill it reports, from the day we first saw this overflow; a spill that starts and stops between two reads can be missed. Spills before then, from January 2024, come from the company\'s own published records, which are recorded differently and may not match the live feed exactly. Some companies publish none, so for theirs the spills start when we first saw the overflow.',
        ],
        'upstream' => [
            'label' => 'Which monitors count as upstream?',
            'text' => 'Every storm overflow monitor whose discharge flows past this spot: those higher up the same river, plus everything on tributaries that join above it. Distances follow the water, not a straight line.',
        ],
        'offline' => [
            'label' => 'What does offline mean?',
            'text' => 'The monitor is not reporting, so we do not know whether that overflow is discharging. Offline monitors never count as "no spills".',
        ],
        'level' => [
            'label' => 'How is the river level measured?',
            'text' => 'The Environment Agency gauge (Natural Resources Wales in Wales, SEPA in Scotland) nearest this spot reads the water level every 15 minutes. Most gauges report the height above their own zero mark, so compare it with its usual range rather than other rivers.',
        ],
        'typical_range' => [
            'label' => 'What is the usual range?',
            'text' => 'The band this gauge sits in most of the time, which the agencies call its typical or normal range. Higher than usual, the river is high for this stretch and likely to be fast and cold. Lower than usual, it is low. It is the same all year, not adjusted for the season.',
        ],
        'maod' => [
            'label' => 'What does mAOD mean?',
            'text' => 'Metres above Ordnance Datum: the height above mean sea level rather than above the river bed. The number looks large, so watch the trend and the usual range instead.',
        ],
        'trend' => [
            'label' => 'How is the trend worked out?',
            'text' => 'The change over the last three hours of readings. Rising means the level has gone up by more than 3 cm in that time, falling means it has gone down by more than 3 cm.',
        ],
        'rainfall' => [
            'label' => 'Where is rainfall measured?',
            'text' => 'Totals from the Environment Agency rain gauge (Natural Resources Wales in Wales, SEPA in Scotland) nearest this spot, summed from its 15 minute readings. Rain further up the catchment may differ, and heavy rain is what triggers overflows.',
        ],
        'weather' => [
            'label' => 'Where does the weather come from?',
            'text' => 'A forecast from Open-Meteo, which uses the Met Office\'s UK model, for the few kilometres around this place, read every three hours. It is a forecast, not a measurement, and it can be windier and colder on the water. First and last light are when it is light enough outside to see without lights, about half an hour either side of sunrise and sunset.',
        ],
        'flood_warning' => [
            'label' => 'Where do flood warnings come from?',
            'text' => 'The Environment Agency (Natural Resources Wales in Wales) issues flood alerts (flooding is possible), flood warnings (flooding is expected) and severe flood warnings (danger to life) for areas along each river. We show any in force for an area covering this stretch, checked every 15 minutes, in the agency\'s own words.',
        ],
        'bathing_classification' => [
            'label' => 'What does the classification mean?',
            'text' => 'Each designated bathing water is classified every year as Excellent, Good, Sufficient or Poor, from the last four seasons of Environment Agency samples (Natural Resources Wales in Wales, SEPA in Scotland). It describes those samples, not the water on any one day.',
        ],
        'pollution_risk' => [
            'label' => 'What is the pollution risk forecast?',
            'text' => 'In the bathing season (15 May to 30 September) the Environment Agency (Natural Resources Wales in Wales) forecasts each day whether rain, wind or tide make short-term pollution more likely at sites where that happens. Increased means the risk is higher than usual today.',
        ],
        'bathing_samples' => [
            'label' => 'What do the sample counts mean?',
            'text' => 'Environment Agency samplers (Natural Resources Wales in Wales, SEPA in Scotland) test the water about weekly in the season for two bacteria from sewage and animal waste: E. coli and intestinal enterococci, counted per 100 ml. Lower is better. A "<" means below the lowest level the test can measure.',
        ],
        'nearby_overflows' => [
            'label' => 'Which overflows count as nearby?',
            'text' => 'The sea and lakes have no upstream, so we list every storm overflow monitor within 3 km of the beach, the lake or one of its bathing sites, by straight-line distance. Overflows further away, or up rivers that flow in, can still affect the water.',
        ],
        'not_covered' => [
            'label' => 'Why is overflow data not available here?',
            'text' => 'We do not read this water company\'s storm overflow feed yet, so we cannot say whether overflows upstream or nearby are discharging. It does not mean there are no overflows here.',
        ],
        'stale' => [
            'label' => 'What does out of date mean?',
            'text' => 'The feed behind this figure has not updated for over six hours. The number shown is the last one received, not the current state.',
        ],
    ];

    public readonly string $label;

    public readonly string $text;

    public function __construct(public string $term)
    {
        if (! isset(self::GLOSSARY[$term])) {
            throw new InvalidArgumentException("Unknown glossary term [{$term}].");
        }

        $this->label = self::GLOSSARY[$term]['label'];
        $this->text = self::GLOSSARY[$term]['text'];
    }

    /**
     * @return array<string, array{label: string, text: string}>
     */
    public static function glossary(): array
    {
        return self::GLOSSARY;
    }

    public function render(): View
    {
        return view('components.help');
    }
}
