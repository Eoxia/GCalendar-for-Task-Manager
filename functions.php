<?php
/**
 * Initialise le client google, lui donne des services accessible(Scopes) avec les infos(clients_secrets) en mode offline.
 *
 * @method init_client
 * @return Object the client initialisé
 */
function init_client() {
	require_once __DIR__ . '/vendor/autoload.php';
	define( 'APPLICATION_NAME', 'Google Calendar API PHP Quickstart' );
	define( 'CLIENT_SECRET_PATH', __DIR__ . '/client_secret.json' );
	// If modifying these scopes, delete your previously saved credentials
	// at ~/.credentials/calendar-php-quickstart.json!
	define( 'SCOPES', implode(' ', array( // on utilise que le service Calendrier la google api.
		Google_Service_Calendar::CALENDAR,
		)
	));
	if ( empty( $current_user ) ) {
		$current_user = wp_get_current_user();
	}
	$code = get_user_meta( $current_user->ID, 'calendar_token', true );
	$client = new Google_Client();
	$client->setApplicationName( APPLICATION_NAME );
	$client->setScopes( SCOPES ); // les services à acceder.
	$client->setAuthConfig( CLIENT_SECRET_PATH ); // le fichier qui gere les authorisation de l'application(plugin).
	$client->setAccessType( 'offline' );
	$client->setAccessToken( $code );
	return $client;
}
