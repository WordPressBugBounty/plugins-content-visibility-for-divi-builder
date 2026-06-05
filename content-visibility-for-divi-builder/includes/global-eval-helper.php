<?php

// Intentionally NO namespace declaration. eval()'d code runs in the namespace
// of the calling function - keeping this helper in the global namespace means
// identifiers in $expression (functions, classes, constants) resolve against
// the global namespace at evaluation time, matching what plugin authors expect.
//
// This is the SOLE eval() call site in the entire plugin. It is used by:
//   - AoDTechnologies\ContentVisibilityForDiviBuilder\ContentVisibilityForDiviBuilder::evaluate_visibility_expression()
//   - AoDTechnologies\ContentVisibilityForDiviBuilder\ContentVisibilityForDiviBuilder::is_eval_available() (smoke probe)
//
// If you are reviewing this file for security: the upstream callers run
// $expression through validate_expression() before reaching here, which
// enforces token + callable allowlists.

if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! function_exists( 'cvdb_eval_expression' ) ) {
	function cvdb_eval_expression( $expression ) {
		return eval( 'return ' . $expression . ';' );
	}
}
