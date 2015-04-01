<?php
/**
 * Contains the class that models a Donor in Charitable.
 *
 * @version		1.0.0
 * @package		Charitable/Classes/Charitable_Donor
 * @author 		Eric Daams
 * @copyright 	Copyright (c) 2014, Studio 164a
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'Charitable_Donor' ) ) : 

/**
 * Charitable_Donor
 *
 * @since 		1.0.0
 */
class Charitable_Donor extends WP_User {

	/**
	 * Create class object.
	 * 
	 * @param 	int|string|stdClass|WP_User $id 		User's ID, a WP_User object, or a user object from the DB.
	 * @param 	string 						$name 		Optional. User's username
	 * @param 	int 						$blog_id 	Optional Blog ID, defaults to current blog.
	 * @return 	void
	 * @access 	public
	 * @since	1.0.0
	 */
	public function __construct( $id = 0, $name = '', $blog_id = '' ) {
		parent::__construct( $id, $name, $blog_id );
	}

	/**
	 * Returns whether the user is logged in. 
	 *
	 * @return 	boolean
	 * @access  public
	 * @since 	1.0.0
	 */
	public function is_logged_in() {
		return 0 !== $this->ID;
	}

	/**
	 * Returns whether the user has ever made a donation. 
	 *
	 * @return 	boolean
	 * @access  public
	 * @since 	1.0.0
	 */
	public function is_donor() {
		return $this->has_cap( 'donor' );
	}

	/**
	 * Returns the display name of the user.
	 *
	 * @return 	string
	 * @access  public
	 * @since 	1.0.0
	 */
	public function get_name() {
		return $this->display_name;
	}

	/**
	 * Return an array of fields used for the address. 
	 *
	 * @return 	array
	 * @access  public
	 * @since 	1.0.0
	 */
	public function get_address_fields() {
		return apply_filters( 'charitable_donor_address_fields', array(
			'donor_address', 
			'donor_address_2', 
			'donor_city', 
			'donor_state', 
			'donor_postcode', 
			'donor_country'
		) );
	}

	/**
	 * Returns printable address of donor. 
	 *
	 * @return 	string
	 * @access  public
	 * @since 	1.0.0
	 */
	public function get_address() {
	
		$address_fields = apply_filters( 'charitable_donor_address_fields', array(
			'first_name'    => $this->get( 'first_name' ),
			'last_name'     => $this->get( 'last_name' ),
			'company'       => $this->get( 'donor_company' ),
			'address'    	=> $this->get( 'donor_address' ),
			'address_2'     => $this->get( 'donor_address_2' ),
			'city'          => $this->get( 'donor_city' ),
			'state'         => $this->get( 'donor_state' ),
			'postcode'      => $this->get( 'donor_postcode' ),
			'country'       => $this->get( 'donor_country' )
		), $this );

		return charitable_get_location_helper()->get_formatted_address( $address_fields );
	}

