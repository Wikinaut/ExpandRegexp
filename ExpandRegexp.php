<?php

// FIXME MODULE IS NOT MULTI-BYTE SAFE
// TG 20110530

/***
 *
 * ExpandRegexp -
 * expand Regular Expressions
 *
 * Originally written by Joseph Rézeau
 * http://joseph.rezeau.pagesperso-orange.fr/eao/developpement/expandRegexpToString.htm
 * (C) 2004 - 2012
 *
 * Credits
 *
 * Re-used some parts of Henk Schotel's original program Testaregex
 * http://home.wanadoo.nl/h.schotel/testaregex/
 *
 * A subroutine comes from the Hot Potatoes suite
 * http://web.uvic.ca/hrd/halfbaked/
 *
 * The script to test parentheses by Alberto Vallini 
 * http://www.unitedscripters.com/scripts/string2.html
 *
 * Inspired by the thread Expand regexes to strings
 * http://dbforums.com/t376186.html started by Stefan Schaden on http://dbforums.com/
 * and the Perl example provided by Benjamin Goldber.
 *
 **/


function isteacher( ) {
	return true;
}

function get_string( $p1, $p2, $myregexp = '' ) {
	echo "$p1 $p2 <b>$myregexp</b><br/>";
}

function expand_regexp( $myregexp ) {
	global $regexporiginal;
	global $CFG;
	
	$regexporiginal = $myregexp;
	$parenserror    = check_my_parens( $myregexp );
	if ( $parenserror ) {
		return -1;
	}
	
	// FIXME
	$charlist = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyzäöüÄÖÜß';
	
	// change [a-c] to [abc] NOTE: ^ metacharacter is not processed inside []
	$pattern = '/\\[\w-\w\\]/'; // find [a-c] in $myregexp
	while ( preg_match( $pattern, $myregexp, $matches, PREG_OFFSET_CAPTURE ) ) {
		$result      = $matches[ 0 ][ 0 ];
		$offset      = $matches[ 0 ][ 1 ];
		$stringleft  = substr( $myregexp, 0, $offset + 1 );
		$stringright = substr( $myregexp, $offset + strlen( $result ) - 1 );
		$c1          = $result[ 1 ];
		$c3          = $result[ 3 ];
		$rs          = '';
		for ( $c = strrpos( $charlist, $c1 ); $c < strrpos( $charlist, $c3 ) + 1; $c++ ) {
			$rs .= $charlist[ $c ];
		}
		$myregexp = $stringleft . $rs . $stringright;
		
	}
	// provisionally replace existing escaped [] before processing the change [abc] to (a|b|c) JR 11-9-2007
	// see Oleg http://moodle.org/mod/forum/discuss.php?d=38542&parent=354095
	while ( strpos( $myregexp, '\[' ) ) {
		$c1       = strpos( $myregexp, '\[' );
		$c0       = $myregexp[ $c1 ];
		$myregexp = substr( $myregexp, 0, $c1 ) . '¬' . substr( $myregexp, $c1 + 2 );
	}
	while ( strpos( $myregexp, '\]' ) ) {
		$c1       = strpos( $myregexp, '\]' );
		$c0       = $myregexp[ $c1 ];
		$myregexp = substr( $myregexp, 0, $c1 ) . '¤' . substr( $myregexp, $c1 + 2 );
	}
	
	// change [abc] to (a|b|c)
	$pattern = '/\[.*?\]/'; // find [abc] in $myregexp
	while ( preg_match( $pattern, $myregexp, $matches, PREG_OFFSET_CAPTURE ) ) {
		$result      = $matches[ 0 ][ 0 ];
		$offset      = $matches[ 0 ][ 1 ];
		$stringleft  = substr( $myregexp, 0, $offset );
		$stringright = substr( $myregexp, $offset + strlen( $result ) );
		$rs          = substr( $result, 1, strlen( $result ) - 2 );
		$r           = '';
		for ( $i = 0; $i < strlen( $rs ); $i++ ) {
			$r .= $rs[ $i ] . '|';
		}
		$rs       = '(' . substr( $r, 0, strlen( $r ) - 1 ) . ')';
		$myregexp = $stringleft . $rs . $stringright;
	}
	
	// we can now safely restore the previously replaced escaped []
	while ( strpos( $myregexp, '¬' ) ) {
		$c1       = strpos( $myregexp, '¬' );
		$c0       = $myregexp[ $c1 ];
		$myregexp = substr( $myregexp, 0, $c1 ) . '\[' . substr( $myregexp, $c1 + 2 );
	}
	while ( strpos( $myregexp, '¤' ) ) {
		$c1       = strpos( $myregexp, '¤' );
		$c0       = $myregexp[ $c1 ];
		$myregexp = substr( $myregexp, 0, $c1 ) . '\]' . substr( $myregexp, $c1 + 2 );
	}
	
	// process ? in regexp (zero or one occurrence of preceding char)
	while ( strpos( $myregexp, '?' ) ) {
		$c1 = strpos( $myregexp, '?' );
		$c0 = $myregexp[ $c1 - 1 ];
		
		// if \? -> escaped ?, treat as literal char (replace with ¬ char temporarily)
		// this ¬ char chosen because non-alphanumeric & rarely used...
		if ( $c0 == '\\' ) {
			$myregexp = substr( $myregexp, 0, $c1 - 1 ) . '¬' . substr( $myregexp, $c1 + 1 );
			continue;
		}
		// if )? -> meta ? action upon parens (), replace with ¤ char temporarily
		// this ¤ char chosen because non-alphanumeric & rarely used...
		if ( $c0 == ')' ) {
			$myregexp = substr( $myregexp, 0, $c1 - 1 ) . '¤' . substr( $myregexp, $c1 + 1 );
			continue;
		}
		// if ? metacharacter acts upon an escaped char, put it in $c2
		if ( $myregexp[ $c1 - 2 ] == '\\' ) {
			$c0 = '\\' . $c0;
		}
		$c2 = '(' . $c0 . '|)';
		$myregexp = str_replace( $c0 . '?', $c2, $myregexp );
	}
	// replaces possible temporary ¬ char with escaped question mark
	if ( strpos( $myregexp, '¬' ) != -1 ) {
		$myregexp = str_replace( '¬', '\?', $myregexp );
	}
	// replaces possible temporary ¤ char with escaped question mark
	if ( strpos( $myregexp, '¤' ) != -1 ) {
		$myregexp = str_replace( '¤', ')?', $myregexp );
	}
	
	// process ? metacharacter acting upon a set of parentheses \(.*?\)\?
	$myregexp = str_replace( ')?', '|)', $myregexp );
	
	// replace escaped characters with their escape code
	while ( $c = strpos( $myregexp, '\\' ) ) {
		$s1 = substr( $myregexp, $c, 2 );
		$s2 = $myregexp[ $c + 1 ];
		$s2 = rawurlencode( $s2 );
		
		//  alaphanumeric chars can't be escaped; escape codes useful here are:
		//  . = %2e    ; + = %2b ; * = %2a
		// add any others as needed & modify below accordingly
		switch ( $s2 ) {
			case '.':
				$s2 = '%2e';
				break;
			case '+':
				$s2 = '%2b';
				break;
			case '*':
				$s2 = '%2a';
				break;
		}
		$myregexp = str_replace( $s1, $s2, $myregexp );
	}
	
	// remove starting and trailing metacharacters; not used for generation but useful for testing regexp
	if ( strpos( $myregexp, '^' ) ) {
		$myregexp = substr( $myregexp, 1 );
	}
	if ( strpos( $myregexp, '$' ) == strlen( $myregexp ) - 1 ) {
		$myregexp = substr( $myregexp, 0, strlen( $myregexp ) - 1 );
	}
	
	// process metacharacters not accepted in sentence generation
	$illegalchars = array(
		 '+',
		'*',
		'.',
		'{',
		'}' 
	);
	$illegalchar = false;
	foreach ( $illegalchars as $i ) {
		if ( strpos( $myregexp, $i ) ) {
			$illegalchar = true;
		}
	}
	if ( $illegalchar == true && isteacher() ) {
		echo ( '<p>' . get_string( 'cannotgenerateregexp', 'qtype_regexp' ) . implode( ' ', $illegalchars ) . '</p><p>' . get_string( 'cannotgenerateregexp2', 'qtype_regexp' ) . '</p><p>' . get_string( 'regexperror', 'qtype_regexp', $myregexp ) . '</strong></p>' );
		
		// PATCH TG 28.05.2008
		// return ('$myregexp');
		return -1;
	}
	
	$mynewregexp = find_nested_ors( $myregexp ); // check $myregexp for nested parentheses
	if ( $mynewregexp != null ) {
		$myregexp = $mynewregexp;
	}
	
	$result = find_ors( $myregexp ); // expand parenthesis contents
	if ( is_array( $result ) ) {
		$results = implode( '\n', $result );
	}
	return $result; // returns array of alternate strings
}

