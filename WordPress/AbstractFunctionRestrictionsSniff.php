<?php
/**
 * WordPress Coding Standard.
 *
 * @package WPCS\WordPressCodingStandards
 * @link    https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards
 * @license https://opensource.org/licenses/MIT MIT
 */

/**
 * Restricts usage of some functions.
 *
 * @package WPCS\WordPressCodingStandards
 *
 * @since   0.3.0
 * @since   0.10.0 Class became a proper abstract class. This was already the behaviour.
 *                 Moved the file and renamed the class from
 *                 `WordPress_Sniffs_Functions_FunctionRestrictionsSniff` to
 *                 `WordPress_AbstractFunctionRestrictionsSniff`.
 * @since   0.11.0 Extends the WordPress_Sniff class.
 */
abstract class WordPress_AbstractFunctionRestrictionsSniff extends WordPress_Sniff {

	/**
	 * Exclude groups.
	 *
	 * Example: 'switch_to_blog,user_meta'
	 *
	 * @var string Comma-delimited group list.
	 */
	public $exclude = '';

	/**
	 * Groups of function data to check against.
	 * Don't use this in extended classes, override getGroups() instead.
	 * This is only used for Unit tests.
	 *
	 * @since 0.10.0
	 *
	 * @var array
	 */
	public static $unittest_groups = array();

	/**
	 * Regex pattern with placeholder for the function names.
	 *
	 * @since 0.10.0
	 *
	 * @var string
	 */
	protected $regex_pattern = '`\b(?:%s)\b`i';

	/**
	 * Cache for the group information.
	 *
	 * @since 0.10.0
	 *
	 * @var array
	 */
	protected $groups = array();

	/**
	 * Cache for the excluded groups information.
	 *
	 * @since 0.11.0
	 *
	 * @var array
	 */
	protected $excluded_groups = array();

	/**
	 * Groups of functions to restrict.
	 *
	 * This method should be overridden in extending classes.
	 *
	 * Example: groups => array(
	 *     'lambda' => array(
	 *         'type'      => 'error' | 'warning',
	 *         'message'   => 'Use anonymous functions instead please!',
	 *         'functions' => array( 'file_get_contents', 'create_function', 'mysql_*' ),
	 *         // Only useful when using wildcards:
	 *         'whitelist' => array( 'mysql_to_rfc3339' => true, ),
	 *     )
	 * )
	 *
	 * You can use * wildcards to target a group of functions.
	 * When you use * wildcards, you may inadvertently restrict too many
	 * functions. In that case you can add the `whitelist` key to
	 * whitelist individual functions to prevent false positives.
	 *
	 * @return array
	 */
	abstract public function getGroups();

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register() {
		// Prepare the function group regular expressions only once.
		if ( false === $this->setup_groups( 'functions' ) ) {
			return array();
		}

		return array(
			T_STRING,
		);
	}