	/**
	 * Create a new donor. 
	 *
	 * @param 	array 			$submitted
	 * @return 	int|false
	 * @static
	 * @access  public
	 * @since 	1.0.0
	 */
	public static function create( $submitted ) {
		$user_data = array( 'role' => 'donor' );

		/**
		 * Set the user's email address.
		 */
		if ( isset( $submitted['user_email'] ) ) {
			$user_data['user_email'] = $submitted['user_email'];
			unset( $submitted['user_email'] );
		}
		elseif ( isset( $submitted['email'] ) ) {
			$user_data['user_email'] = $submitted['email'];
			unset( $submitted['email'] );
		}
		else {
			/**
			 * @todo 	Set error message. 
			 */
			return false;
		}

		$user = get_user_by( 'email', $user_data['user_email'] );

		/**
		 * This is a completely new user. 
		 */
		if ( false === $user ) {
			/**
			 * Set their password, if provided. 
			 */
			if ( isset( $submitted['user_pass'] ) ) {
				$user_data['user_pass'] = $submitted['user_pass'];
				unset( $user_data['user_pass'] );
			}
			else {
				$user_data['user_pass'] = NULL;
			}		

			/**
			 * Set their username, if provided. Otherwise it's set to their email address.
			 */
			if ( isset( $submitted['user_login'] ) ) {
				$user_data['user_login'] = $submitted['user_login'];
				unset( $user_data['username'] );		
			}
			else {
				$user_data['user_login'] = $user_data['user_email'];
			}

			/**
			 * Set their first name and last name, if provided.
			 */
			if ( isset( $submitted['first_name'] ) ) {
				$user_data['first_name'] = $submitted['first_name'];
				unset( $submitted['first_name'] );
			}

			if ( isset( $submitted['last_name'] ) ) {
				$user_data['last_name'] = $submitted['last_name'];
				unset( $submitted['last_name'] );
			}

			$user_id = wp_insert_user( $user_data );

			if ( is_wp_error( $user_id ) ) {

			}
		}
		/**
		 * The user already exists, so just make them a donor.
		 */
		else {
			self::create_from_user( $user );
			$user_id = $user->ID;
		}		

		/**
		 * Finally, loop over all the other provided values and save them as user meta fields. 
		 */
		foreach ( $submitted as $key => $value ) {
			update_user_meta( $user_id, 'donor_' . $key, $value );
		}

		return $user_id;
	}

	/**
	 * Make an existing user a donor. 
	 *
	 * @param 	WP_User 			$user
	 * @return 	void
	 * @static
	 * @access  public	 
	 * @since 	1.0.0
	 */
	public static function create_from_user( WP_User $user ) {				
		if ( ! $user->has_cap( 'donor' ) ) {
			$user->add_role( 'donor' );
		}
	}

	/**
	 * Return all donations made by donor. 
	 *
	 * @return 	Object
	 * @access  public
	 * @since 	1.0.0
	 */
	public function get_donations() {
		return charitable()->get_db_table( 'campaign_donations' )->get_donations_by_donor( $this->ID );
	}

	/**
	 * Return the number of donations made by the donor. 
	 *
	 * @param 	boolean 	$count_distinct 		If true, will not include extra donations made to the same campaign. 
	 * @return 	int
	 * @access  public
	 * @since 	1.0.0
	 */
	public function get_donation_count( $count_distinct = false ) {
		return charitable()->get_db_table( 'campaign_donations' )->count_donations_by_donor( $this->ID, $count_distinct );
	}

	/**
	 * Return the total amount donated by the donor.
	 *
	 * @return 	float
	 * @access  public
	 * @since 	1.0.0
	 */
	public function get_total_donated() {
		return (float) charitable()->get_db_table( 'campaign_donations' )->get_total_donated_by_donor( $this->ID );
	}

	/**
	 * Returns the user's avatar. 
	 *
	 * By default, this will return the gravatar, but it can 
	 * be extended to add support for locally hosted avatars.
	 *
	 * @return 	string
	 * @access 	public
	 * @since 	1.0.0
	 */
	public function get_avatar( $size = 100 ) {

		$avatar = apply_filters( 'charitable_user_avatar', false, $this );

		if ( false === $avatar ) {

			$avatar = get_avatar( $this->ID, $size );

		}
			
		return $avatar;
	}

	/**
	 * Returns the user's donation and campaign creation activity. 
	 *
	 * @see 	WP_Query 	
	 * @param 	array 		$args 		Optional. Any arguments accepted by WP_Query.
	 * @return 	WP_Query 
	 * @access  public
	 * @since 	1.0.0
	 */
	public function get_activity( $args = array() ) {
		$defaults = array(
			'author'		=> $this->ID,
			'post_status'	=> array( 'charitable-completed', 'charitable-preapproved', 'publish' ),
			'post_type' 	=> array( 'donation', 'campaign' ), 
			'order'			=> 'DESC', 
			'orderby'		=> 'date'
		);

		$args = wp_parse_args( $args, $defaults );

		$args = apply_filters( 'charitable_donor_activity_args', $args, $this );

		return new WP_Query( $args );
	}
}

endif; // End class_exists check