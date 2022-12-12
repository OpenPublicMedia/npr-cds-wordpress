<?php

/**
 * @file
 * Defines basic OOP containers for NPRML.
 */

/**
 * Defines a class for NPRML creation/transmission and retrieval/parsing, for any PHP-based system.
 */
class NPRAPI {

	// HTTP status code = OK
	const NPRAPI_STATUS_OK = 200;

	// Default URL for pulling stories
	const NPRAPI_PULL_URL = 'https://api.npr.org';
	const NPR_CDS_VERSION = 'v1';

	/**
	 * Initializes an NPRML object.
	 */
	function __construct() {
		$this->request = new stdClass;
		$this->request->method = NULL;
		$this->request->params = NULL;
		$this->request->data = NULL;
		$this->request->path = NULL;
		$this->request->base = NULL;
		$this->request->request_url = NULL;


		$this->response = new stdClass;
		$this->response->id = NULL;
		$this->response->code = NULL;
	}

	function request() {

	}

	function prepare_request() {

	}

	/**
	 * This function will send the push request to the NPR API to add/update a story.
	 *
	 * @see NPRAPI::send_request()
	 *
	 * @param string $nprml
	 * @param int $ID
	 */
	function send_request( $nprml, $ID ) {

	}

	function parse_response() {
		$json = json_decode( $this->response->data, TRUE );
		if ( !empty( $json->resources[0] ) ) {
			$id = $json['resources'][0]['id'];
		}
		$this->response->id = $id ? $id : NULL;
	}

	function flatten() {

	}

	/**
	 * Create NPRML from wordpress post.
	 *
	 * @param object $object
	 * @return string n NPRML string.
	 */
	function create_NPRML( $object ) {
		return '';
	}

	/**
	 * Parses object. Turns raw XML(NPRML) into various object properties.
	 */
	function parse() {
		if ( !empty( $this->json ) ) {
			$json = $this->json;
		} else {
			$this->notices[] = 'No JSON to parse.';
			return;
		}

		$object = json_decode( $json, false );

		if ( !empty( $object->resources ) ) {
			foreach ( $object->resources as $story ) {
				$this->stories[] = $story;
			}
			// if the query didn't have a sort parameter, reverse the order so that we end up with
			// stories in reverse-chron order.
			// there are no params and 'sort=' is not in the URL
			if ( empty( $this->request->params ) && !stristr( $this->request->request_url, 'sort=' ) ) {
				$this->stories = array_reverse( $this->stories );
			}
			// there are params, and sort is not one of them
			if ( !empty( $this->request->params ) && !array_key_exists( 'sort', $this->request->params ) ) {
				$this->stories = array_reverse( $this->stories );
			}
		}
	}

	/**
	 * Generates basic report of NPRML object.
	 *
	 * @return array
	 *   Various messages (strings) .
	 */
	function report() {
		$msg = [];
		$params = '';
		if ( isset( $this->request->params ) ) {
			foreach ( $this->request->params as $k => $v ) {
				$params .= " [$k => $v]";
			}
			$msg[] =  'Request params were: ' . $params;
		} else {
			$msg[] = 'Request had no parameters.';
		}

		if ( $this->response->code == self::NPRAPI_STATUS_OK ) {
			$msg[] = 'Response code was ' . $this->response->code . '.';
			if ( isset( $this->stories ) ) {
				$msg[] = ' Request returned ' . count( $this->stories ) . ' stories.';
			}
		} elseif ( $this->response->code != self::NPRAPI_STATUS_OK ) {
			$msg[] = 'Return code was ' . $this->response->code . '.';
		} else {
			$msg[] = 'No info available.';
		}
		return $msg;
	}
}