function check_my_parens( $myregexp ) {
	$openparen    = 0;
	$closeparen   = 0;
	$opensqbrack  = 0;
	$closesqbrack = 0;
	$iserror      = false;
	$message      = '';
	for ( $i = 0; $i < strlen( $myregexp ); $i++ ) {
		if ( $myregexp[ $i ] != '\\' ) {
			switch ( $myregexp[ $i ] ) {
				case '(':
					$openparen++;
					break;
				case ')':
					$closeparen++;
					break;
				case '[':
					$opensqbrack++;
					break;
				case ']':
					$closesqbrack++;
					break;
				default:
					break;
			}
		}
	}
	if ( ( $openparen != $closeparen ) || ( $opensqbrack != $closesqbrack ) ) {
		$iserror = true;
		$message .= get_string( 'regexperror', 'qtype_regexp', $myregexp ) . '<br>';
	}
	if ( $openparen != $closeparen ) {
		$message .= get_string( 'regexperrorparen', 'qtype_regexp' ) . ' - ' . get_string( 'regexperroropen', 'qtype_regexp', $openparen ) . " # " . get_string( 'regexperrorclose', 'qtype_regexp', $closeparen ) . '<br>';
	}
	if ( $opensqbrack != $closesqbrack ) {
		$message .= get_string( 'regexperrorsqbrack', 'qtype_regexp' ) . ' - ' . get_string( 'regexperroropen', 'qtype_regexp', $opensqbrack ) . " # " . get_string( 'regexperrorclose', 'qtype_regexp', $closesqbrack );
	}
	if ( $iserror ) {
		return $message;
	}
	return;
}

