<?php

namespace CommonsBooking\Map;

use CommonsBooking\Helper\Wordpress;
use CommonsBooking\Model\Day;
use CommonsBooking\View\Calendar;
use DateInterval;
use DatePeriod;
use DateTime;
use Exception;

class MapItemAvailable {

	/**
	 * item is available
	 */
	const ITEM_AVAILABLE = 'available';


	/**
	 * location closed because of holiday / official holiday
	 */
	const LOCATION_HOLIDAY = 'location-holiday';

	/**
	 * item is partially booked
	 */
	const ITEM_PARTIALLY_BOOKED = 'partially-booked';

	/**
	 * item is partially locked
	 */
	const ITEM_LOCKED = 'locked';


	/**
	 * item is booked or blocked
	 */
	const ITEM_BOOKED = 'booked';

	/**
	 * no timeframe for item set
	 */
	const OUT_OF_TIMEFRAME = 'no-timeframe';

	/**
	 * @param $locations
	 * @param $date_start
	 * @param $date_end
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public static function create_items_availabilities( $locations, $date_start, $date_end ) {

		$startDay = new Day( $date_start );
		$endDay   = new Day( $date_end );

		$filter_period = new DatePeriod(
			Wordpress::getUTCDateTime( $date_start ),
			new DateInterval( 'P1D' ),
			Wordpress::getUTCDateTime( $date_end . ' +1 day' )
		);

		foreach ( $locations as $location_id => &$location ) {
			foreach ( $location['items'] as &$item ) {

				// Init availability array
				$availability = [];
				foreach ( $filter_period as $date ) {
					$availability[] = [
						"date"   => $date->format( 'Y-m-d' ),
						"status" => self::OUT_OF_TIMEFRAME,
					];
				}

				// get calendardata based on availability range
				$calendarData = Calendar::prepareJsonResponse(
					$startDay,
					$endDay,
					[ $location_id ],
					[ $item['id'] ]
				);

				//mark days in timeframe
				$availability = self::markDaysInTimeframe( $calendarData, $availability );

				$item['availability'] = $availability;
			}
		}

		return $locations;
	}

	/**
	 * @param $calendarData
	 * @param $availabilities
	 *
	 * @return mixed
	 */
	protected static function markDaysInTimeframe( $calendarData, $availabilities ) {
		//mark days which are inside a timeframe
		foreach ( $availabilities as &$availability ) {
			if ( array_key_exists( $availability['date'], $calendarData['days'] ) ) {
				$day = $calendarData['days'][ $availability['date'] ];
				if (! count( $day['slots'])){
					$availability['status'] = self::ITEM_LOCKED;
				}elseif ( $day['holiday']) {
					$availability['status'] = self::LOCATION_HOLIDAY;
				} elseif ($day['locked'] && $day['firstSlotBooked'] && $day['lastSlotBooked']){
					$availability['status'] = self::ITEM_BOOKED;
				} elseif ( $day['locked'] && $day['partiallyBookedDay']) {
					$availability['status'] = self::ITEM_PARTIALLY_BOOKED;
				}  else {
					$availability['status'] = self::ITEM_AVAILABLE;
				}
			}
		}

		return $availabilities;
	}

}
