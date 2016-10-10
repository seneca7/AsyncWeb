<?php
namespace AsyncWeb\Text;

class Number{
	public static $LOCALE = "sk-SK";
	public static function format($number,$decimals=2){
		if($maxdecimal === null) $maxdecimal = $mindecimals;
		$a = new \NumberFormatter(Number::$LOCALE, \NumberFormatter::DECIMAL);
		$a->setAttribute(\NumberFormatter::FRACTION_DIGITS, $decimals); 
		return $a->format($number);
	}
}