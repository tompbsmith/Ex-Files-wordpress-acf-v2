<?php
/**
 * Questionnaire form fields.
 *
 * Conditional fields carry data-show-if and are hidden on the server when their
 * condition isn't met; assets/js/questionnaire.js shows and hides them as the client
 * answers. Each repeater item, group and money field is a "scope" (.q-scope), so a
 * condition looks at the answers beside it.
 *
 * @package exfiles
 */

defined( 'ABSPATH' ) || exit;

/**
 * Turn an input name into an id.
 *
 * @param string $name Name.
 */
function exf_q_id( $name ) {
	return 'q-' . trim( preg_replace( '/[^a-z0-9]+/i', '-', $name ), '-' );
}

/**
 * The show-if attributes for a field.
 *
 * @param array $field   Field.
 * @param bool  $visible Visible now.
 */
function exf_q_show_attrs( array $field, $visible ) {
	if ( empty( $field['show_if'] ) ) {
		return '';
	}
	return ' data-show-if="' . esc_attr( wp_json_encode( array( 'key' => $field['show_if'][0], 'values' => $field['show_if'][1] ) ) ) . '"' . ( $visible ? '' : ' hidden' );
}

/**
 * Error message under a field.
 *
 * @param array  $errors Errors.
 * @param string $name   Input name.
 */
function exf_q_error( array $errors, $name ) {
	if ( empty( $errors[ $name ] ) ) {
		return '';
	}
	return '<p class="field-error" id="' . esc_attr( exf_q_id( $name ) ) . '-error">' . esc_html( $errors[ $name ] ) . '</p>';
}

/**
 * Render fields.
 *
 * @param array  $fields Field definitions.
 * @param array  $values Values in this scope.
 * @param string $prefix Input name prefix (e.g. q, q[properties][0]).
 * @param array  $errors Errors keyed by input name.
 * @param array  $all    All answers.
 */
function exf_q_render_fields( array $fields, array $values, $prefix, array $errors, array $all ) {
	foreach ( $fields as $key => $field ) {
		if ( ! empty( $field['show_data'] ) && ! exf_q_field_visible( array( 'show_data' => $field['show_data'] ), array(), $all ) ) {
			continue; // Depends on another section: not shown at all.
		}
		$name    = $prefix . '[' . $key . ']';
		$visible = exf_q_field_visible( array( 'show_if' => $field['show_if'] ?? null ), $values, $all );
		$value   = $values[ $key ] ?? ( 'repeater' === $field['type'] ? array() : '' );
		exf_q_render_field( $key, $field, $value, $name, $errors, $all, $visible );
	}
}

/**
 * Render one field.
 *
 * @param string $key     Field key.
 * @param array  $field   Definition.
 * @param mixed  $value   Value.
 * @param string $name    Input name.
 * @param array  $errors  Errors.
 * @param array  $all     All answers.
 * @param bool   $visible Visible now.
 */
