<?php
class FlowPress_Sniffs_WP_EnqueuedInlineResourcesSniff implements PHP_CodeSniffer_Sniff {

	public function register() {
		return array(
			T_CONSTANT_ENCAPSED_STRING,
			T_DOUBLE_QUOTED_STRING,
			T_INLINE_HTML,
		);

	}

	public function process( PHP_CodeSniffer_File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();
		$token  = $tokens[ $stackPtr ];
var_dump($token);
		// if ( preg_match( '#rel=[\'"]?stylesheet[\'"]?#', $token['content'] ) > 0 ) {
			$phpcsFile->addError( 'Stylesheets must be registered/enqueued via wp_enqueue_style', $stackPtr, 'NonEnqueuedStylesheet' );
		// }

		// if ( preg_match( '#<script[^>]*(?<=src=)#', $token['content'] ) > 0 ) {
			// $phpcsFile->addError( 'Scripts must be registered/enqueued via wp_enqueue_script', $stackPtr, 'NonEnqueuedScript' );
		// }
		// var_dump('test');

	} // End process().

} // End class.