	/**
	 * Set up the regular expressions for each group.
	 *
	 * @since 0.10.0
	 *
	 * @param string $key The group array index key where the input for the regular expression can be found.
	 * @return bool True if the groups were setup. False if not.
	 */
	protected function setup_groups( $key ) {
		// Prepare the function group regular expressions only once.
		$this->groups = $this->getGroups();

		if ( empty( $this->groups ) && empty( self::$unittest_groups ) ) {
			return false;
		}

		// Allow for adding extra unit tests.
		if ( ! empty( self::$unittest_groups ) ) {
			$this->groups = array_merge( $this->groups, self::$unittest_groups );
		}

		foreach ( $this->groups as $groupName => $group ) {
			if ( empty( $group[ $key ] ) ) {
				unset( $this->groups[ $groupName ] );
			} else {
				$items = array_map( array( $this, 'prepare_name_for_regex' ), $group[ $key ] );
				$items = implode( '|', $items );

				$this->groups[ $groupName ]['regex'] = sprintf( $this->regex_pattern, $items );
			}
		}

		if ( empty( $this->groups ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @param PHP_CodeSniffer_File $phpcsFile The file being scanned.
	 * @param int                  $stackPtr  The position of the current token
	 *                                        in the stack passed in $tokens.
	 *
	 * @return void
	 */
	public function process( PHP_CodeSniffer_File $phpcsFile, $stackPtr ) {

		$this->excluded_groups = array_flip( explode( ',', $this->exclude ) );
		if ( array_diff_key( $this->groups, $this->excluded_groups ) === array() ) {
			// All groups have been excluded.
			// Don't remove the listener as the exclude property can be changed inline.
			return;
		}

		// Make phpcsFile and tokens available as properties.
		$this->init( $phpcsFile );

		$this->is_targetted_token( $stackPtr );

	} // End process().

	/**
	 * Verify is the current token is a function call and one of the targetted functions.
	 *
	 * @since 0.11.0
	 *
	 * @param int $stackPtr The position of the current token in the stack.
	 *
	 * @return void
	 */
	public function is_targetted_token( $stackPtr ) {

		$token         = $this->tokens[ $stackPtr ];
		$token_content = strtolower( $token['content'] );

		// Exclude function definitions, class methods, and namespaced calls.
		if ( T_STRING === $token['code'] && isset( $this->tokens[ ( $stackPtr - 1 ) ] ) ) {
			$prev = $this->phpcsFile->findPrevious( PHP_CodeSniffer_Tokens::$emptyTokens, ( $stackPtr - 1 ), null, true );

			if ( false !== $prev ) {
				// Skip sniffing if calling a same-named method, or on function definitions.
				$skipped = array(
					T_FUNCTION        => T_FUNCTION,
					T_DOUBLE_COLON    => T_DOUBLE_COLON,
					T_OBJECT_OPERATOR => T_OBJECT_OPERATOR,
				);

				if ( isset( $skipped[ $this->tokens[ $prev ]['code'] ] ) ) {
					return;
				}

				// Skip namespaced functions, ie: \foo\bar() not \bar().
				if ( T_NS_SEPARATOR === $this->tokens[ $prev ]['code'] ) {
					$pprev = $this->phpcsFile->findPrevious( PHP_CodeSniffer_Tokens::$emptyTokens, ( $prev - 1 ), null, true );
					if ( false !== $pprev && T_STRING === $this->tokens[ $pprev ]['code'] ) {
						return;
					}
				}
			}
			unset( $prev, $pprev, $skipped );
		}

		foreach ( $this->groups as $groupName => $group ) {

			if ( isset( $this->excluded_groups[ $groupName ] ) ) {
				continue;
			}

			if ( isset( $group['whitelist'][ $token_content ] ) ) {
				continue;
			}

			if ( preg_match( $group['regex'], $token_content ) === 1 ) {
				$this->process_matched_token( $stackPtr, $groupName, $token_content );
			}
		}

	} // End is_targetted_token().

	/**
	 * Process a matched token.
	 *
	 * @since 0.11.0
	 *
	 * @param int    $stackPtr        The position of the current token in the stack.
	 * @param array  $group_name      The name of the group which was matched.
	 * @param string $matched_content The token content (function name) which was matched.
	 *
	 * @return void
	 */
	public function process_matched_token( $stackPtr, $group_name, $matched_content ) {

		if ( 'warning' === $this->groups[ $group_name ]['type'] ) {
			$addWhat = array( $this->phpcsFile, 'addWarning' );
		} else {
			$addWhat = array( $this->phpcsFile, 'addError' );
		}

		call_user_func(
			$addWhat,
			$this->groups[ $group_name ]['message'],
			$stackPtr,
			$group_name,
			array( $matched_content )
		);
	} // End process_matched_token().

	/**
	 * Prepare the function name for use in a regular expression.
	 *
	 * The getGroups() method allows for providing function names with a wildcard * to target
	 * a group of functions. This prepare routine takes that into account while still safely
	 * escaping the function name for use in a regular expression.
	 *
	 * @since 0.10.0
	 *
	 * @param string $function Function name.
	 * @return string Regex escaped function name.
	 */
	protected function prepare_name_for_regex( $function ) {
		$function = str_replace( array( '.*', '*' ), '#', $function ); // Replace wildcards with placeholder.
		$function = preg_quote( $function, '`' );
		$function = str_replace( '#', '.*', $function ); // Replace placeholder with regex wildcard.

		return $function;
	}

} // End class.
