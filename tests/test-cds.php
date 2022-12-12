<?php
	function npr_asset_id ( $href ) {
		$href_xp = explode( '/', $href );
		return end( $href_xp );
	}
	function npr_profile_extract ( $profiles ) {
		$output = [];
		foreach ( $profiles as $p ) {
			$p_xp = explode( '/', $p->href );
			$output[] = end( $p_xp );
		}
		return $output;
	}
	function extract_asset_profile ( $asset ) {
		$output = '';
		foreach ( $asset->profiles as $profile ) {
			if ( !empty( $profile->rels ) ) {
				if ( in_array( 'type', $profile->rels ) ) {
					$output = npr_asset_id( $profile->href );
				}
			}
		}
		return $output;
	}
	function npr_remote_get ( $id ) {
		// $token = 'c976e1a9-f408-4641-a665-0d528ae45a6b';
		// $url = 'https://stage-content-v1.api.nprinfra.org/v1/documents/' . $id;
		// $options = [
		// 	'headers' => [
		// 		"Authorization" => "Bearer " . $token
		// 	]
		// ];
		// $remote = wp_remote_get( $url, $options );
		// if ( is_wp_error( $remote ) ) {
		// 	return false;
		// }
		// $body = wp_remote_retrieve_body( $remote );
		$curl = curl_init();

		curl_setopt_array( $curl, [
			CURLOPT_URL => 'https://stage-content-v1.api.nprinfra.org/v1/documents/' . $id,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'GET',
			CURLOPT_HTTPHEADER => [
				'Authorization: Bearer c976e1a9-f408-4641-a665-0d528ae45a6b'
			],
		]);

		$response = curl_exec( $curl );

		curl_close( $curl );
		return json_decode( $response, false );
	}
	function npr_url_extract ( $href ) {
		$id = npr_asset_id( $href );
		$json = npr_remote_get( $id );
		$output = '';
		$j = $json->resources[0];
		foreach ( $j->webPages as $web ) {
			if ( in_array( 'canonical', $web->rels ) ) {
				$output = $web->href;
			}
		}
		return $output;
	}

	$json = npr_remote_get( '1075781724' );

	$j = $json->resources[0];
	$cards = [];
	foreach ( $j->assets as $k => $v ) {
		$a_type = extract_asset_profile( $v );
		if ( $a_type == 'card' ) {
			$cards[ $k ] = $v;
			$cards[ $k ]->children = [];
		}
	}
	foreach ( $cards as $card ) {
		if ( !empty( $card->layout ) ) {
			foreach ( $card->layout as $clayout ) {
				$child_id = npr_asset_id( $clayout->href );
				$child_asset = $j->assets->{ $child_id };
				$card->children[ $child_id ] = $child_asset;
				unset( $cards[ $child_id ] );
				$card->children[ $child_id ]->children = [];
				if ( !empty( $child_asset->layout ) ) {
					foreach ( $child_asset->layout as $cclayout ) {
						$cchild_id = npr_asset_id( $cclayout->href );
						$cchild_asset = $j->assets->{ $cchild_id };
						$card->children[ $child_id ]->children[ $cchild_id ] = $cchild_asset;
					}
				}
			}
		}
	}

	foreach ( $cards as $card ) {
		$type = npr_profile_extract( $card->profiles );
		echo "Parent: " . implode( " / ", $type ) . PHP_EOL;
		foreach ( $card->children as $child ) {
			$ctype = npr_profile_extract( $child->profiles );
			echo "\tChild: " . implode( " / ", $ctype ) . PHP_EOL;
			foreach ( $child->children as $cchild ) {
				$cctype = npr_profile_extract( $cchild->profiles );
				echo "\t\tChild: " . implode( " / ", $cctype ) . PHP_EOL;
			}
		}
	}



	die;




	$article = [
		'id' => $j->id,
		'title' => $j->title,
		'brandings' => [],
		'bylines' => [],
		'publish_date' => $j->publishDateTime,
		'body' => '',
		'nprWebsitePath' => $j->nprWebsitePath,
		'excerpt' => $j->teaser,
		'profiles' => npr_profile_extract( $j->profiles ),
		'audio' => []
	];
	if ( in_array( 'renderable', $article['profiles'] ) && in_array( 'publishable', $article['profiles'] ) ) {
		if ( !empty( $j->assets ) ) {
			$assets = $j->assets;
		}
		if ( !empty( $j->layout ) ) {
			foreach( $j->layout as $lo ) {
				$lo_id = npr_asset_id( $lo->href );
				$current = $assets->$lo_id;
				$profiles = npr_profile_extract( $current->profiles );
				if ( in_array( 'text', $profiles ) ) {
					$article['body'] .= '<p>' . $current->text . '</p>';
				} elseif ( in_array( 'image', $profiles ) ) {
					foreach ( $current->enclosures  as $enc ) {
						if ( in_array( 'primary', $enc->rels ) ) {
							$article['body'] .= '<p><a href="' . $enc->href . '">' . $current->caption . '</a></p>';
						}
					}
				} elseif ( in_array( 'promo-card', $profiles ) ) {
					$article['body'] .= '<div class="promo-card"><h3>' . $current->eyebrowText . '</h3><p><a href="' . npr_url_extract( $current->documentLink->href ) . '">' . $current->linkText . '</a></p></div>';
				}
			}
		}
		if ( !empty( $j->bylines ) ) {
			foreach( $j->bylines as $byline ) {
				$byline_id = npr_asset_id( $byline->href );
				$article['bylines'][] = $assets->$byline_id->name;
			}
		}
		if ( !empty( $j->brandings ) ) {
			foreach ( $j->brandings as $branding ) {
				$brand = json_decode( file_get_contents( $branding->href ), false );
				$article['brandings'][] = $brand->brand->displayName;
			}
		}
		if ( !empty( $j->audio ) ) {
			foreach ( $j->audio as $audio ) {
				$audio_id = npr_asset_id( $audio->href );
				if ( in_array( 'primary', $audio->rels ) ) {
					$audio_current = $assets->{ $audio_id };
					if ( $audio_current->isAvailable ) {
						if ( $audio_current->isEmbeddable ) {
							$article['audio'][] = '<p><iframe class="npr-embed-audio" style="width: 100%; height: 235px;" src="' . $audio_current->embeddedPlayerLink->href . '"></iframe></p>';
						} elseif ( $audio_current->isDownloadable ) {
							foreach ( $audio_current->enclosures as $enclose ) {
								if ( $enclose->type == 'audio/mpeg' ) {
									$article['audio'][] = '[audio mp3="' . $enclose->href . '"][/audio]';
								}
							}
						}
					}
				}
			}
		}
	}
	print_r( $article );
