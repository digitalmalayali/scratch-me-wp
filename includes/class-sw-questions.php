<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Custom questions asked before the scratch card unlocks.
 *
 * A campaign stores its questions as JSON (sw_campaigns.questions). Each lead
 * stores a snapshot of the answers it gave (sw_leads.answers) as a list of
 * { id, label, value } so answers stay readable even if a question is later
 * renamed or deleted.
 *
 * All rules are enforced twice: in the browser for instant feedback, and here
 * on the server, which is the one that counts.
 */
class SW_Questions {

	const MAX_QUESTIONS      = 20;
	const MAX_OPTIONS        = 50;
	const MAX_OPTION_LENGTH  = 200;
	const TEXT_HARD_LIMIT    = 1000;
	const TEXTAREA_HARD_LIMIT = 5000;

	/** Allowed-character presets for text questions. */
	const PATTERNS = array( 'any', 'letters', 'digits', 'alnum' );

	public static function types() {
		return array(
			'text'     => __( 'Short text', 'scratch-win' ),
			'textarea' => __( 'Paragraph text', 'scratch-win' ),
			'select'   => __( 'Dropdown', 'scratch-win' ),
			'radio'    => __( 'Radio buttons (pick one)', 'scratch-win' ),
			'checkbox' => __( 'Checkboxes (pick several)', 'scratch-win' ),
			'number'   => __( 'Number', 'scratch-win' ),
			'email'    => __( 'Email address', 'scratch-win' ),
			'date'     => __( 'Date', 'scratch-win' ),
		);
	}

	public static function pattern_labels() {
		return array(
			'any'     => __( 'Anything', 'scratch-win' ),
			'letters' => __( 'Letters and spaces only (e.g. a name)', 'scratch-win' ),
			'digits'  => __( 'Digits only (e.g. a PIN code)', 'scratch-win' ),
			'alnum'   => __( 'Letters, digits and spaces only', 'scratch-win' ),
		);
	}

	public static function is_choice_type( $type ) {
		return in_array( $type, array( 'select', 'radio', 'checkbox' ), true );
	}

	public static function is_text_type( $type ) {
		return in_array( $type, array( 'text', 'textarea' ), true );
	}

	// -----------------------------------------------------------------
	// Sanitizing the admin's question list
	// -----------------------------------------------------------------