// find individual $nestedors expressions in $myregexp
function is_nested_ors( $mystring ) { //return false;
	$orsstart = 0;
	$orsend   = 0;
	$isnested = false;
	$parens   = 0;
	$result   = '';
	for ( $i = 0; $i < strlen( $mystring ); $i++ ) {
		switch ( $mystring[ $i ] ) {
			case '(':
				$parens++;
				if ( $parens == 1 ) {
					$orsstart = $i;
				}
				if ( $parens == 2 ) {
					$isnested = true;
				}
				break;
			case ')':
				$parens--;
				if ( $parens == 0 ) {
					if ( $isnested == true ) {
						$orsend = $i + 1;
						$i      = strlen( $mystring );
						break;
					} //end if
				} //end case
		} //end switch
	} // end for
	if ( $isnested == true ) {
		$result = substr( $mystring, $orsstart, $orsend - $orsstart );
		return $result;
	}
	return false;
}

// find nested parentheses
function is_parents( $myregexp ) {
	$finalresult = null;
	$pattern     = '/[^(|)]*\\(([^(|)]*\\|[^(|)]*)+\\)[^(|)]*/';
	if ( preg_match_all( $pattern, $myregexp, $matches, PREG_OFFSET_CAPTURE ) ) {
		$matches = $matches[ 0 ];
		for ( $i = 0; $i < sizeof( $matches ); $i++ ) {
			$thisresult = $matches[ $i ][ 0 ];
			$leftchar   = $thisresult[ 0 ];
			$rightchar  = $thisresult[ strlen( $thisresult ) - 1 ];
			$outerchars = $leftchar . $rightchar;
			if ( $outerchars !== '()' ) {
				$finalresult = $thisresult;
				break;
			}
		} // end for
	} // end if
	
	return $finalresult;
}

