<?php

namespace CommonsBooking\Model;

use CommonsBooking\PostType\Timeframe;

class Day
{

    protected $date;

    protected $locations;

    protected $items;

    protected $types;

    /**
     * Day constructor.
     *
     * @param $date
     * @param $locations
     * @param $items
     * @param $types
     */
    public function __construct($date, $locations = [], $items = [], $types = [])
    {
        $this->date = $date;
        $this->locations = $locations;
        $this->items = $items;
        $this->types = $types;
    }

    /**
     * @return mixed
     */
    public function getDayOfWeek()
    {
        return date('w', strtotime($this->getDate()));
    }

    public function getDateObject()
    {
        return new \DateTime($this->getDate());
    }

    /**
     * @return mixed
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * @param mixed $date
     *
     * @return Day
     */
    public function setDate($date)
    {
        $this->date = $date;

        return $this;
    }

    public function getName()
    {
        return date('l', strtotime($this->getDate()));
    }

    protected function getTimeRangeQuery()
    {
        return array(
            'relation' => "OR",
            array(
                'key'     => 'start-date',
                'value'   => array(
                    date('Y-m-d\TH:i', strtotime($this->getDate())),
                    date('Y-m-d\TH:i', strtotime($this->getDate() . 'T23:59'))
                ),
                'compare' => 'BETWEEN',
                'type'    => 'DATE'
            ),
            array(
                'key'     => 'end-date',
                'value'   => array(
                    date('Y-m-d\TH:i', strtotime($this->getDate())),
                    date('Y-m-d\TH:i', strtotime($this->getDate() . 'T23:59'))
                ),
                'compare' => 'BETWEEN',
                'type'    => 'DATE'
            ),
            array(
                'relation' => "AND",
                array(
                    'key'     => 'start-date',
                    'value'   => date('Y-m-d\TH:i', strtotime($this->getDate())),
                    'compare' => '<',
                    'type'    => 'DATE'
                ),
                array(
                    'key'     => 'end-date',
                    'value'   => date('Y-m-d\TH:i', strtotime($this->getDate())),
                    'compare' => '>',
                    'type'    => 'DATE'
                )
            )
        );
    }

    protected function getLocationItemMetaQuery($locations, $items, $types)
    {
        $metaQuery = [
            'relation' => 'AND'
        ];

        if (count($locations)) {
            $metaQuery[] = array(
                'key'     => 'location-id',
                'value'   => $locations,
                'compare' => 'IN'
            );
        }

        if (count($items)) {
            $metaQuery[] = array(
                'key'     => 'item-id',
                'value'   => $items,
                'compare' => 'IN'
            );
        }

        if (count($types)) {
            $metaQuery[] = array(
                'key'     => 'type',
                'value'   => $types,
                'compare' => 'IN'
            );
        }

        return $metaQuery;
    }

    /**
     *
     * @return array|int[]|\WP_Post[]
     */
    public function getTimeframes()
    {
        // Default query
        $args = array(
            'post_type'  => Timeframe::getPostType(),
            'meta_query' => $this->getTimeRangeQuery()
        );

        // Filtered query (items, locations)
        if (count($this->locations) || count($this->items) || count($this->types)) {
            $args['meta_query'] = array(
                'relation' => 'AND',
                [$this->getTimeRangeQuery()],
                [$this->getLocationItemMetaQuery($this->locations, $this->items, $this->types)]
            );
        }

        $query = new \WP_Query($args);
        if ($query->have_posts()) {
            return $query->get_posts();
        }

        return [];
    }

    /**
     * Returns grid of timeframes.
     * @return array
     * @throws \Exception
     */
    public function getGrid()
    {
        $timeFrames = $this->getTimeframes();
        $slots = $this->getTimeframeSlots($timeFrames);

        return $slots;
    }

