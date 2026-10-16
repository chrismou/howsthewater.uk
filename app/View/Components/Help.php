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
            'text' => 'A storm overflow upstream is releasing untreated sewage mixed with rainwater into the river at this moment. The monitors report within about 15 minutes, so this is as live as the data gets.',
        ],
        'history' => [
            'label' => 'How is the 30 day history built?',
            'text' => 'We record every spill the feed reports from the day a monitor was first seen. The feed itself has no history, so the chart only goes back as far as our own records.',
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
            'text' => 'The Environment Agency gauge (SEPA in Scotland) nearest this spot reads the water level every 15 minutes. Most gauges report the height above their own zero mark, so compare it with the typical range rather than other rivers.',
        ],
        'typical_range' => [
            'label' => 'What is the typical range?',
            'text' => 'The band the gauge usually sits in. Above it, the river is high for this stretch and likely to be fast and cold. Below it, the river is low.',
        ],
        'maod' => [
            'label' => 'What does mAOD mean?',
            'text' => 'Metres above Ordnance Datum: the height above mean sea level rather than above the river bed. The number looks large, so watch the trend and typical range instead.',
        ],
        'trend' => [
            'label' => 'How is the trend worked out?',
            'text' => 'The change over the last three hours of readings. Rising means the level has gone up by more than 3 cm in that time, falling means it has gone down by more than 3 cm.',
        ],
        'rainfall' => [
            'label' => 'Where is rainfall measured?',
            'text' => 'Totals from the Environment Agency rain gauge (SEPA in Scotland) nearest this spot, summed from its 15 minute readings. Rain further up the catchment may differ, and heavy rain is what triggers overflows.',
        ],
        'flood_warning' => [
            'label' => 'Where do flood warnings come from?',
            'text' => 'The Environment Agency issues flood alerts (flooding is possible), flood warnings (flooding is expected) and severe flood warnings (danger to life) for areas along each river. We show any in force for an area covering this stretch, checked every 15 minutes, in the Environment Agency\'s own words.',
        ],
        'bathing_classification' => [
            'label' => 'What does the classification mean?',
            'text' => 'Each designated bathing water is classified every year as Excellent, Good, Sufficient or Poor, from the last four seasons of Environment Agency samples. It describes those samples, not the water on any one day.',
        ],
        'pollution_risk' => [
            'label' => 'What is the pollution risk forecast?',
            'text' => 'In the bathing season (15 May to 30 September) the Environment Agency forecasts each day whether rain, wind or tide make short-term pollution more likely at sites where that happens. Increased means the risk is higher than usual today.',
        ],
        'bathing_samples' => [
            'label' => 'What do the sample counts mean?',
            'text' => 'Environment Agency samplers test the water about weekly in the season for two bacteria from sewage and animal waste: E. coli and intestinal enterococci, counted per 100 ml. Lower is better. A "<" means below the lowest level the test can measure.',
        ],
        'nearby_overflows' => [
            'label' => 'Which overflows count as nearby?',
            'text' => 'The sea and lakes have no upstream, so we list every storm overflow monitor within 3 km of the beach, the lake or one of its bathing sites, by straight-line distance. Overflows further away, or up rivers that flow in, can still affect the water.',
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