	/**
	 * Clean a list of questions coming from the campaign editor (or from the
	 * database). Questions without a label are dropped. Every value is
	 * clamped to a safe range, so the result can always be rendered and
	 * validated without further checks.
	 */
	public static function sanitize_questions( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$types = array_keys( self::types() );
		$clean = array();
		$seen  = array();

		foreach ( $raw as $q ) {
			if ( ! is_array( $q ) ) {
				continue;
			}
			$label = self::clip( self::clean_text( $q['label'] ?? '' ), 200 );
			if ( '' === $label ) {
				continue;
			}

			$type = (string) ( $q['type'] ?? 'text' );
			if ( ! in_array( $type, $types, true ) ) {
				$type = 'text';
			}

			// Stable id: keep the one we were given if it is valid and unique.
			$id = strtolower( (string) ( $q['id'] ?? '' ) );
			if ( ! preg_match( '/^[a-z0-9_]{1,40}$/', $id ) || isset( $seen[ $id ] ) ) {
				do {
					$id = 'q_' . strtolower( wp_generate_password( 8, false, false ) );
				} while ( isset( $seen[ $id ] ) );
			}
			$seen[ $id ] = true;

			$item = array(
				'id'          => $id,
				'label'       => $label,
				'type'        => $type,
				'required'    => ! empty( $q['required'] ) ? 1 : 0,
				'placeholder' => self::clip( self::clean_text( $q['placeholder'] ?? '' ), 150 ),
				'help'        => self::clip( self::clean_text( $q['help'] ?? '' ), 300 ),
				'options'     => array(),
				'min_length'  => '',
				'max_length'  => '',
				'pattern'     => 'any',
				'min'         => '',
				'max'         => '',
				'integer'     => 0,
				'min_select'  => '',
				'max_select'  => '',
				'min_date'    => '',
				'max_date'    => '',
			);

			if ( self::is_choice_type( $type ) ) {
				$item['options'] = self::sanitize_options( $q['options'] ?? array() );
			}

			if ( self::is_text_type( $type ) ) {
				$hard = 'textarea' === $type ? self::TEXTAREA_HARD_LIMIT : self::TEXT_HARD_LIMIT;
				list( $item['min_length'], $item['max_length'] ) = self::int_range( $q['min_length'] ?? '', $q['max_length'] ?? '', 0, $hard );
				// Text fields always get an upper limit, so nobody can paste a novel.
				if ( '' === $item['max_length'] ) {
					$item['max_length'] = $hard;
				}
				$pattern         = (string) ( $q['pattern'] ?? 'any' );
				$item['pattern'] = in_array( $pattern, self::PATTERNS, true ) ? $pattern : 'any';
			}

			if ( 'number' === $type ) {
				$item['integer']                    = ! empty( $q['integer'] ) ? 1 : 0;
				list( $item['min'], $item['max'] ) = self::num_range( $q['min'] ?? '', $q['max'] ?? '' );
			}

			if ( 'checkbox' === $type ) {
				$count = max( 1, count( $item['options'] ) );
				list( $item['min_select'], $item['max_select'] ) = self::int_range( $q['min_select'] ?? '', $q['max_select'] ?? '', 0, $count );
			}

			if ( 'date' === $type ) {
				$min = self::valid_date( $q['min_date'] ?? '' ) ? (string) $q['min_date'] : '';
				$max = self::valid_date( $q['max_date'] ?? '' ) ? (string) $q['max_date'] : '';
				if ( $min && $max && $min > $max ) {
					list( $min, $max ) = array( $max, $min );
				}
				$item['min_date'] = $min;
				$item['max_date'] = $max;
			}

			$clean[] = $item;
			if ( count( $clean ) >= self::MAX_QUESTIONS ) {
				break;
			}
		}

		return $clean;
	}

	/**
	 * Options arrive as an array or as text with one option per line.
	 */
	private static function sanitize_options( $raw ) {
		if ( is_string( $raw ) ) {
			$raw = preg_split( '/\r\n|\r|\n/', $raw );
		}
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $opt ) {
			$opt = self::clip( self::clean_text( $opt ), self::MAX_OPTION_LENGTH );
			if ( '' !== $opt && ! in_array( $opt, $out, true ) ) {
				$out[] = $opt;
			}
			if ( count( $out ) >= self::MAX_OPTIONS ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Two optional whole numbers within [floor, ceil]; swapped if reversed.
	 * Blank stays blank ('' = no limit).
	 */
	private static function int_range( $min, $max, $floor, $ceil ) {
		$norm = function( $v ) use ( $floor, $ceil ) {
			$v = trim( (string) $v );
			if ( '' === $v || ! is_numeric( $v ) ) {
				return '';
			}
			return (int) max( $floor, min( $ceil, (int) round( (float) $v ) ) );
		};
		$min = $norm( $min );
		$max = $norm( $max );
		if ( '' !== $min && '' !== $max && $min > $max ) {
			list( $min, $max ) = array( $max, $min );
		}
		return array( $min, $max );
	}

	/**
	 * Two optional numbers (decimals allowed); swapped if reversed.
	 */
	private static function num_range( $min, $max ) {
		$norm = function( $v ) {
			$v = trim( (string) $v );
			if ( '' === $v || ! is_numeric( $v ) ) {
				return '';
			}
			$f = (float) $v;
			$f = max( -1e12, min( 1e12, $f ) );
			return ( floor( $f ) === $f ) ? (int) $f : round( $f, 4 );
		};
		$min = $norm( $min );
		$max = $norm( $max );
		if ( '' !== $min && '' !== $max && $min > $max ) {
			list( $min, $max ) = array( $max, $min );
		}
		return array( $min, $max );
	}

	public static function valid_date( $value ) {
		$value = (string) $value;
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
			return false;
		}
		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
	}

	/**
	 * Like sanitize_text_field(), but keeps text such as "100%cashback"
	 * intact (sanitize_text_field deletes anything that looks like a
	 * %-encoded byte). Strips HTML, invalid UTF-8 and control characters;
	 * collapses whitespace (keeps line breaks when $multiline).
	 */
	public static function clean_text( $text, $multiline = false ) {
		$text = wp_check_invalid_utf8( is_scalar( $text ) ? (string) $text : '' );
		if ( false !== strpos( $text, '<' ) ) {
			$text = wp_pre_kses_less_than( $text );
			$text = wp_strip_all_tags( $text, false );
			$text = str_replace( "<\n", "&lt;\n", $text );
		}
		// Remove control characters except tab/newline.
		$text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text );
		if ( $multiline ) {
			$text  = str_replace( array( "\r\n", "\r" ), "\n", $text );
			$lines = array_map(
				function( $line ) {
					return trim( preg_replace( '/[ \t]+/', ' ', $line ) );
				},
				explode( "\n", $text )
			);
			$text = trim( preg_replace( "/\n{3,}/", "\n\n", implode( "\n", $lines ) ) );
		} else {
			$text = trim( preg_replace( '/[\r\n\t ]+/', ' ', $text ) );
		}
		return (string) $text;
	}