// find ((a|b)c)
function find_nested_ors( $myregexp ) {
	// find next nested parentheses in $myregexp
	while ( $nestedors = is_nested_ors( $myregexp ) ) {
		$nestedorsoriginal = $nestedors;
		
		// find what?
		while ( $myparent = is_parents( $nestedors ) ) {
			$leftchar   = $nestedors[ strpos( $nestedors, $myparent ) - 1 ];
			$rightchar  = $nestedors[ strpos( $nestedors, $myparent ) + strlen( $myparent ) ];
			$outerchars = $leftchar . $rightchar;
			// il ne faut sans doute pas faire de BREAK ici...
			if ( $outerchars == ')(' ) {
				//                break;
			}
			switch ( $outerchars ) {
				case '||':
				case '()':
					$leftpar  = '';
					$rightpar = '';
					break;
				case '((':
				case '))':
				case '(|':
				case '|(':
				case ')|':
				case '|)':
					$leftpar  = '(';
					$rightpar = ')';
					break;
				default:
					break;
			}
			$t1        = find_ors( $myparent );
			$t         = implode( '|', $t1 );
			$myresult  = $leftpar . $t . $rightpar;
			$nestedors = str_replace( $myparent, $myresult, $nestedors );
			
		}
		//    detect sequence of ((*|*)|(*|*)) within parentheses or |) or (| and remove all INSIDE parentheses
		$pattern = '/(\\(|\\|)\\([^(|)]*\\|[^(|)]*\\)(\\|\\([^(|)]*\\|[^(|)]*\\))*(\\)|\\|)/';
		while ( preg_match( $pattern, $nestedors, $matches, PREG_OFFSET_CAPTURE ) ) {
			$plainors  = $matches[ 0 ][ 0 ];
			$leftchar  = $plainors[ 0 ];
			$rightchar = $plainors[ strlen( $plainors ) - 1 ];
			$plainors2 = substr( $plainors, 1, strlen( $plainors ) - 2 ); // remove leading & trailing chars
			$plainors2 = str_replace( '(', '', $plainors2 );
			$plainors2 = str_replace( ')', '', $plainors2 );
			$plainors2 = $leftchar . $plainors2 . $rightchar;
			$nestedors = str_replace( $plainors, $plainors2, $nestedors );
			if ( is_parents( $nestedors ) ) {
				$myregexp = str_replace( $nestedorsoriginal, $nestedors, $myregexp );
				continue;
			}
		}
		
		//        any sequence of (|)(|) in $nestedors? process them all
		$pattern = '/(\\([^(]*?\\|*?\\)){2,99}/';
		while ( preg_match( $pattern, $nestedors, $matches, PREG_OFFSET_CAPTURE ) ) {
			$parensseq = $matches[ 0 ][ 0 ];
			$myresult  = find_ors( $parensseq );
			$myresult  = implode( '|', $myresult );
			$nestedors = str_replace( $parensseq, $myresult, $nestedors );
		}
		// test if we have reached the singleOrs stage
		if ( is_parents( $nestedors ) != null ) {
			$myregexp = str_replace( $nestedorsoriginal, $nestedors, $myregexp );
			continue;
		}
		// no parents left in $nestedors, ...
		// find all single (*|*|*|*) and remove parentheses
		$patternsingleors      = '/\\([^()]*\\)/';
		$patternsingleorstotal = '/^\\([^()]*\\)$/';
		
		while ( $p = preg_match( $patternsingleors, $nestedors, $matches, PREG_OFFSET_CAPTURE ) ) {
			$r = preg_match( $patternsingleorstotal, $nestedors, $matches, PREG_OFFSET_CAPTURE );
			if ( $r ) {
				if ( $matches[ 0 ][ 0 ] == $nestedors ) {
					break;
				} // we have reached top of $nestedors: keep ( )!
			}
			$r            = preg_match( $patternsingleors, $nestedors, $matches, PREG_OFFSET_CAPTURE );
			$singleparens = $matches[ 0 ][ 0 ];
			$myresult     = substr( $singleparens, 1, strlen( $singleparens ) - 2 );
			$nestedors    = str_replace( $singleparens, $myresult, $nestedors );
			if ( is_parents( $nestedors ) != null ) {
				$myregexp = str_replace( $nestedorsoriginal, $nestedors, $myregexp );
				continue;
			}
			
		}
		$myregexp = str_replace( $nestedorsoriginal, $nestedors, $myregexp );
		
	} // end while ($nestedors = is_nested_ors ($myregexp))
	return $myregexp;
}

