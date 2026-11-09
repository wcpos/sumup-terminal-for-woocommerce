<?php
// phpcs:ignoreFile
require_once __DIR__ . '/stubs/wcpos-pro-server.php';
require_once __DIR__ . '/stubs/sumup-server.php';
list( $provider, $profile, $readers ) = server_fixture();
$readers->result = array(
	array( 'id' => 'one', 'name' => 'Till', 'status' => 'paired', 'device' => array( 'model' => 'solo' ) ),
	array( 'id' => 'two', 'name' => '', 'status' => 'processing', 'device' => array( 'identifier' => 'SERIAL', 'model' => 'solo' ) ),
	array( 'id' => 'three', 'status' => 'unknown', 'device' => array( 'model' => 'solo' ) ),
	array( 'id' => 'four', 'status' => 'expired', 'device' => array( 'model' => 'solo' ) ),
	array( 'id' => 'five', 'status' => 'paired', 'device' => array( 'model' => 'virtual-solo' ) ),
);
expect( array(
	array( 'id' => 'one', 'label' => 'Till', 'status' => 'online' ),
	array( 'id' => 'two', 'label' => 'SERIAL', 'status' => 'offline' ),
	array( 'id' => 'three', 'label' => 'three', 'status' => 'offline' ),
	array( 'id' => 'five', 'label' => 'five', 'status' => 'online' ),
) === $provider->list_readers(), 'reader discovery projection' );
$readers->result = array(); expect( array() === $provider->list_readers(), 'empty discovery' );
foreach ( array( false, new WP_Error( 'timeout', 'Timed out' ), new RuntimeException( 'bad' ) ) as $readers->result ) {
	provider_error_expect( $provider->list_readers(), is_wp_error( $readers->result ) ? 'timeout' : 'sumup_api_error' );
}
echo "server-list-readers ok\n";
