<?php
if ( !empty( $_GET ) && ( !isset( $noHeaders ) || !$noHeaders ) ) {
  header( 'Content-Type: application/json' );
}

if ( !isset ( $printfulProductID ) ) {
  $printfulProductID = $_GET['productID'];
}
  
if ( !isset( $size ) ) {
  $size = $_GET['size'];
}

if ( !isset( $color ) ) {
  $color = $_GET['color'];
}
// get_query_var( $var );

// wp_send_json(
$variant = getPrintfulVariantFromSpecs( $printfulProductID, $size, $color );

echo json_encode( $variant );
?>