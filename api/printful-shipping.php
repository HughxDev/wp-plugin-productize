<?php
if ( !empty( $_POST ) ) {
  header( 'Content-Type: application/json' );
}

if ( !isset ( $countryCode ) ) {
  $countryCode = $_POST['country-code'];
}
  
if ( !isset( $stateCode ) ) {
  $stateCode = $_POST['state-code'];
}

if ( !isset( $zipCode ) ) {
  $zipCode = $_POST['zip'];
}

if ( !isset( $items ) ) {
  $items = $_POST['items'];
}
// get_query_var( $var );

// var_dump( $_POST );

echo json_encode( getPrintfulShippingRates( $countryCode, $stateCode, $zipCode, $items ) );
?>