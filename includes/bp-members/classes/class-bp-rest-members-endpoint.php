<?php
/**
 * BP REST: BP_REST_Members_Endpoint class
 *
 * @package BuddyPress
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * BuddyPress Members endpoints.
 *
 * @since 0.1.0
 */
class BP_REST_Members_Endpoint extends WP_REST_Users_Controller {

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->namespace = bp_rest_namespace() . '/' . bp_rest_version();
		$this->rest_base = 'members';
	}

	/**
	 * Retrieve users.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$args = array(
			'type'           => $request['type'],
			'user_id'        => $request['user_id'],
			'user_ids'       => $request['user_ids'],
			'xprofile_query' => $request['xprofile'],
			'exclude'        => $request['exclude'],
			'member_type'    => $request['member_type'],
			'search_terms'   => $request['search'],
			'per_page'       => $request['per_page'],
			'page'           => $request['page'],
		);

		if ( empty( $request['user_ids'] ) ) {
			$args['user_ids'] = false;
		}

		if ( empty( $request['exclude'] ) ) {
			$args['exclude'] = false;
		}

		if ( empty( $request['xprofile'] ) ) {
			$args['xprofile_query'] = false;
		}

		if ( empty( $request['member_type'] ) ) {
			$args['member_type'] = '';
		}

		/**
		 * Filter the query arguments for the request.
		 *
		 * @since 0.1.0
		 *
		 * @param array           $args    Key value array of query var to query value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		$args = apply_filters( 'bp_rest_members_get_items_query_args', $args, $request );

		// Actually, query it.
		$members = new BP_User_Query( $args );

		$retval = array();
		foreach ( array_values( $members->results ) as $member ) {
			$retval[] = $this->prepare_response_for_collection(
				$this->prepare_item_for_response( $member, $request )
			);
		}

		$response = rest_ensure_response( $retval );
		$response = bp_rest_response_add_total_headers( $response, $members->total_users, $args['per_page'] );

		/**
		 * Fires after a list of members is fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param array            $members  Fetched members.
		 * @param WP_REST_Response $response The response data.
		 * @param WP_REST_Request  $request  The request sent to the API.
		 */
		do_action( 'bp_rest_members_get_items', $members, $response, $request );

		return $response;
	}

	/**
	 * Checks if a given request has access to get all users.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return boolean
	 */
	public function get_items_permissions_check( $request ) {
		return true;
	}

	/**
	 * Checks if a given request has access to read a user.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return boolean|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		$user = bp_rest_get_user( $request['id'] );

		if ( ! $user ) {
			return new WP_Error( 'bp_rest_member_invalid_id',
				__( 'Invalid member id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( get_current_user_id() === $user->ID ) {
			return true;
		}

		if ( 'edit' === $request['context'] && ! current_user_can( 'list_users' ) ) {
			return new WP_Error( 'bp_rest_member_cannot_view',
				__( 'Sorry, you are not allowed to list users.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		return true;
	}

	/**
	 * Checks if a given request has access create members.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return boolean|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		if ( ! current_user_can( 'bp_moderate' ) ) {
			return new WP_Error( 'bp_rest_member_cannot_create',
				__( 'Sorry, you are not allowed to create new members.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		return true;
	}

	/**
	 * Check if a given request has access to update a member.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return boolean|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		$user = bp_rest_get_user( $request['id'] );

		if ( ! $user ) {
			return new WP_Error( 'bp_rest_member_invalid_id',
				__( 'Invalid member id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( ! $this->can_manage_member( $user ) ) {
			return new WP_Error( 'bp_rest_member_cannot_update',
				__( 'Sorry, you are not allowed to update this member.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		return true;
	}

	/**
	 * Check if a given request has access to delete a member.
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		$user = bp_rest_get_user( $request['id'] );

		if ( ! $user ) {
			return new WP_Error( 'bp_rest_member_invalid_id',
				__( 'Invalid member id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		if ( ! $this->can_manage_member( $user ) ) {
			return new WP_Error( 'bp_rest_member_cannot_delete',
				__( 'Sorry, you are not allowed to delete this member.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		return true;
	}

	/**
	 * Prepares a single user output for response.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_User         $user    User object.
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $user, $request ) {

		$data    = $this->user_data( $user );
		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';

		if ( 'edit' === $context ) {
			$data['roles']              = array_values( $user->roles );
			$data['capabilities']       = (object) $user->allcaps;
			$data['extra_capabilities'] = (object) $user->caps;
		}

		$data = $this->add_additional_fields_to_object( $data, $request );
		$data = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $user ) );

		/**
		 * Filters user data returned from the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_REST_Response $response The response object.
		 * @param WP_User          $user     WP_User object.
		 * @param WP_REST_Request  $request  The request object.
		 */
		return apply_filters( 'bp_rest_member_prepare_user', $response, $user, $request );
	}

	/**
	 * Method to facilitate fetching of user data.
	 *
	 * This was abstracted to be used in other BuddyPress endpoints.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_User $user User object.
	 * @return array
	 */
	public function user_data( $user ) {
		$data = array(
			'id'                 => $user->ID,
			'name'               => $user->display_name,
			'email'              => $user->user_email,
			'user_login'         => $user->user_login,
			'link'               => bp_core_get_user_domain( $user->ID, $user->user_nicename, $user->user_login ),
			'registered_date'    => bp_rest_prepare_date_response( $user->user_registered ),
			'member_types'       => bp_get_member_type( $user->ID, false ),
			'roles'              => array(),
			'capabilities'       => array(),
			'extra_capabilities' => array(),
			'xprofile'           => $this->xprofile_data( $user->ID ),
		);

		// Avatars.
		$data['avatar_urls'] = array(
			'full' => bp_core_fetch_avatar( array(
				'item_id' => $user->ID,
				'html'    => false,
				'type'    => 'full',
			) ),
			'thumb' => bp_core_fetch_avatar( array(
				'item_id' => $user->ID,
				'html'    => false,
			) ),
		);

		// Fallback.
		if ( false === $data['member_types'] ) {
			$data['member_types'] = array();
		}

		return $data;
	}

	/**
	 * Prepares a single user for creation or update.
	 *
	 * @todo Improve sanitization and schema verification.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return object $prepared_user User object.
	 */
	protected function prepare_item_for_database( $request ) {
		$prepared_user = parent::prepare_item_for_database( $request );

		// The parent class uses username instead of user_login.
		if ( ! isset( $prepared_user->user_login ) && isset( $request['user_login'] ) ) {
			$prepared_user->user_login = $request['user_login'];
		}

		/**
		 * Filters an user object before it is inserted or updated via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param stdClass        $prepared_user An object prepared for inserting or updating the database.
		 * @param WP_REST_Request $request       Request object.
		 */
		return apply_filters( 'bp_rest_member_pre_insert_value', $prepared_user, $request );
	}

	/**
	 * Get XProfile info from the user.
	 *
	 * @since 0.1.0
	 *
	 * @param  int $user_id User ID.
	 * @return array XProfile info.
	 */
	protected function xprofile_data( $user_id ) {
		$data = array();

		// Get XProfile groups, only if the component is active.
		if ( bp_is_active( 'xprofile' ) ) {
			$groups = bp_xprofile_get_groups( array(
				'user_id'          => $user_id,
				'fetch_fields'     => true,
				'fetch_field_data' => true,
			) );

			foreach ( $groups as $group ) {
				$data['groups'][ $group->id ] = array(
					'name' => $group->name,
				);

				foreach ( $group->fields as $item ) {
					$data['groups'][ $group->id ]['fields'][ $item->id ] = array(
						'name'  => $item->name,
						'value' => maybe_unserialize( $item->data->value ),
					);
				}
			}
		} else {
			$data = array( __( 'No extended profile data available as the component is inactive', 'buddypress' ) );
		}

		return $data;
	}

	/**
	 * Can user manage (delete/update) a member?
	 *
	 * @since 0.1.0
	 *
	 * @param  WP_User $user User object.
	 * @return bool
	 */
	protected function can_manage_member( $user ) {

		if ( current_user_can( 'bp_moderate' ) ) {
			return true;
		}

		if ( current_user_can( 'delete_user', $user->ID ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get the plugin schema, conforming to JSON Schema.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'member',
			'type'       => 'object',
			'properties' => array(
				'id'          => array(
					'description' => __( 'Unique identifier for the member.', 'buddypress' ),
					'type'        => 'integer',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),

				'name'        => array(
					'description' => __( 'Display name for the member.', 'buddypress' ),
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),

				'email'       => array(
					'description' => __( 'The email address for the member.', 'buddypress' ),
					'type'        => 'string',
					'format'      => 'email',
					'context'     => array( 'embed', 'view', 'edit' ),
					'required'    => true,
				),

				'link'        => array(
					'description' => __( 'Profile URL of the member.', 'buddypress' ),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),

				'user_login'        => array(
					'description' => __( 'An alphanumeric identifier for the member.', 'buddypress' ),
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
					'required'    => true,
					'arg_options' => array(
						'sanitize_callback' => array( $this, 'check_username' ),
					),
				),

				'member_types' => array(
					'description' => __( 'Member types associated with the member.', 'buddypress' ),
					'type'        => 'object',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),

				'registered_date' => array(
					'description' => __( 'Registration date for the member.', 'buddypress' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'edit' ),
					'readonly'    => true,
				),

				'password'        => array(
					'description' => __( 'Password for the member (never included).', 'buddypress' ),
					'type'        => 'string',
					'context'     => array(), // Password is never displayed.
					'required'    => true,
					'arg_options' => array(
						'sanitize_callback' => array( $this, 'check_user_password' ),
					),
				),

				'roles'           => array(
					'description' => __( 'Roles assigned to the member.', 'buddypress' ),
					'type'        => 'array',
					'context'     => array( 'edit' ),
					'items'       => array(
						'type'    => 'string',
					),
				),

				'capabilities'    => array(
					'description' => __( 'All capabilities assigned to the user.', 'buddypress' ),
					'type'        => 'object',
					'context'     => array( 'edit' ),
					'readonly'    => true,
				),

				'extra_capabilities' => array(
					'description' => __( 'Any extra capabilities assigned to the user.', 'buddypress' ),
					'type'        => 'object',
					'context'     => array( 'edit' ),
					'readonly'    => true,
				),

				'xprofile' => array(
					'description' => __( 'Member XProfile groups and its fields.', 'buddypress' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
			),
		);

		// Avatars.
		if ( true === buddypress()->avatar->show_avatars ) {
			$avatar_properties = array();

			$avatar_properties['full'] = array(
				/* translators: Full image size for the member Avatar */
				'description' => sprintf( __( 'Avatar URL with full image size (%1$d x %2$d pixels).', 'buddypress' ), number_format_i18n( bp_core_avatar_full_width() ), number_format_i18n( bp_core_avatar_full_height() ) ),
				'type'        => 'string',
				'format'      => 'uri',
				'context'     => array( 'embed', 'view', 'edit' ),
			);

			$avatar_properties['thumb'] = array(
				/* translators: Thumb imaze size for the member Avatar */
				'description' => sprintf( __( 'Avatar URL with thumb image size (%1$d x %2$d pixels).', 'buddypress' ), number_format_i18n( bp_core_avatar_thumb_width() ), number_format_i18n( bp_core_avatar_thumb_height() ) ),
				'type'        => 'string',
				'format'      => 'uri',
				'context'     => array( 'embed', 'view', 'edit' ),
			);

			$schema['properties']['avatar_urls'] = array(
				'description' => __( 'Avatar URLs for the member.', 'buddypress' ),
				'type'        => 'object',
				'context'     => array( 'embed', 'view', 'edit' ),
				'readonly'    => true,
				'properties'  => $avatar_properties,
			);
		}

		return $this->add_additional_fields_schema( $schema );
	}

	/**
	 * Get the query params for collections.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params                       = parent::get_collection_params();
		$params['context']['default'] = 'view';

		$params['type'] = array(
			'description'       => __( 'Shorthand for certain orderby/order combinations.', 'buddypress' ),
			'default'           => 'newest',
			'type'              => 'string',
			'enum'              => array( 'active', 'newest', 'alphabetical', 'random', 'online', 'popular' ),
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['user_id'] = array(
			'description'       => __( 'Limit results to friends of a user.', 'buddypress' ),
			'default'           => 0,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['user_ids'] = array(
			'description'       => __( 'Pass IDs of users to limit result set.', 'buddypress' ),
			'default'           => array(),
			'type'              => 'array()',
			'sanitize_callback' => 'wp_parse_id_list',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['exclude'] = array(
			'description'       => __( 'Ensure result set excludes specific IDs.', 'buddypress' ),
			'default'           => array(),
			'type'              => 'array',
			'sanitize_callback' => 'wp_parse_id_list',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['member_type'] = array(
			'description'       => __( 'Limit results set to certain type(s).', 'buddypress' ),
			'default'           => array(),
			'type'              => 'array',
			'sanitize_callback' => 'bp_rest_sanitize_string_list',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['xprofile'] = array(
			'description'       => __( 'Limit results set to a certain XProfile field.', 'buddypress' ),
			'default'           => '',
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		return $params;
	}
}
