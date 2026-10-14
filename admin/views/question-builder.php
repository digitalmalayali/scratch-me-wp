<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * "Questions" section of the campaign editor.
 *
 * @var array $questions Sanitized questions for this campaign.
 */

/**
 * One question card. Fields post as questions[<key>][field]; the order of the
 * cards on the page is the order visitors see.
 *
 * @param array      $q   Question (or defaults for the JS template).
 * @param int|string $key Card key; '__QKEY__' in the template.
 */
$sw_question_card = function( array $q, $key ) {
	$q = wp_parse_args(
		$q,
		array(
			'id' => '', 'label' => '', 'type' => 'text', 'required' => 1, 'placeholder' => '', 'help' => '',
			'options' => array(), 'min_length' => '', 'max_length' => '', 'pattern' => 'any',
			'min' => '', 'max' => '', 'integer' => 0, 'min_select' => '', 'max_select' => '',
			'min_date' => '', 'max_date' => '',
		)
	);
	$n = function( $field ) use ( $key ) {
		return 'questions[' . $key . '][' . $field . ']';
	};
	$uid = 'swq-' . $key;
	?>
	<div class="sw-question-card" data-type="<?php echo esc_attr( $q['type'] ); ?>">
		<input type="hidden" name="<?php echo esc_attr( $n( 'id' ) ); ?>" value="<?php echo esc_attr( $q['id'] ); ?>">

		<div class="sw-qc-head">
			<span class="sw-qc-number"></span>
			<input type="text" class="sw-qc-label" name="<?php echo esc_attr( $n( 'label' ) ); ?>" value="<?php echo esc_attr( $q['label'] ); ?>"
				placeholder="<?php esc_attr_e( 'Question, e.g. Which city do you live in?', 'scratch-win' ); ?>" maxlength="200" aria-label="<?php esc_attr_e( 'Question', 'scratch-win' ); ?>">
			<select class="sw-qc-type" name="<?php echo esc_attr( $n( 'type' ) ); ?>" aria-label="<?php esc_attr_e( 'Answer type', 'scratch-win' ); ?>">
				<?php foreach ( SW_Questions::types() as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $q['type'], $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<label class="sw-qc-required"><input type="checkbox" name="<?php echo esc_attr( $n( 'required' ) ); ?>" value="1" <?php checked( (int) $q['required'], 1 ); ?>> <?php esc_html_e( 'Required', 'scratch-win' ); ?></label>
			<span class="sw-qc-actions">
				<button type="button" class="button button-small sw-qc-up" aria-label="<?php esc_attr_e( 'Move up', 'scratch-win' ); ?>">↑</button>
				<button type="button" class="button button-small sw-qc-down" aria-label="<?php esc_attr_e( 'Move down', 'scratch-win' ); ?>">↓</button>
				<button type="button" class="button button-small sw-qc-remove"><?php esc_html_e( 'Delete', 'scratch-win' ); ?></button>
			</span>
		</div>

		<div class="sw-qc-body">
			<p class="sw-qc-field" data-show-for="select radio checkbox">
				<label for="<?php echo esc_attr( $uid ); ?>-options"><?php esc_html_e( 'Options (one per line)', 'scratch-win' ); ?></label>
				<textarea id="<?php echo esc_attr( $uid ); ?>-options" class="sw-qc-options" name="<?php echo esc_attr( $n( 'options' ) ); ?>" rows="4" placeholder="<?php esc_attr_e( "Chennai\nBengaluru\nMumbai", 'scratch-win' ); ?>"><?php echo esc_textarea( implode( "\n", (array) $q['options'] ) ); ?></textarea>
			</p>

			<p class="sw-qc-field" data-show-for="text textarea number email select">
				<label for="<?php echo esc_attr( $uid ); ?>-ph"><span data-show-for="text textarea number email"><?php esc_html_e( 'Placeholder', 'scratch-win' ); ?></span><span data-show-for="select"><?php esc_html_e( 'First line of the dropdown', 'scratch-win' ); ?></span></label>
				<input type="text" id="<?php echo esc_attr( $uid ); ?>-ph" name="<?php echo esc_attr( $n( 'placeholder' ) ); ?>" value="<?php echo esc_attr( $q['placeholder'] ); ?>" maxlength="150" placeholder="<?php esc_attr_e( 'Optional', 'scratch-win' ); ?>">
			</p>

			<p class="sw-qc-field">
				<label for="<?php echo esc_attr( $uid ); ?>-help"><?php esc_html_e( 'Help text (shown under the field)', 'scratch-win' ); ?></label>
				<input type="text" id="<?php echo esc_attr( $uid ); ?>-help" name="<?php echo esc_attr( $n( 'help' ) ); ?>" value="<?php echo esc_attr( $q['help'] ); ?>" maxlength="300" placeholder="<?php esc_attr_e( 'Optional', 'scratch-win' ); ?>">
			</p>

			<div class="sw-qc-row" data-show-for="text textarea">
				<p class="sw-qc-field sw-qc-small">
					<label for="<?php echo esc_attr( $uid ); ?>-minl"><?php esc_html_e( 'Min characters', 'scratch-win' ); ?></label>
					<input type="number" id="<?php echo esc_attr( $uid ); ?>-minl" name="<?php echo esc_attr( $n( 'min_length' ) ); ?>" min="0" step="1" value="<?php echo esc_attr( $q['min_length'] ); ?>" placeholder="0">
				</p>
				<p class="sw-qc-field sw-qc-small">
					<label for="<?php echo esc_attr( $uid ); ?>-maxl"><?php esc_html_e( 'Max characters', 'scratch-win' ); ?></label>
					<input type="number" id="<?php echo esc_attr( $uid ); ?>-maxl" name="<?php echo esc_attr( $n( 'max_length' ) ); ?>" min="1" step="1" value="<?php echo esc_attr( $q['max_length'] ); ?>" placeholder="<?php echo esc_attr( 'textarea' === $q['type'] ? SW_Questions::TEXTAREA_HARD_LIMIT : SW_Questions::TEXT_HARD_LIMIT ); ?>">
				</p>
				<p class="sw-qc-field">
					<label for="<?php echo esc_attr( $uid ); ?>-pattern"><?php esc_html_e( 'Allowed characters', 'scratch-win' ); ?></label>
					<select id="<?php echo esc_attr( $uid ); ?>-pattern" name="<?php echo esc_attr( $n( 'pattern' ) ); ?>">
						<?php foreach ( SW_Questions::pattern_labels() as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $q['pattern'], $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
			</div>

			<div class="sw-qc-row" data-show-for="number">
				<p class="sw-qc-field sw-qc-small">
					<label for="<?php echo esc_attr( $uid ); ?>-min"><?php esc_html_e( 'Minimum value', 'scratch-win' ); ?></label>
					<input type="number" id="<?php echo esc_attr( $uid ); ?>-min" name="<?php echo esc_attr( $n( 'min' ) ); ?>" step="any" value="<?php echo esc_attr( $q['min'] ); ?>" placeholder="<?php esc_attr_e( 'None', 'scratch-win' ); ?>">
				</p>
				<p class="sw-qc-field sw-qc-small">
					<label for="<?php echo esc_attr( $uid ); ?>-max"><?php esc_html_e( 'Maximum value', 'scratch-win' ); ?></label>
					<input type="number" id="<?php echo esc_attr( $uid ); ?>-max" name="<?php echo esc_attr( $n( 'max' ) ); ?>" step="any" value="<?php echo esc_attr( $q['max'] ); ?>" placeholder="<?php esc_attr_e( 'None', 'scratch-win' ); ?>">
				</p>
				<p class="sw-qc-field sw-qc-check">
					<label><input type="checkbox" name="<?php echo esc_attr( $n( 'integer' ) ); ?>" value="1" <?php checked( (int) $q['integer'], 1 ); ?>> <?php esc_html_e( 'Whole numbers only', 'scratch-win' ); ?></label>
				</p>
			</div>

			<div class="sw-qc-row" data-show-for="checkbox">
				<p class="sw-qc-field sw-qc-small">
					<label for="<?php echo esc_attr( $uid ); ?>-mins"><?php esc_html_e( 'Min selections', 'scratch-win' ); ?></label>
					<input type="number" id="<?php echo esc_attr( $uid ); ?>-mins" name="<?php echo esc_attr( $n( 'min_select' ) ); ?>" min="0" step="1" value="<?php echo esc_attr( $q['min_select'] ); ?>" placeholder="<?php esc_attr_e( 'None', 'scratch-win' ); ?>">
				</p>
				<p class="sw-qc-field sw-qc-small">
					<label for="<?php echo esc_attr( $uid ); ?>-maxs"><?php esc_html_e( 'Max selections', 'scratch-win' ); ?></label>
					<input type="number" id="<?php echo esc_attr( $uid ); ?>-maxs" name="<?php echo esc_attr( $n( 'max_select' ) ); ?>" min="1" step="1" value="<?php echo esc_attr( $q['max_select'] ); ?>" placeholder="<?php esc_attr_e( 'None', 'scratch-win' ); ?>">
				</p>
			</div>

			<div class="sw-qc-row" data-show-for="date">
				<p class="sw-qc-field sw-qc-small">
					<label for="<?php echo esc_attr( $uid ); ?>-mind"><?php esc_html_e( 'Earliest date', 'scratch-win' ); ?></label>
					<input type="date" id="<?php echo esc_attr( $uid ); ?>-mind" name="<?php echo esc_attr( $n( 'min_date' ) ); ?>" value="<?php echo esc_attr( $q['min_date'] ); ?>">
				</p>
				<p class="sw-qc-field sw-qc-small">
					<label for="<?php echo esc_attr( $uid ); ?>-maxd"><?php esc_html_e( 'Latest date', 'scratch-win' ); ?></label>
					<input type="date" id="<?php echo esc_attr( $uid ); ?>-maxd" name="<?php echo esc_attr( $n( 'max_date' ) ); ?>" value="<?php echo esc_attr( $q['max_date'] ); ?>">
				</p>
			</div>
		</div>
	</div>
	<?php
};

$sw_q_warnings = SW_Questions::warnings( $questions );
?>
<h2 class="title"><?php esc_html_e( 'Questions', 'scratch-win' ); ?></h2>
<p class="description sw-q-intro">
	<?php esc_html_e( 'Ask visitors questions together with their mobile number, before the card can be scratched (or before the prize is claimed, if the phone number is asked after scratching). Answers appear on the Leads page, in the CSV export and in the email alert. Leave this empty to ask only for the mobile number.', 'scratch-win' ); ?>
</p>

<?php if ( $sw_q_warnings ) : ?>
	<div class="notice notice-warning inline">
		<?php foreach ( $sw_q_warnings as $w ) : ?>
			<p><?php echo esc_html( $w ); ?></p>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<div id="sw-questions-list" data-max="<?php echo (int) SW_Questions::MAX_QUESTIONS; ?>">
	<?php
	foreach ( array_values( $questions ) as $i => $q ) {
		$sw_question_card( $q, $i );
	}
	?>
</div>
<p class="sw-q-empty" <?php echo $questions ? 'style="display:none;"' : ''; ?>><?php esc_html_e( 'No questions yet.', 'scratch-win' ); ?></p>
<p>
	<button type="button" class="button" id="sw-add-question"><?php esc_html_e( '+ Add Question', 'scratch-win' ); ?></button>
	<span class="description sw-q-limit" style="display:none;">
		<?php
		/* translators: %d: maximum number of questions */
		printf( esc_html__( 'You can add up to %d questions.', 'scratch-win' ), (int) SW_Questions::MAX_QUESTIONS );
		?>
	</span>
</p>

<template id="sw-question-template">
	<?php $sw_question_card( array(), '__QKEY__' ); ?>
</template>
