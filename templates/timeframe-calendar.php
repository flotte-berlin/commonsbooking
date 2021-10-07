<?php
    global $templateData;

    // we check if template is used not used in backend ... 
    if(!array_key_exists('backend', $templateData) || $templateData['backend'] != true) {
?>
    <script type="text/javascript">
		<?php
		echo "let data = " . $templateData['calendar_data'] . ';';
		?>
    </script>
    <div id="litepicker"></div>
    <div id="booking-form-container">
        <form method="get" id="booking-form">
			<?php echo $templateData['wp_nonce']; ?>
            <input type="hidden" name="location-id" value="<?php echo $templateData['location']->ID; ?>"/>
            <input type="hidden" name="item-id" value="<?php echo $templateData['item']->ID; ?>"/>
            <input type="hidden" name="type" value="<?php echo $templateData['type']; ?>"/>
            <input type="hidden" name="post_type" value="cb_booking"/>
            <input type="hidden" name="post_status" value="unconfirmed"/>

            <div class="time-selection-container">
                <div class="time-selection repetition-start">
                    <label for="repetition-start">
						<?php echo esc_html__( 'Pickup', 'commonsbooking' ); ?>:
                    </label>
                    <span class="hint-selection"><?php echo esc_html__( 'Please select the pickup date in the calendar', 'commonsbooking' ); ?></span>
                    <span class="date"></span>
                    <select style="display: none" id="repetition-start" name="repetition-start"></select>
                </div>
                <div class="time-selection repetition-end">
                    <label for="repetition-end">
						<?php echo esc_html__( 'Return', 'commonsbooking' ); ?>:
                    </label>
                    <span class="hint-selection"><?php echo esc_html__( 'Please select the return date in the calendar', 'commonsbooking' ); ?></span>
                    <span class="date"></span>
                    <select style="display: none" id="repetition-end" name="repetition-end"></select>
                </div>
				<?php
				if (
                    array_key_exists( 'location', $templateData ) &&
				     $templateData['location']->getRestrictions() &&
                     count( $templateData['location']->getRestrictions())
				) { ?>
                    <div>
                        <label><?php echo '⚠ ' . esc_html__( 'Restrictions', 'commonsbooking' ); ?>:</label>
                        <span class="restrictions">
						<?php
                            foreach ( $templateData['location']->getRestrictions() as $restriction ) {
                                if($restriction->isActive()) {
	                                echo $restriction->getHint() . '<br>';
                                }
                            }
						?>
                        </span>
                    </div>
                <?php } ?>
            <p id="fullDayInfo"></p>
            <p id="calendarNotice"></p>
            </div>
			<?php
			if ( is_user_logged_in() ) { ?>
                <input type="submit" disabled="disabled"
                       value="<?php echo esc_html__( 'Continue to booking confirmation', 'commonsbooking' ); ?>"/>
			<?php } ?>
        </form>
    </div>
<?php 

    // if template is used in backend 
    } else {
        foreach ($templateData['calendar']['weeks'] as $week) {
?>
        <ul class="cb-calendar">
			<?php
			$dayNrs = [ 1, 2, 3, 4, 5, 6, 0 ];
			foreach ( $dayNrs as $dayNr ) {
				/** @var \CommonsBooking\Model\Day $day */
				foreach ( $templateData['week']->getDays() as $day ) {
					if ( $day->getDayOfWeek() == $dayNr ) {
						include __DIR__ . 'timeframe-calendar-day.php';
					}
				}
			}
			?>
        </ul>
		<?php
	}
}