	private static function clip( $text, $len ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $len ) : substr( $text, 0, $len );
	}

	private static function length( $text ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
	}

	// -----------------------------------------------------------------
	// Reading
	// -----------------------------------------------------------------

	/**
	 * A campaign's questions, as saved by the admin.
	 */
	public static function for_campaign( $campaign ) {
		if ( empty( $campaign ) || empty( $campaign->questions ) ) {
			return array();
		}
		$decoded = json_decode( (string) $campaign->questions, true );
		return self::sanitize_questions( is_array( $decoded ) ? $decoded : array() );
	}

	/**
	 * Only the questions that can actually be shown (a choice question with
	 * no options is skipped, and the editor warns about it).
	 */
	public static function visible( array $questions ) {
		return array_values(
			array_filter(
				$questions,
				function( $q ) {
					return ! self::is_choice_type( $q['type'] ) || ! empty( $q['options'] );
				}
			)
		);
	}

	/**
	 * Problems the admin should fix, shown in the campaign editor.
	 */
	public static function warnings( array $questions ) {
		$warnings = array();
		foreach ( $questions as $q ) {
			if ( self::is_choice_type( $q['type'] ) && empty( $q['options'] ) ) {
				/* translators: %s: question text */
				$warnings[] = sprintf( __( '"%s" has no options, so it is hidden from visitors. Add at least one option (one per line).', 'scratch-win' ), $q['label'] );
			}
			if ( 'checkbox' === $q['type'] && '' !== $q['min_select'] && (int) $q['min_select'] > count( $q['options'] ) ) {
				/* translators: %s: question text */
				$warnings[] = sprintf( __( '"%s" asks for more selections than it has options.', 'scratch-win' ), $q['label'] );
			}
		}
		return $warnings;
	}

	/**
	 * Decode a lead's stored answers into a list of { id, label, value }.
	 */
	public static function lead_answers( $lead ) {
		if ( empty( $lead ) || empty( $lead->answers ) ) {
			return array();
		}
		$decoded = json_decode( (string) $lead->answers, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}
		$out = array();
		foreach ( $decoded as $a ) {
			if ( is_array( $a ) && isset( $a['label'] ) ) {
				$out[] = array(
					'id'    => (string) ( $a['id'] ?? '' ),
					'label' => (string) $a['label'],
					'value' => $a['value'] ?? '',
				);
			}
		}
		return $out;
	}

	/**
	 * One answer value as plain text ("A, B" for checkboxes).
	 */
	public static function value_text( $value ) {
		if ( is_array( $value ) ) {
			$value = implode( ', ', array_map( 'strval', $value ) );
		}
		// Plain text (email, CSV, list): turn &lt; etc. back into characters.
		return wp_specialchars_decode( (string) $value, ENT_QUOTES );
	}

	/**
	 * All answers as "Question: answer" lines.
	 */
	public static function answers_text( array $answers, $separator = "\n" ) {
		$lines = array();
		foreach ( $answers as $a ) {
			$lines[] = wp_specialchars_decode( $a['label'], ENT_QUOTES ) . ': ' . self::value_text( $a['value'] );
		}
		return implode( $separator, $lines );
	}

	// -----------------------------------------------------------------
	// Validating a visitor's answers (server side — the one that counts)
	// -----------------------------------------------------------------

	/**
	 * @param array $questions Sanitized questions (visible ones are used).
	 * @param mixed $posted    Raw $_POST['answers'] (already unslashed).
	 * @return array { answers: list of {id,label,value}, errors: id => message }
	 */
	public static function validate( array $questions, $posted ) {
		$posted  = is_array( $posted ) ? $posted : array();
		$answers = array();
		$errors  = array();

		foreach ( self::visible( $questions ) as $q ) {
			$raw   = $posted[ $q['id'] ] ?? '';
			$value = '';
			$error = '';

			if ( 'checkbox' === $q['type'] ) {
				$value = self::validate_checkbox( $q, $raw, $error );
			} else {
				if ( is_array( $raw ) ) {
					$raw = '';
				}
				$raw = (string) $raw;
				if ( 'textarea' === $q['type'] ) {
					$value = self::clean_text( $raw, true );
				} else {
					$value = self::clean_text( $raw );
				}

				if ( '' === $value ) {
					if ( $q['required'] ) {
						$error = self::msg_required( $q );
					}
				} else {
					$error = self::validate_value( $q, $value );
				}
			}

			if ( '' !== $error ) {
				$errors[ $q['id'] ] = $error;
				continue;
			}

			$answers[] = array(
				'id'    => $q['id'],
				'label' => $q['label'],
				'value' => $value,
			);
		}

		return array(
			'answers' => $answers,
			'errors'  => $errors,
		);
	}

	private static function validate_checkbox( $q, $raw, &$error ) {
		$picked = array();
		foreach ( (array) $raw as $v ) {
			if ( is_array( $v ) ) {
				continue;
			}
			$v = self::clean_text( $v );
			if ( in_array( $v, $q['options'], true ) && ! in_array( $v, $picked, true ) ) {
				$picked[] = $v;
			} elseif ( '' !== $v ) {
				$error = __( 'Please choose from the options given.', 'scratch-win' );
				return array();
			}
		}
		// Keep the admin's option order.
		$picked = array_values( array_intersect( $q['options'], $picked ) );
		$count  = count( $picked );

		$min = '' !== $q['min_select'] ? (int) $q['min_select'] : 0;
		$max = '' !== $q['max_select'] ? (int) $q['max_select'] : 0;

		if ( 0 === $count ) {
			if ( $q['required'] || $min > 0 ) {
				$error = $min > 1
					/* translators: %d: number of options */
					? sprintf( __( 'Please select at least %d options.', 'scratch-win' ), $min )
					: __( 'Please select at least one option.', 'scratch-win' );
			}
			return $picked;
		}
		if ( $min && $count < $min ) {
			/* translators: %d: number of options */
			$error = sprintf( _n( 'Please select at least %d option.', 'Please select at least %d options.', $min, 'scratch-win' ), $min );
		} elseif ( $max && $count > $max ) {
			/* translators: %d: number of options */
			$error = sprintf( _n( 'Please select no more than %d option.', 'Please select no more than %d options.', $max, 'scratch-win' ), $max );
		}
		return $picked;
	}

	/**
	 * Validate one non-empty, non-checkbox value. Returns '' when valid.
	 */
	private static function validate_value( $q, $value ) {
		switch ( $q['type'] ) {
			case 'text':
			case 'textarea':
				$len = self::length( $value );
				if ( '' !== $q['min_length'] && $len < (int) $q['min_length'] ) {
					/* translators: %d: number of characters */
					return sprintf( _n( 'Please enter at least %d character.', 'Please enter at least %d characters.', (int) $q['min_length'], 'scratch-win' ), (int) $q['min_length'] );
				}
				if ( '' !== $q['max_length'] && $len > (int) $q['max_length'] ) {
					/* translators: %d: number of characters */
					return sprintf( _n( 'Please enter no more than %d character.', 'Please enter no more than %d characters.', (int) $q['max_length'], 'scratch-win' ), (int) $q['max_length'] );
				}
				return self::check_pattern( $q['pattern'], $value );

			case 'select':
			case 'radio':
				return in_array( $value, $q['options'], true ) ? '' : __( 'Please choose from the options given.', 'scratch-win' );

			case 'number':
				if ( ! preg_match( '/^-?\d+(\.\d+)?$/', $value ) ) {
					return __( 'Please enter a number.', 'scratch-win' );
				}
				if ( $q['integer'] && false !== strpos( $value, '.' ) ) {
					return __( 'Please enter a whole number.', 'scratch-win' );
				}
				$num = (float) $value;
				if ( '' !== $q['min'] && $num < (float) $q['min'] ) {
					/* translators: %s: minimum value */
					return sprintf( __( 'Please enter %s or more.', 'scratch-win' ), self::num_text( $q['min'] ) );
				}
				if ( '' !== $q['max'] && $num > (float) $q['max'] ) {
					/* translators: %s: maximum value */
					return sprintf( __( 'Please enter %s or less.', 'scratch-win' ), self::num_text( $q['max'] ) );
				}
				return '';

			case 'email':
				return is_email( $value ) ? '' : __( 'Please enter a valid email address.', 'scratch-win' );

			case 'date':
				if ( ! self::valid_date( $value ) ) {
					return __( 'Please enter a valid date.', 'scratch-win' );
				}
				if ( '' !== $q['min_date'] && $value < $q['min_date'] ) {
					/* translators: %s: date */
					return sprintf( __( 'Please choose a date on or after %s.', 'scratch-win' ), self::date_text( $q['min_date'] ) );
				}
				if ( '' !== $q['max_date'] && $value > $q['max_date'] ) {
					/* translators: %s: date */
					return sprintf( __( 'Please choose a date on or before %s.', 'scratch-win' ), self::date_text( $q['max_date'] ) );
				}
				return '';
		}
		return '';
	}

	private static function check_pattern( $pattern, $value ) {
		switch ( $pattern ) {
			case 'letters':
				return preg_match( "/^[\\p{L}\\p{M}\\s.'-]+$/u", $value ) ? '' : __( 'Please use letters and spaces only.', 'scratch-win' );
			case 'digits':
				return preg_match( '/^[0-9]+$/', $value ) ? '' : __( 'Please use digits only.', 'scratch-win' );
			case 'alnum':
				return preg_match( '/^[\p{L}\p{M}\p{N}\s]+$/u', $value ) ? '' : __( 'Please use letters, digits and spaces only.', 'scratch-win' );
		}
		return '';
	}

	private static function msg_required( $q ) {
		if ( in_array( $q['type'], array( 'select', 'radio' ), true ) ) {
			return __( 'Please choose an option.', 'scratch-win' );
		}
		return __( 'This field is required.', 'scratch-win' );
	}

	private static function num_text( $n ) {
		return (string) ( floor( (float) $n ) === (float) $n ? (int) $n : $n );
	}

	public static function date_text( $ymd ) {
		$ts = strtotime( $ymd . ' 12:00:00 UTC' );
		return $ts ? wp_date( 'j F Y', $ts, new DateTimeZone( 'UTC' ) ) : $ymd;
	}

	/**
	 * Messages the browser uses, so its wording matches the server's exactly.
	 */
	public static function js_messages() {
		return array(
			'required'      => __( 'This field is required.', 'scratch-win' ),
			'chooseOne'     => __( 'Please choose an option.', 'scratch-win' ),
			'chooseValid'   => __( 'Please choose from the options given.', 'scratch-win' ),
			/* translators: %d: number of characters */
			'minLength'     => __( 'Please enter at least %d characters.', 'scratch-win' ),
			/* translators: %d: number of characters */
			'maxLength'     => __( 'Please enter no more than %d characters.', 'scratch-win' ),
			'letters'       => __( 'Please use letters and spaces only.', 'scratch-win' ),
			'digits'        => __( 'Please use digits only.', 'scratch-win' ),
			'alnum'         => __( 'Please use letters, digits and spaces only.', 'scratch-win' ),
			'number'        => __( 'Please enter a number.', 'scratch-win' ),
			'integer'       => __( 'Please enter a whole number.', 'scratch-win' ),
			/* translators: %s: minimum value */
			'min'           => __( 'Please enter %s or more.', 'scratch-win' ),
			/* translators: %s: maximum value */
			'max'           => __( 'Please enter %s or less.', 'scratch-win' ),
			'email'         => __( 'Please enter a valid email address.', 'scratch-win' ),
			'date'          => __( 'Please enter a valid date.', 'scratch-win' ),
			/* translators: %s: date */
			'minDate'       => __( 'Please choose a date on or after %s.', 'scratch-win' ),
			/* translators: %s: date */
			'maxDate'       => __( 'Please choose a date on or before %s.', 'scratch-win' ),
			'selectOne'     => __( 'Please select at least one option.', 'scratch-win' ),
			/* translators: %d: number of options */
			'minSelect'     => __( 'Please select at least %d options.', 'scratch-win' ),
			/* translators: %d: number of options */
			'maxSelect'     => __( 'Please select no more than %d options.', 'scratch-win' ),
			// Singular forms, used when the number is 1.
			'minLength1'    => _n( 'Please enter at least %d character.', 'Please enter at least %d characters.', 1, 'scratch-win' ),
			'maxLength1'    => _n( 'Please enter no more than %d character.', 'Please enter no more than %d characters.', 1, 'scratch-win' ),
			'maxSelect1'    => _n( 'Please select no more than %d option.', 'Please select no more than %d options.', 1, 'scratch-win' ),
			'reload'        => __( 'This form has been updated. Please reload the page and try again.', 'scratch-win' ),
			'fixErrors'     => __( 'Please check the highlighted answers.', 'scratch-win' ),
			/* translators: 1: characters typed, 2: character limit */
			'counter'       => __( '%1$s / %2$s', 'scratch-win' ),
		);
	}

	// -----------------------------------------------------------------
	// Rendering the visitor's form
	// -----------------------------------------------------------------

	/**
	 * Print the question fields. $uid keeps element ids unique when several
	 * cards are on one page.
	 */
	public static function render_fields( array $questions, $uid ) {
		$questions = self::visible( $questions );
		if ( empty( $questions ) ) {
			return;
		}
		echo '<div class="sw-questions">';
		foreach ( $questions as $q ) {
			self::render_field( $q, $uid );
		}
		echo '</div>';
	}

	private static function render_field( $q, $uid ) {
		$field_id = 'sw-' . $uid . '-' . $q['id'];
		$help_id  = $field_id . '-help';
		$err_id   = $field_id . '-error';
		$name     = 'answers[' . $q['id'] . ']';
		$required = (bool) $q['required'];
		$describe = trim( ( $q['help'] ? $help_id . ' ' : '' ) . $err_id );

		$data = array(
			'data-qid'      => $q['id'],
			'data-type'     => $q['type'],
			'data-required' => $required ? '1' : '0',
		);
		foreach ( array( 'min_length', 'max_length', 'min', 'max', 'min_select', 'max_select', 'min_date', 'max_date' ) as $key ) {
			if ( '' !== $q[ $key ] ) {
				$data[ 'data-' . str_replace( '_', '-', $key ) ] = (string) $q[ $key ];
			}
		}
		if ( self::is_text_type( $q['type'] ) ) {
			$data['data-pattern'] = $q['pattern'];
		}
		if ( 'number' === $q['type'] && $q['integer'] ) {
			$data['data-integer'] = '1';
		}
		if ( 'date' === $q['type'] ) {
			if ( '' !== $q['min_date'] ) {
				$data['data-min-date-text'] = self::date_text( $q['min_date'] );
			}
			if ( '' !== $q['max_date'] ) {
				$data['data-max-date-text'] = self::date_text( $q['max_date'] );
			}
		}

		$attrs = '';
		foreach ( $data as $k => $v ) {
			$attrs .= ' ' . $k . '="' . esc_attr( $v ) . '"';
		}

		$is_group = in_array( $q['type'], array( 'radio', 'checkbox' ), true );
		$star     = $required ? ' <span class="sw-q-required" aria-hidden="true">*</span>' : '';

		echo '<div class="sw-question sw-question-' . esc_attr( $q['type'] ) . '"' . $attrs . '>'; // phpcs:ignore -- attrs escaped above.

		if ( $is_group ) {
			echo '<fieldset class="sw-q-group" aria-describedby="' . esc_attr( $describe ) . '"' . ( $required ? ' aria-required="true"' : '' ) . '>';
			echo '<legend class="sw-q-label">' . esc_html( $q['label'] ) . $star . '</legend>'; // phpcs:ignore
		} else {
			echo '<label class="sw-q-label" for="' . esc_attr( $field_id ) . '">' . esc_html( $q['label'] ) . $star . '</label>'; // phpcs:ignore
		}

		$common = ' id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '" class="sw-q-input" aria-describedby="' . esc_attr( $describe ) . '"' . ( $required ? ' aria-required="true"' : '' );
		$ph     = '' !== $q['placeholder'] ? ' placeholder="' . esc_attr( $q['placeholder'] ) . '"' : '';

		switch ( $q['type'] ) {
			case 'text':
				echo '<input type="text"' . $common . $ph . ' maxlength="' . (int) $q['max_length'] . '" autocomplete="off">'; // phpcs:ignore
				break;

			case 'textarea':
				echo '<textarea rows="3"' . $common . $ph . ' maxlength="' . (int) $q['max_length'] . '"></textarea>'; // phpcs:ignore
				break;

			case 'email':
				echo '<input type="email" inputmode="email" autocomplete="email"' . $common . $ph . '>'; // phpcs:ignore
				break;

			case 'number':
				$step = $q['integer'] ? '1' : 'any';
				$mm   = ( '' !== $q['min'] ? ' min="' . esc_attr( $q['min'] ) . '"' : '' ) . ( '' !== $q['max'] ? ' max="' . esc_attr( $q['max'] ) . '"' : '' );
				echo '<input type="number" step="' . esc_attr( $step ) . '" inputmode="' . ( $q['integer'] ? 'numeric' : 'decimal' ) . '"' . $common . $ph . $mm . '>'; // phpcs:ignore
				break;

			case 'date':
				$mm = ( '' !== $q['min_date'] ? ' min="' . esc_attr( $q['min_date'] ) . '"' : '' ) . ( '' !== $q['max_date'] ? ' max="' . esc_attr( $q['max_date'] ) . '"' : '' );
				echo '<input type="date"' . $common . $mm . '>'; // phpcs:ignore
				break;

			case 'select':
				echo '<select' . $common . '>'; // phpcs:ignore
				echo '<option value="">' . esc_html( '' !== $q['placeholder'] ? $q['placeholder'] : __( '— Select —', 'scratch-win' ) ) . '</option>';
				foreach ( $q['options'] as $opt ) {
					echo '<option value="' . esc_attr( $opt ) . '">' . esc_html( $opt ) . '</option>';
				}
				echo '</select>';
				break;

			case 'radio':
			case 'checkbox':
				$input_name = 'checkbox' === $q['type'] ? $name . '[]' : $name;
				echo '<div class="sw-q-options">';
				foreach ( $q['options'] as $i => $opt ) {
					$opt_id = $field_id . '-' . $i;
					echo '<label class="sw-q-option" for="' . esc_attr( $opt_id ) . '">';
					echo '<input type="' . esc_attr( $q['type'] ) . '" id="' . esc_attr( $opt_id ) . '" name="' . esc_attr( $input_name ) . '" class="sw-q-input" value="' . esc_attr( $opt ) . '">';
					echo '<span>' . esc_html( $opt ) . '</span></label>';
				}
				echo '</div>';
				break;
		}

		if ( $is_group ) {
			echo '</fieldset>';
		}

		echo '<div class="sw-q-meta">';
		if ( $q['help'] ) {
			echo '<span class="sw-q-help" id="' . esc_attr( $help_id ) . '">' . esc_html( $q['help'] ) . '</span>';
		}
		if ( self::is_text_type( $q['type'] ) ) {
			echo '<span class="sw-q-counter" aria-hidden="true">0 / ' . (int) $q['max_length'] . '</span>';
		}
		echo '</div>';
		echo '<p class="sw-q-error" id="' . esc_attr( $err_id ) . '" role="alert" style="display:none;"></p>';
		echo '</div>';
	}
}