function exf_q_render_field( $key, array $field, $value, $name, array $errors, array $all, $visible ) {
	$id    = exf_q_id( $name );
	$show  = exf_q_show_attrs( $field, $visible );
	$error = exf_q_error( $errors, $name );
	$help  = ! empty( $field['help'] ) ? '<p class="field-note">' . esc_html( $field['help'] ) . '</p>' : '';
	$data  = ' data-key="' . esc_attr( $key ) . '"';
	$aria  = $error ? ' aria-invalid="true" aria-describedby="' . esc_attr( $id ) . '-error"' : '';
	// Questions without 'required' in the schema say so (money answers use "(if any)" instead).
	$opt = empty( $field['required'] ) ? ' <span class="optional">' . esc_html__( '(optional)', 'exfiles' ) . '</span>' : '';

	switch ( $field['type'] ) {
		case 'radio':
			echo '<fieldset class="q-field q-radio' . ( $error ? ' has-error' : '' ) . '"' . $show . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<legend>' . esc_html( $field['label'] ) . $opt . '</legend>' . $help; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<div class="q-pills">';
			foreach ( $field['options'] as $opt ) {
				printf(
					'<label class="q-pill"><input type="radio" name="%1$s" value="%2$s"%3$s%4$s><span>%5$s</span></label>',
					esc_attr( $name ),
					esc_attr( $opt ),
					checked( (string) $value, $opt, false ),
					$data, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					esc_html( $opt )
				);
			}
			echo '</div>' . $error . '</fieldset>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;

		case 'money':
			exf_q_render_money( $key, $field, is_array( $value ) ? $value : array(), $name, $errors, $show );
			return;

		case 'group':
			echo '<div class="q-group q-scope"' . $show . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<h3 class="q-group-title">' . esc_html( $field['label'] ) . '</h3>';
			exf_q_render_fields( $field['fields'], is_array( $value ) ? $value : array(), $name, $errors, $all );
			echo '</div>';
			return;

		case 'repeater':
			exf_q_render_repeater( $key, $field, (array) $value, $name, $errors, $all, $show );
			return;

		case 'outgoings':
			exf_q_render_outgoings( is_array( $value ) ? $value : array(), $name, $errors );
			return;
	}

	if ( 'date' === $field['type'] ) {
		echo '<div role="group" aria-labelledby="' . esc_attr( $id ) . '-legend" class="field field--date q-field' . ( $error ? ' has-error' : '' ) . '"' . $show . $data . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		printf( '<div class="field-legend" id="%s-legend">%s%s</div>', esc_attr( $id ), esc_html( $field['label'] ), $opt ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo exf_date_parts_html( $name, $id, (string) $value, $aria ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $help . $error . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return;
	}

	echo '<div class="field q-field' . ( $error ? ' has-error' : '' ) . '"' . $show . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	printf( '<label for="%s">%s%s</label>', esc_attr( $id ), esc_html( $field['label'] ), $opt ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	if ( 'select' === $field['type'] ) {
		printf( '<select id="%1$s" name="%2$s"%3$s%4$s>', esc_attr( $id ), esc_attr( $name ), $data, $aria ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<option value="">' . esc_html__( 'Choose…', 'exfiles' ) . '</option>';
		foreach ( $field['options'] as $opt ) {
			printf( '<option value="%1$s"%2$s>%1$s</option>', esc_attr( $opt ), selected( (string) $value, $opt, false ) );
		}
		echo '</select>';
	} elseif ( 'textarea' === $field['type'] ) {
		printf( '<textarea id="%1$s" name="%2$s" rows="%3$d"%4$s%5$s>%6$s</textarea>', esc_attr( $id ), esc_attr( $name ), (int) ( $field['rows'] ?? 3 ), $data, $aria, esc_textarea( (string) $value ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	} else {
		$type = 'date' === $field['type'] ? 'date' : ( 'number' === $field['type'] ? 'text' : 'text' );
		$mode = 'number' === $field['type'] ? ' inputmode="decimal"' : '';
		printf( '<input type="%1$s" id="%2$s" name="%3$s" value="%4$s"%5$s%6$s%7$s%8$s>', esc_attr( $type ), esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ), $data, $aria, $mode, 'date' === $type ? ' max="2100-12-31"' : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	echo $help . $error . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * A money field: certainty, then amount, date, basis and frequency.
 *
 * @param string $key    Field key.
 * @param array  $field  Definition.
 * @param array  $v      Value.
 * @param string $name   Input name.
 * @param array  $errors Errors.
 * @param string $show   Show-if attributes.
 */
function exf_q_render_money( $key, array $field, array $v, $name, array $errors, $show ) {
	$c       = $v['certainty'] ?? '';
	$has     = in_array( $c, array( 'Known current', 'Estimated current' ), true ) || ( '' === $c && ! empty( $v['amount'] ) );
	$any_err = false;
	foreach ( $errors as $k => $unused ) {
		if ( 0 === strpos( $k, $name . '[' ) ) {
			$any_err = true;
			break;
		}
	}
	$known  = wp_json_encode( array( 'key' => 'certainty', 'values' => array( 'Known current', 'Estimated current' ) ) );
	$est    = wp_json_encode( array( 'key' => 'certainty', 'values' => array( 'Estimated current' ) ) );
	$opt    = ! empty( $field['optional'] ) ? ' <span class="optional">' . esc_html__( '(if any)', 'exfiles' ) . '</span>' : '';
	$amount = (string) ( $v['amount'] ?? '' );
	?>
	<fieldset class="q-money q-scope<?php echo $any_err ? ' has-error' : ''; ?><?php echo isset( $errors[ $name . '[certainty]' ] ) ? ' has-cert-error' : ''; ?>" data-key="<?php echo esc_attr( $key ); ?>"<?php echo $show; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<legend><?php echo esc_html( $field['label'] ) . $opt; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></legend>
		<div class="q-pills q-pills--certainty">
			<?php foreach ( exf_q_certainty_options( $field ) as $o ) : ?>
				<label class="q-pill"><input type="radio" name="<?php echo esc_attr( $name . '[certainty]' ); ?>" value="<?php echo esc_attr( $o ); ?>" data-key="certainty" <?php checked( $c, $o ); ?>><span><?php echo esc_html( $o ); ?></span></label>
			<?php endforeach; ?>
		</div>
		<?php echo exf_q_error( $errors, $name . '[certainty]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<div class="q-money-row" data-show-if="<?php echo esc_attr( $known ); ?>"<?php echo $has ? '' : ' hidden'; ?>>
			<div class="field q-amount<?php echo isset( $errors[ $name . '[amount]' ] ) ? ' has-error' : ''; ?>">
				<label for="<?php echo esc_attr( exf_q_id( $name . '[amount]' ) ); ?>"><?php esc_html_e( 'Amount (£)', 'exfiles' ); ?></label>
				<div class="q-pound"><span aria-hidden="true">£</span><input type="text" inputmode="decimal" id="<?php echo esc_attr( exf_q_id( $name . '[amount]' ) ); ?>" name="<?php echo esc_attr( $name . '[amount]' ); ?>" value="<?php echo esc_attr( $amount ); ?>" data-key="amount"></div>
				<?php echo exf_q_error( $errors, $name . '[amount]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<?php if ( ! empty( $field['freq'] ) ) : ?>
				<div class="field<?php echo isset( $errors[ $name . '[frequency]' ] ) ? ' has-error' : ''; ?>">
					<label for="<?php echo esc_attr( exf_q_id( $name . '[frequency]' ) ); ?>"><?php esc_html_e( 'How often', 'exfiles' ); ?></label>
					<select id="<?php echo esc_attr( exf_q_id( $name . '[frequency]' ) ); ?>" name="<?php echo esc_attr( $name . '[frequency]' ); ?>" data-key="frequency">
						<option value=""><?php esc_html_e( 'Choose…', 'exfiles' ); ?></option>
						<?php foreach ( exf_q_freq_options( $field ) as $f ) : ?>
							<option value="<?php echo esc_attr( $f ); ?>" <?php selected( $v['frequency'] ?? '', $f ); ?>><?php echo esc_html( $f ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php echo exf_q_error( $errors, $name . '[frequency]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			<?php endif; ?>
			<div role="group" aria-labelledby="<?php echo esc_attr( exf_q_id( $name . '[as_at]' ) ); ?>-legend" class="field field--date<?php echo isset( $errors[ $name . '[as_at]' ] ) ? ' has-error' : ''; ?>">
				<div class="field-legend" id="<?php echo esc_attr( exf_q_id( $name . '[as_at]' ) ); ?>-legend"><?php esc_html_e( 'As at (date)', 'exfiles' ); ?></div>
				<?php echo exf_date_parts_html( $name . '[as_at]', exf_q_id( $name . '[as_at]' ), (string) ( $v['as_at'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo exf_q_error( $errors, $name . '[as_at]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
		<?php if ( ! empty( $field['freq'] ) ) : ?>
			<p class="q-monthly field-note" data-monthly aria-live="polite"></p>
		<?php endif; ?>
		<div class="field q-basis<?php echo isset( $errors[ $name . '[basis]' ] ) ? ' has-error' : ''; ?>" data-show-if="<?php echo esc_attr( $est ); ?>"<?php echo 'Estimated current' === $c ? '' : ' hidden'; ?>>
			<label for="<?php echo esc_attr( exf_q_id( $name . '[basis]' ) ); ?>"><?php esc_html_e( 'How did you estimate it?', 'exfiles' ); ?></label>
			<input type="text" id="<?php echo esc_attr( exf_q_id( $name . '[basis]' ) ); ?>" name="<?php echo esc_attr( $name . '[basis]' ); ?>" value="<?php echo esc_attr( (string) ( $v['basis'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'For example: an online valuation, an old statement', 'exfiles' ); ?>">
			<?php echo exf_q_error( $errors, $name . '[basis]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	</fieldset>
	<?php
}

/**
 * A repeater: one block per item, with Add another and Remove.
 *
 * @param string $key    Field key.
 * @param array  $field  Definition.
 * @param array  $items  Items.
 * @param string $name   Input name.
 * @param array  $errors Errors.
 * @param array  $all    All answers.
 * @param string $show   Show-if attributes.
 */
function exf_q_render_repeater( $key, array $field, array $items, $name, array $errors, array $all, $show ) {
	$items = array_filter( $items, 'is_array' );
	if ( ! $items ) {
		$items = array( array() ); // Start with one empty block.
	}
	$item_label = ucfirst( $field['item'] );
	echo '<div class="q-repeater" data-repeater="' . esc_attr( $key ) . '" data-item-label="' . esc_attr( $item_label ) . '"' . $show . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '<div class="q-items">';
	foreach ( array_values( array_filter( $items, 'is_array' ) ) as $i => $item ) {
		exf_q_render_item( $field, $item, $name . '[' . $i . ']', $errors, $all, $item_label . ' ' . ( $i + 1 ) );
	}
	echo '</div>';
	echo '<template>';
	exf_q_render_item( $field, array(), $name . '[__i__]', array(), $all, $item_label );
	echo '</template>';
	/* translators: %s: item, e.g. property */
	echo '<button type="button" class="btn small outline q-add">' . esc_html( sprintf( __( '+ Add another %s', 'exfiles' ), $field['item'] ) ) . '</button>';
	echo '</div>';
}

/**
 * One repeater item.
 *
 * @param array  $field  Repeater definition.
 * @param array  $item   Values.
 * @param string $name   Input name prefix.
 * @param array  $errors Errors.
 * @param array  $all    All answers.
 * @param string $title  Heading.
 */
function exf_q_render_item( array $field, array $item, $name, array $errors, array $all, $title ) {
	echo '<div class="q-item q-scope">';
	echo '<div class="q-item-head"><h3 class="q-item-title">' . esc_html( $title ) . '</h3>';
	/* translators: %s: item, e.g. property */
	echo '<button type="button" class="link-button q-remove">' . esc_html( sprintf( __( 'Remove this %s', 'exfiles' ), $field['item'] ) ) . '</button></div>';
	exf_q_render_fields( $field['fields'], $item, $name, $errors, $all );
	echo '</div>';
}

/**
 * The outgoings table.
 *
 * @param array  $rows   Rows.
 * @param string $name   Input name.
 * @param array  $errors Errors.
 */
function exf_q_render_outgoings( array $rows, $name, array $errors ) {
	$freqs = array( 'Weekly', 'Fortnightly', 'Four-weekly', 'Monthly', 'Quarterly', 'Annual' );
	?>
	<div class="q-outgoings" data-outgoings>
		<div class="q-out-head" aria-hidden="true">
			<span><?php esc_html_e( 'What for', 'exfiles' ); ?></span><span><?php esc_html_e( 'Now (£)', 'exfiles' ); ?></span><span><?php esc_html_e( 'After separating (£)', 'exfiles' ); ?></span><span><?php esc_html_e( 'How often', 'exfiles' ); ?></span><span><?php esc_html_e( 'Is it…', 'exfiles' ); ?></span>
		</div>
		<?php foreach ( exf_q_outgoing_categories() as $i => $cat ) : ?>
			<?php
			$r = $rows[ $i ] ?? array();
			$n = $name . '[' . $i . ']';
			?>
			<div class="q-out-row" role="group" aria-label="<?php echo esc_attr( $cat ); ?>">
				<span class="q-out-cat"><?php echo esc_html( $cat ); ?></span>
				<?php foreach ( array( 'current' => __( 'Now (£)', 'exfiles' ), 'future' => __( 'After separating (£)', 'exfiles' ) ) as $col => $lab ) : ?>
					<label class="q-out-cell<?php echo isset( $errors[ $n . '[' . $col . ']' ] ) ? ' has-error' : ''; ?>">
						<span class="q-out-label"><?php echo esc_html( $lab ); ?></span>
						<input type="text" inputmode="decimal" name="<?php echo esc_attr( $n . '[' . $col . ']' ); ?>" value="<?php echo esc_attr( (string) ( $r[ $col ] ?? '' ) ); ?>" data-col="<?php echo esc_attr( $col ); ?>" aria-label="<?php echo esc_attr( $cat . ': ' . $lab ); ?>">
					</label>
				<?php endforeach; ?>
				<label class="q-out-cell">
					<span class="q-out-label"><?php esc_html_e( 'How often', 'exfiles' ); ?></span>
					<select name="<?php echo esc_attr( $n . '[frequency]' ); ?>" data-col="frequency" aria-label="<?php echo esc_attr( $cat . ': ' . __( 'How often', 'exfiles' ) ); ?>">
						<?php foreach ( $freqs as $f ) : ?>
							<option value="<?php echo esc_attr( $f ); ?>" <?php selected( $r['frequency'] ?? 'Monthly', $f ); ?>><?php echo esc_html( $f ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="q-out-cell">
					<span class="q-out-label"><?php esc_html_e( 'Is it…', 'exfiles' ); ?></span>
					<select name="<?php echo esc_attr( $n . '[certainty]' ); ?>" aria-label="<?php echo esc_attr( $cat . ': ' . __( 'Known or estimated', 'exfiles' ) ); ?>">
						<?php foreach ( array( 'Known' => __( 'Known', 'exfiles' ), 'Estimated' => __( 'Estimated', 'exfiles' ), "Don't know" => __( "Don't know", 'exfiles' ) ) as $k => $l ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $r['certainty'] ?? 'Known', $k ); ?>><?php echo esc_html( $l ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<?php echo exf_q_error( $errors, $n . '[current]' ) . exf_q_error( $errors, $n . '[future]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		<?php endforeach; ?>
		<div class="q-out-total" aria-live="polite">
			<span><?php esc_html_e( 'Total per month', 'exfiles' ); ?></span>
			<span><?php esc_html_e( 'Now:', 'exfiles' ); ?> <strong data-total="current">£0</strong></span>
			<span><?php esc_html_e( 'After:', 'exfiles' ); ?> <strong data-total="future">£0</strong></span>
		</div>
	</div>
	<?php
}

/**
 * A section's form body: the gate (if any), then the fields.
 *
 * @param string $key    Section key.
 * @param array  $data   All answers.
 * @param array  $errors Errors.
 */
function exf_q_render_section( $key, array $data, array $errors ) {
	$section = exf_q_sections()[ $key ];
	$values  = $data[ $key ] ?? array();
	echo '<div class="q-scope q-section">';
	if ( ! empty( $section['gate'] ) ) {
		$gate = $values['_gate'] ?? '';
		exf_q_render_field( '_gate', array( 'type' => 'radio', 'label' => $section['gate'], 'options' => EXF_Q_YND ), $gate, 'q[_gate]', $errors, $data, true );
		exf_q_render_field(
			'_gate_note',
			array(
				'type'    => 'textarea',
				'label'   => __( "Tell us what you do know. We can explain how to find out the rest.", 'exfiles' ),
				'show_if' => array( '_gate', array( "Don't know" ) ),
			),
			$values['_gate_note'] ?? '',
			'q[_gate_note]',
			$errors,
			$data,
			"Don't know" === $gate
		);
		echo '<div class="q-gated" data-show-if="' . esc_attr( wp_json_encode( array( 'key' => '_gate', 'values' => array( 'Yes' ) ) ) ) . '"' . ( 'Yes' === $gate ? '' : ' hidden' ) . '>';
		if ( ! empty( $section['money_note'] ) ) {
			echo '<p class="q-note">' . esc_html( $section['money_note'] ) . '</p>';
		}
		exf_q_render_fields( $section['fields'], $values, 'q', $errors, $data );
		echo '</div>';
	} else {
		if ( ! empty( $section['money_note'] ) ) {
			echo '<p class="q-note">' . esc_html( $section['money_note'] ) . '</p>';
		}
		exf_q_render_fields( $section['fields'], $values, 'q', $errors, $data );
	}
	echo '</div>';
}