function find_ors( $mystring ) {
	global $regexporiginal;
	
	//    add extra space between consecutive parentheses (that extra space will be removed later on)
	$pattern = '/\\(.*?\\|.*?\\)/';
	while ( strpos( $mystring, ')(' ) ) {
		$mystring = str_replace( ')(', ')Âµ(', $mystring );
	}
	if ( strpos( $mystring, ')(' ) ) {
		$mystring = str_replace( ')(', ')Â£(', $mystring );
	}
	//    in $mystring, find the parts outside of parentheses ($plainparts)
	$plainparts = preg_split( $pattern, $mystring );
	if ( $plainparts ) {
		$plainparts = index_plain_parts( $mystring, $plainparts );
	}
	$a = preg_match_all( $pattern, $mystring, $matches, PREG_OFFSET_CAPTURE );
	if ( !$a ) {
		$regexporiginal = stripslashes( $regexporiginal );
		return $regexporiginal;
	}
	$plainors = index_ors( $mystring, $matches );
	//    send $list of $plainparts and $plainors to expand_ors () function
	return ( expand_ors( $plainparts, $plainors ) );
}

function expand_ors( $plainparts, $plainors ) { //return;
	
	//    this function expands a chunk of words containing a single set of parenthesized alternatives
	//    of the type: <(aaa|bbb)> OR <ccc (aaa|bbb)> OR <ccc (aaa|bbb) ddd> etc.
	//    into a LIST of possible alternatives, 
	//    e.g. <ccc (aaa|bbb|)> -> <ccc aaa>, <ccc bbb>, <ccc>
	$expandedors              = array( );
	$expandedors[ ]           = '';
	$slen                     = sizeof( $expandedors );
	$expandedors[ $slen - 1 ] = '';
	if ( sizeof( $plainparts ) != 0 && $plainparts[ 0 ] == 0 ) { // if chunk begins with $plainparts
		$expandedors[ $slen - 1 ] = $plainparts[ 1 ];
		array_splice( $plainparts, 0, 2 );
	}
	while ( ( sizeof( $plainparts ) != 0 ) || ( sizeof( $plainors ) != 0 ) ) { // go through sentence $plainparts 
		$l = sizeof( $expandedors );
		for ( $k = 0; $k < $l; $k++ ) {
			for ( $m = 0; $m < sizeof( $plainors[ 1 ] ); $m++ ) {
				$expandedors[ ]       = '';
				$slen                 = sizeof( $expandedors ) - 1;
				$expandedors[ $slen ] = $expandedors[ 0 ] . $plainors[ 1 ][ $m ];
				if ( sizeof( $plainparts ) ) {
					if ( $plainparts[ 1 ] ) {
						$expandedors[ $slen ] .= $plainparts[ 1 ];
					}
				}
				$expandedors[ $slen ] = rawurldecode( $expandedors[ $slen ] );
			}
			array_splice( $expandedors, 0, 1 ); // remove current "model" sentence from Sentences    
		}
		array_splice( $plainors, 0, 2 ); // remove current $plainors
		array_splice( $plainparts, 0, 2 ); // remove current $plainparts
		
	}
	//    eliminate all extra Âµ signs which have been placed to replace consecutive parentheses by )Âµ(
	$n = count( $expandedors );
	for ( $i = 0; $i < $n; $i++ ) {
		if ( is_int( strpos( $expandedors[ $i ], 'Âµ' ) ) ) { //corrects strpos for 1st char of a string found!
			$expandedors[ $i ] = str_replace( 'Âµ', '', $expandedors[ $i ] );
		}
	}
	return ( $expandedors );
}

