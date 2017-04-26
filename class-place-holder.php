<?php
/**
 * Plugin Name: Place Holder
 * Description: Task manager add-on, link all your tasks to your google calendar with toto plugin!
 * Version: 0.1
 * Author: Bactery
 * License: GPL2
 *
 * @package theme-name
 */

/**
 * La creation et suppression d'évenement google agenda depuis les action performer sur task manager.
 *
 * @method __construct
 */
class Place_Holder {
	/**
	 * Initialise les notices admin pour activer l'adresse qui est ajouter le champ dans profile utilisateur, puis est relié a task manager pour pouvoir créer et supprimer des evenement sur l'agenda google.
	 *
	 * @method __construct
	 */
	public function __construct() {
		include __DIR__ . '/functions.php';
		add_action( 'admin_notices', array( $this, 'google_authorization' ) );
		add_action( 'show_user_profile', array( $this, 'add_profile_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'add_profile_fields' ) );
		add_action( 'personal_options_update', array( $this, 'update_profile_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'update_profile_fields' ) );
		add_action( 'tm_added_history_time', array( $this, 'create_google_calendar_event' ), 10, 4 );
		add_action( 'tm_deleted_history_time', array( $this, 'delete_google_calendar_event' ) );

	}
	/**
	 * Rajoute un champ (lien google agenda) sur le profile de l'utilisateur
	 *
	 * @method add_profile_fields
	 * @param  string $profileuser on recupère le profile de l'utilisateur en question.
	 */
	public function add_profile_fields( $profileuser ) {
		$gmail_adress = get_user_meta( $profileuser->ID, 'gmail_adress', true );
		?>
		<h3><?php esc_html_e( 'Lien Google Agenda' ); ?></h3>
		<table class="form-table">
			<tr>
				<th><label for="gmail_adress"><?php esc_html_e( 'Adresse Gmail' ); ?></label></th>
				<td><input type="text" name="gmail_adress" id="gmail_adress" value="<?php if ( isset( $gmail_adress ) ) { echo esc_attr( $gmail_adress ); } ?>" class="regular-text" /></td>
			</tr>
		</table>
		<?php
	}
	/**
	 * On met a jour l'email donner dans le profile utilisateur
	 *
	 * @method update_profile_fields
	 * @param integer $user_id l'id de l'utilisateur.
	 * @return void rien.
	 */
	public function update_profile_fields( $user_id ) {
		$auth = get_user_meta( $user_id, 'gmail_auth', true );
		$mail = get_user_meta( $user_id, 'gmail_adress', true );
		if ( isset( $_POST['gmail_adress'] ) ) {
			wp_verify_nonce( $_POST['gmail_adress'] );
			if ( $mail !== $_POST['gmail_adress'] ) {
				update_user_meta( $user_id, 'gmail_auth', 'false' );
				delete_user_meta( $user_id, 'calendar_token' );
			}
			if ( ! empty( $_POST['gmail_adress'] ) ) {
				update_user_meta( $user_id, 'gmail_adress', sanitize_text_field( $_POST['gmail_adress'] ) );
			}
		}
	}
	/**
	 * Permet à l'utilisateur d'autoriser notre plugin d'acceder à google Agenda
	 *
	 * @method google_authorization
	 * @return void rien.
	 */
	public function google_authorization() {
		$current_user = wp_get_current_user();
		$user_token = get_user_meta( $current_user->ID, 'calendar_token', true );
		$auth = get_user_meta( $current_user->ID, 'gmail_auth', true );
		$client = init_client();
		$redirect_uri = admin_url(); // L'url sur laquel l'utilisateur va être rediriger apres avoir autoriser l'application.
		$client->setRedirectUri( $redirect_uri );
		if ( isset( $_GET['code'] ) ) { // le code recuperer apres la redirection
			   $client->authenticate( $_GET['code'] ); // cette fonction est une sorte de connection ou le code est le mot de passe. on s'en sert pour recuperer un accesstoken qui servira à recuperer des 'refresh token', donc on le stock dans la base données.
			   $token = $client->getAccessToken();
			   $calendar_token = wp_json_encode( $token );
			   update_user_meta( $current_user->ID, 'gmail_auth', 'true' ); // on change l'autorisation en "true" dans la base de données
			   update_user_meta( $current_user->ID, 'calendar_token', $calendar_token ); // on met le token pour pouvoir recuprer des refresh tokens sur demande.
			?>
			<div class="notice notice-success is-dismissible">
				<p>Congratulation, your mail adress has been succesfully authorized. Your tasks will now get added to your google calendar, try it out <a href=<?php echo esc_html( admin_url() . 'admin.php?page=wpeomtm-dashboard' ); ?>>here</a></p>
			</div>
			<?php
		} // End if().
		if ( empty( $user_token ) && ! isset( $_GET['code'] ) ) { // Si le token n'est pas deja.
			if ( 'false' === $auth ) {
				$auth_url = $client->createAuthUrl();
			?>
			<div class="notice notice-error is-dismissible">
				<p>You haven't authorized your mail adress yet. Authorize it <a href=<?php echo esc_html( "$auth_url" ); ?>>here</a></p>
			</div>
			<?php
			}
	    }
	}
	/**
	 * Ajoute un évenement sur google agenda, si l'utilisateur a donner les droits d'authorisation. On récupere l'objet 'task' pour pouvoir trouver les ID des attendants à la tache.
	 *
	 * @method create_google_calendar_event
	 * @param  Object   $history_time_created   objet de task_manager, pour recupérer les infos sur la base de donnée.
	 * @param  integer  $task_id              id des attendants s'occupant de la tache.
	 * @param  dateTime $due_date             date à laquel la tache est prévu.
	 * @param  integer  $estimated_time       le temps donnée par l'utilisateur en minutes.
	 * @return void                                            rien
	 */
	public function create_google_calendar_event( $history_time_created, $task_id, $due_date, $estimated_time ) {
		// add the google calendar events.
		$task = \task_manager\Task_Class::g()->get( array(
			'include' => array( $task_id ),
		), true );
		 $task_attendees = $task->user_info['affected_id'];
		 $current_user = wp_get_current_user();
		 $auth = get_user_meta( $current_user->ID, 'gmail_auth', 'true' );
		if ( 'true' === $auth ) {
			$code = get_user_meta( $current_user->ID, 'calendar_token', 'true' );
			$user_mail = get_user_meta( $current_user->ID, 'gmail_adress', 'true' );
			$client = init_client();
			$date_start = DateTime::createFromFormat( 'd/m/Y H:i:s', $due_date . ' 00:00:00' ); // créer les dates, en partant de la variable $due_date puis initialise le temps à 00:00:00 (heures, minutes, secondes).
			$date_start->setTime( 7, 0 ); // Change l'heure a 07:00:00 (? cela permet d'avoir les taches qui commence a 9h ?).
			$date_end = DateTime::createFromFormat( 'd/m/Y H:i:s', $due_date . ' 00:00:00' );
			$date_end->setTime( 7, 0 );
			$date_end->add( new DateInterval( 'PT' . $estimated_time . 'M' ) ); // ajoute les minutes prévu a la date de fin.
			$description = admin_url() . 'admin.php?page=wpeomtm-dashboard&term=' . $task_id; // crée une url pour que l'utilisateur puisse retrouver sa tache depuis son agenda.
			$service = new Google_Service_Calendar( $client ); // initialise le service depuis l'object client créer plûtot
			/* création d'un compteur (0) pour pouvoir compter les attendants qui on authoriser leur g-mail,
			de cette façon on n'aura pas des paramettre vide dans la variable data. */
			$count = 0;
			$data = array();
			foreach ( $task_attendees as $id ) {
				$check_mail = get_user_meta( $id, 'gmail_adress', true );
				if ( ! empty( $check_mail ) ) {
							$mail = $check_mail;
							$count++;
							$data[] = $mail;
				} // End if().
			}
			/* création de l'évenement on ajoute tout les élements en array sauf les attendants.
			 les reminders peuvent être enlever si ils ne sont pas nécessaire. */
			$event = new Google_Service_Calendar_Event(array(
				'summary' => "#$task_id    $task->title", // task
				'location' => '', // optional?
				'description' => '<a href="' . $description . '">Check out your task here</a>', // point
				'start' => array(								// task beginning!
				  'dateTime' => $date_start->format( DateTime::ISO8601 ),
				),
				'end' => array(								// task planned ending!
				  'dateTime' => $date_end->format( DateTime::ISO8601 ),
				),
				'reminders' => array(							// optional?
				  'useDefault' => false,
				  'overrides' => array(
					array(
						   'method' => 'email',
						   'minutes' => 24 * 60,
						 ),
					array(
						   'method' => 'popup',
						   'minutes' => 1,
						 ),
				  ),
				),
			));
			/* on lance une boucle qui va de 0 au nombre d'adresses mail des attendants,
			puis on crée une variable de variable qui contient le numéro de l'attendant ($i),
			enfin on utilise la fonction setEmail de la classe objet Google_Service_Calendar pour initialiser chaque email que l'on ajoute en variable */
			$attendees = array();
			for ( $i = 0 ; $i < $count; $i++ ) {
				${'attendees_n' . $i} = new Google_Service_Calendar_EventAttendee();
				${'attendees_n' . $i}->setEmail( $data[ $i ] );
				$attendees[] = ${'attendees_n' . $i};
			}
				$event->attendees = $attendees;
				$calendar_id = 'primary'; // We then display that the event was succesfully created and give a link for the user to see. Only used for testing but can be implemented in the plugin!
				$event = $service->events->insert( $calendar_id, $event );
				$event_id = $event->getId();
				$history_time_created->google_event_id = $event_id;
				\task_manager\History_Time_Class::g()->update( $history_time_created ); // On envoie l'id de l'evenement sur history time pour qu'ils soit entrer dans la base de données.
		} // End if().

	}
	/**
	 * Supprime un event sur google agenda au momenet ou l'utilisateur supprime la nouvelle date voulu et le temps éstimé.
	 *
	 * @method delete_google_calendar_event
	 * @param  integer $history_time_id l'id qui nouas permet d'initialiser l'objet history time.
	 * @return void rien.
	 */
	public function delete_google_calendar_event( $history_time_id ) {
		$history_time = \task_manager\History_Time_Class::g()->get( array(
			'id' => $history_time_id,
		), true ); // récupere l'objet history_time depuis la classe qui existe dans le plugin task manager.
		$event_id = $history_time->google_event_id; // l'id de l'evenement qu'on a stocker dans la base de données de history_time.
		$current_user = wp_get_current_user();
		$user_mail = get_user_meta( $current_user->ID, 'gmail_adress', 'true' );
		$client = init_client();
		$service = new Google_Service_Calendar( $client );
		$service->events->delete( 'primary', $event_id ); // supprime les évenement.
	}
}

new Place_Holder();