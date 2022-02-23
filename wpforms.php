<?php

// Filters the email confirmation message.
function wpforms_filter_email_message( $message, $emailsInstance ) {
	$data = [];
	foreach ( $emailsInstance->fields as $field ) {
		$fieldName          = strtolower( $field['name'] );
		$data[ $fieldName ] = sanitize_text_field( $field['value'] );
	}

	$personCount = 0;
	foreach ( $data as $k => $v ) {
		if ( preg_match( '/naam|nom/i', $k ) && $v ) {
			$personCount++;
		}
	}

	$parkingTicket = (bool) $data['parkeerticket']
		? 'U heeft ook een parkingticket gereserveerd.'
		: '';

	$message = preg_replace( '/\[NUMBER\]/i', $personCount, $message );
	$message = preg_replace( '/\[PARKING\]/i', $parkingTicket, $message );

	return $message;
}

add_filter( 'wpforms_email_message', 'wpforms_filter_email_message', 10, 2 );

// Filters form fields before rendering.
function wpforms_filter_field_properties( $properties, $field ) {
	if ( ! isset( $field['label'] ) || ! preg_match( '/maaltijd/i', $field['label'] ) ) {
		return $properties;
	}


	foreach ( $properties['inputs'] as $optionIndex => $option ) {
		$mealName = get_meal_name( $option['label']['text'] );
		if ( ! $mealName ) {
			continue;
		}

		$count = (int) get_option( "form_meal_$mealName", 0 );
		if ( $count > 2 ) {
			$properties['inputs'][ $optionIndex ]['label']['text'] .= ' (reeds volzet/pas de places restantes)';
			$properties['inputs'][ $optionIndex ]['class'][] = 'wpf-disable-field';
		}
	}

	return $properties;
}

add_filter( 'wpforms_field_properties', 'wpforms_filter_field_properties', 10, 2 );

// Runs after form has been submitted with success.
function wpforms_do_process_complete( $fields ) {
	$mealName    = false;
	$personCount = 0;
	foreach ( $fields as $field ) {
		if (
			'radio' === $field['type'] &&
			preg_match( '/maaltijd/i', $field['name'] )
		) {
			$mealName = get_meal_name( $field['value'] );
		}

		if ( preg_match( '/naam|nom/i', $field['name'] ) && $field['value'] ) {
			$personCount++;
		}
	}

	if ( ! $mealName ) {
		return;
	}

	$value = (int) get_option( "form_meal_$mealName", 0 );
	update_option( "form_meal_$mealName", $value + $personCount );
}

add_action( 'wpforms_process_complete', 'wpforms_do_process_complete', 10, 4 );

// Outputs additional scripts in the footer.
function wpforms_footer_script() {
	?>
	<script type="text/javascript">

	// Disable a specific option.
	// Class needs to be added manually.
	jQuery(function($) {
		$( 'input.wpf-disable-field' ).attr({
			disabled: 'disabled'
		});
	});

	</script>

	// Restrict number field to a min/max value.
	// Class needs to be manually added.
	<script type="text/javascript">
		jQuery(function($){
			$('.wpf-num-limit input').attr({'min':1, 'max':110});
		});
	</script>
	<?php
}

add_action( 'wpforms_wp_footer_end', 'wpforms_footer_script', 30 );