function index_plain_parts( $mystring, $plainparts ) {
	$indexedplainparts = array( );
	if ( is_array( $plainparts ) ) {
		foreach ( $plainparts as $parts ) {
			if ( $parts ) {
				$index                = strpos( $mystring, $parts );
				$indexedplainparts[ ] = $index;
				$indexedplainparts[ ] = $parts;
			}
		}
	}
	return ( $indexedplainparts );
}

function index_ors( $mystring, $plainors ) {
	$indexedplainors = array( );
	foreach ( $plainors as $ors ) {
		foreach ( $ors as $or ) {
			$indexedplainors[ ] = $or[ 1 ];
			$o                  = substr( $or[ 0 ], 1, strlen( $or[ 0 ] ) - 2 );
			$o                  = explode( '|', $o );
			$indexedplainors[ ] = $o;
		}
	}
	return ( $indexedplainors );
}

// functions adapted from Hot Potatoes
function check_beginning( $guess, $answer, $ignorecase, $ishint ) {
	/// Use text services
	$textlib = textlib_get_instance();
	if ( $textlib->substr( $answer, 0, 8 ) == '<strong>' ) { // this answer is in fact the regexp itself, do not process it
		return;
	}
	$outstring = '';
	if ( $ignorecase ) {
		$guessoriginal = $guess;
		$guess         = strtoupper( $guess );
		$answer        = strtoupper( $answer );
	}
	$i1 = $textlib->strlen( $guess );
	$i2 = $textlib->strlen( $answer );
	
	for ( $i = 0; ( $i < $i1 && $i < $i2 ); $i++ ) {
		if ( strlen( $answer ) < $i ) {
			break;
		}
		if ( $textlib->substr( $guess, $i, 1 ) == $textlib->substr( $answer, $i, 1 ) ) {
			$outstring .= $textlib->substr( $guess, $i, 1 );
		} else {
			break;
		}
	}
	if ( $ignorecase ) {
		$outstring = $textlib->substr( $guessoriginal, 0, $textlib->strlen( $outstring ) );
	}
	return $outstring;
}

function get_closest( $guess, $answers, $ignorecase ) {
	/// Use text services
	$textlib      = textlib_get_instance();
	$closest[ 0 ] = ''; // closest answer
	$closest[ 1 ] = false; // closestcomplete false
	$closesta     = '';
	$l            = $textlib->strlen( $guess );
	$ishint       = $textlib->substr( $guess, $textlib->strlen( $guess ) - 1 );
	if ( $ishint == 'Â¶' ) {
		$ishint = TRUE;
	} else {
		$ishint = FALSE;
	}
	
	$rightbits = array( );
	foreach ( $answers as $answer ) {
		$rightbits[ 0 ][ ] = $answer;
		$rightbits[ 1 ][ ] = check_beginning( $guess, $answer, $ignorecase, $ishint );
	}
	$s       = sizeof( $rightbits );
	$longest = 0;
	
	if ( $s ) {
		$a = $rightbits[ 0 ];
		$s = sizeof( $a );
		for ( $i = 0; $i < $s; $i++ ) {
			$a = $rightbits[ 0 ][ $i ];
			$g = $rightbits[ 1 ][ $i ];
			if ( $textlib->strlen( $g ) > $longest ) {
				$longest  = $textlib->strlen( $g );
				$closesta = $g;
				if ( $ishint ) {
					$c = $textlib->substr( $a, $longest, 1 );
					$closesta .= $textlib->substr( $a, $longest, 1 );
					if ( $textlib->substr( $a, $longest, 1 ) == ' ' ) { // if hint letter is a space, add next one
						$closesta .= $textlib->substr( $a, $longest + 1, 1 );
					}
					if ( $a == $closesta ) {
						$closest[ 1 ] = true;
					}
					
				}
			}
		}
	}
	$closest[ 0 ] = $closesta;
	return $closest;
}
// end functions adapted from Hot Potatoes