    /**
     * Returns the slot number for specific timeframe and time.
     *
     * @param \DateTime $time
     * @param $grid
     *
     * @return float|int
     */
    protected function getSlotByTime(\DateTime $time, $grid, $timeframe, $type)
    {
        $hourSlots = $time->format('H') / $grid;
        $minuteSlots = $time->format('i') / 60 / $grid;

        $slot = $hourSlots + $minuteSlots;

        $multidayTimeframeTypes = Timeframe::$multiDayFrames;
        $multidayTimeframe = in_array(get_post_meta($timeframe->ID, 'type', true), $multidayTimeframeTypes);

        if($multidayTimeframe) {
            // Check if Timeframe starts on another day before.
            if (
                $type == 'start' &&
                $time->getTimestamp() < $this->getDateObject()->setTime(0,0)->getTimestamp()) {
                $slot = 0;
            }

            // Check if Timeframe ends on another day after.
            if (
                $type == 'end' &&
                $time->getTimestamp() > $this->getDateObject()->setTime(23,59)->getTimestamp()) {
                $slot = 24 / $grid;
            }
        }

        return $slot;
    }

    /**
     * @param \DateTime $time
     * @param $grid
     * @param $timeframe
     *
     * @return float|int
     */
    protected function getStartSlot(\DateTime $time, $grid, $timeframe) {
        return $this->getSlotByTime($time, $grid, $timeframe, 'start');
    }

    /**
     * @param \DateTime $time
     * @param $grid
     * @param $timeframe
     *
     * @return float|int
     */
    protected function getEndSlot(\DateTime $time, $grid, $timeframe) {
        return $this->getSlotByTime($time, $grid, $timeframe, 'end');
    }

    /**
     * Returns minimal grid from list of timeframes.
     *
     * @param $timeframes
     *
     * @return bool|float
     */
    protected function getMinimalGridFromTimeframes($timeframes)
    {
        $grid = 0.5;
        // Get grid size from existing timeframes
        foreach ($timeframes as $timeframe) {
            if ($timeframeGrid = get_post_meta($timeframe->ID, 'grid', true) < $grid) {
                $grid = $timeframeGrid;
            }
        }

        return $grid;
    }

    /**
     * Fills timeslots with timeframes.
     *
     * @param $slots
     * @param $timeframes
     *
     * @throws \Exception
     */
    protected function mapTimeFrames(&$slots, $timeframes)
    {
        $grid = 24 / count($slots);

        // Iterate through timeframes and fill slots
        foreach ($timeframes as $timeframe) {
            $startDateString = get_post_meta($timeframe->ID, 'start-date', true);
            $endDateString = get_post_meta($timeframe->ID, 'end-date', true);
            $startDate = new \DateTime($startDateString);
            $endDate = new \DateTime($endDateString);

            $startSlot = $this->getStartSlot($startDate, $grid, $timeframe);
            $endSlot = $this->getEndSlot($endDate, $grid, $timeframe);

            // Add timeframe to relevant slots
            while ($startSlot <= $endSlot) {
                $slots[$startSlot++]['timeframes'][] = $timeframe;

                //@TODO: Define main frame for calendar.
//                if(!$slots[$startSlot+]['mainframe']) {
//                    $slots[$startSlot++]['mainframe'] = $timeframe;
//                }
            }
        }
    }

    /**
     * Returns array of timeslots filled with timeframes.
     *
     * @param $timeframes
     *
     * @return array
     * @throws \Exception
     */
    protected function getTimeframeSlots($timeframes)
    {

        $slots = [];
        $grid = $this->getMinimalGridFromTimeframes($timeframes);
        $slotsPerDay = 24 / $grid;

        // Init Slots
        for ($i = 0; $i < $slotsPerDay; $i++) {
            $slots[$i] = [
                'timestart'  => date('H:i', $i * ((24 / $slotsPerDay) * 3600)),
                'timeend'    => date('H:i', ($i + 1) * ((24 / $slotsPerDay) * 3600)),
                'timeframes' => [],
                'mainframe' => null
            ];
        }

        $this->mapTimeFrames($slots, $timeframes);

        return $slots;
    }


}