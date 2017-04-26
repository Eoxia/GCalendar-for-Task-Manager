<?php
/**
 * Initialise le client google, lui donne des services accessible(Scopes) avec les infos(clients_secrets) en mode offline.
 *
 * @method init_client
 * @param integer $user_id l'id de l'utilisateur.
 * @return Object the client initialisé
 */
function init_client( $user_id ) {
	$code = get_user_meta( $user_id, 'calendar_token', true );
	$client = new Google_Client();
	$client->setApplicationName( APPLICATION_NAME );
	$client->setScopes( SCOPES ); // les services à acceder.
	$client->setAuthConfig( CLIENT_SECRET_PATH ); // le fichier qui gere les authorisation de l'application(plugin).
	$client->setAccessType( 'offline' );
	$client->setAccessToken( $code );
	return $client;
}
