<?php


namespace CommonsBooking\Model;


use CommonsBooking\Messages\RestrictionMessages;

class Restriction extends CustomPost {

	const TYPE_REPAIR = 'repair';

	const TYPE_HINT = 'hint';

	const META_HINT = 'restriction-hint';

	const META_START = 'restriction-start';

	const META_END = 'restriction-end';

	const META_TYPE = 'restriction-type';

	const META_STATE = 'restriction-state';

	const META_LOCATION_ID = 'restriction-location-id';

	const META_ITEM_ID = 'restriction-item-id';

	const META_SENT = 'restriction-sent';

	protected $active;

	protected $canceled;

	/**
	 * Returns start-time \DateTime.
	 *
	 * @return \DateTime
	 */
	public function getStartTimeDateTime(): \DateTime {
		$startDateString = $this->getMeta( self::META_START );
		$startDate       = new \DateTime();
		$startDate->setTimestamp( $startDateString );
		return $startDate;
	}

	/**
	 * Returns end-date \DateTime.
	 *
	 * @return \DateTime
	 */
	public function getEndDateDateTime(): \DateTime {
		$endDateString = intval( $this->getMeta( self::META_END ) );
		$endDate       = new \DateTime();
		$endDate->setTimestamp( $endDateString );

		return $endDate;
	}

	/**
	 * Returns start-time \DateTime.
	 *
	 * @param null $endDateString
	 *
	 * @return \DateTime
	 */
	public function getEndTimeDateTime( $endDateString = null ): \DateTime {
		$endTimeString = $this->getMeta( self::META_END );
		$endDate       = new \DateTime();

		if ( $endTimeString ) {
			$endTime = new \DateTime();
			$endTime->setTimestamp( $endTimeString );
			$endDate->setTime( $endTime->format( 'H' ), $endTime->format( 'i' ) );
		} else {
			$endDate->setTimestamp( $endDateString );
		}

		return $endDate;
	}

	/**
	 * @return int Timestamp
	 */
	public function getStartDate(): int {
		return intval( $this->getMeta( self::META_START ) );
	}

	/**
	 * @return int Timestamp
	 */
	public function getEndDate(): int {
		return intval( $this->getMeta( self::META_END ) );
	}

	/**
	 * Returns true if restriction isn't active.
	 * @return bool
	 */
	public function isOverBookable(): bool {
		return ! $this->isActive();
	}

	/**
	 * Returns true if restriction ist active.
	 * @return bool
	 */
	public function isLocked(): bool {
		return $this->isActive();
	}

	/**
	 * Returns restriction type.
	 * @return mixed
	 */
	public function getType() {
		return $this->getMeta( self::META_TYPE );
	}

	/**
	 * Returns restriction hint.
	 * @return mixed
	 */
	public function getHint() {
		return $this->getMeta( self::META_HINT );
	}

	/**
	 * Returns nicely formatted start datetime.
	 * @return string
	 */
	public function getFormattedStartDateTime() {
		return $this->getStartTimeDateTime()->format( 'd.m.Y h:i' );
	}

	/**
	 * Returns nicely formatted end datetime.
	 * @return string
	 */
	public function getFormattedEndDateTime() {
		return $this->getEndDateDateTime()->format( 'd.m.Y h:i' );
	}

	/**
	 * @return bool
	 */
	public function isActive(): bool {
		if ( $this->active == null ) {
			$this->active = $this->getMeta( self::META_STATE ) ?: false;
		}

		return $this->active;
	}

	/**
	 * @return bool
	 */
	public function isCancelled(): bool {
		if($this->canceled == null) {
			$this->canceled = $this->getMeta( self::META_STATE ) === '0' ?: false;
		}

		return $this->canceled;
	}

	/**
	 * Returns location id.
	 * @return mixed
	 */
	public function getLocationId() {
		return self::getMeta( self::META_LOCATION_ID );
	}

	/**
	 * Returns itemId
	 * @return mixed
	 */
	public function getItemId() {
		return self::getMeta( self::META_ITEM_ID );
	}

	/**
	 * Returns item name.
	 * @return string
	 */
	public function getItemName(): string {
		$itemName = esc_html__( 'Not set', 'commonsbooking' );
		if ( $this->getItemId() ) {
			$item     = get_post( $this->getItemId() );
			$itemName = $item->post_title;
		}

		return $itemName;
	}

	/**
	 * Returns location name.
	 * @return string
	 */
	public function getLocationName(): string {
		$locationName = esc_html__( 'Not set', 'commonsbooking' );
		if ( $this->getLocationId() ) {
			$location     = get_post( $this->getLocationId() );
			$locationName = $location->post_title;
		}

		return $locationName;
	}

	/**
	 * Send mails regarding item/location admins and booked timeslots.
	 */
	protected function sendRestrictionMails( $bookings ) {
		$userIds = [];

		foreach ( $bookings as $booking ) {
			// User IDs from booking
			$userIds[] = $booking->getUserData()->ID;

			// Admins IDs
			$userIds = array_merge( $userIds, $booking->getAdmins() );
		}

		$userIds = array_unique( $userIds );

		foreach ( $userIds as $userId ) {
			$hintMail = new RestrictionMessages( $this, get_userdata( $userId ), $this->getType() );
			$hintMail->triggerMail();
		}
	}

	/**
	 * Cancels bookings if restriction is active and of type repair.
	 *
	 * @param $bookings
	 */
	protected function cancelBookings( $bookings ) {
		foreach ( $bookings as $booking ) {
			$booking->cancel();
		}
	}

	/**
	 * Apply restriction workflow.
	 */
	public function apply() {
		// Check if this is an active restriction
		if($this->isActive()) {
			$bookings = \CommonsBooking\Repository\Booking::getByRestriction( $this );
			if ( $bookings ) {
				if ( $this->isActive() && $this->getType() == self::TYPE_REPAIR ) {
					$this->cancelBookings($bookings);
				}
				$this->sendRestrictionMails( $bookings );
			}
		}

		// Check if this is a canceled/solved restriction
		if($this->isCancelled()) {
			$canceledBookings = \CommonsBooking\Repository\Booking::getCanceledByRestriction( $this );
			if ( $canceledBookings ) {
				$this->sendRestrictionMails( $canceledBookings );
			}
		}
	}

}