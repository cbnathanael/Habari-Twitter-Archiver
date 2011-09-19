<?php
/* nate's twitter archiver, serious code regurgitation from the Twitter Plugin */

class TwitterArchiver extends Plugin
{
	CONST CONSUMER_KEY = "ZenVbxTsgEgG3AbhdhrRZA";
	CONST CONSUMER_SECRET = "SjQjI8SNVVrJIhi5eEqvZIndxAbTcC7rf5rm5MhPFwM";
	
	/**
	 * Sets default options; daily archival, do not archive @-replies, linkify URLs, run hashtag queries.
	 **/

	public function action_plugin_activation( $file )
	{
		if( Plugins::id_from_file($file) == Plugins::id_from_file(__FILE__) ) {
			if ( Options::get( 'twitterArchiver__hide_replies' ) == null ) {
				Options::set( 'twitterArchiver__hide_replies', 1 );
			}
			if ( ( Options::get( 'twitterArchiver__linkify_urls' ) == null ) or ( Options::get( 'twitterArchiver__linkify_urls' ) > 1 ) ) {
				Options::set( 'twitterArchiver__linkify_urls', 1 );
			}
			if ( Options::get( 'twitterArchiver__hashtags_query' ) == null ) {
				Options::set( 'twitterArchiver__hashtags_query', 'http://hashtags.org/search?query=' );
			}
			if ( Options::get( 'twitterArchiver__frequency' ) == null ) {
				Options::set( 'twitterArchiver__frequency', 'daily' );
			}
      if ( Options::get( 'twitterArchiver__post_title' ) == null ) {
				Options::set( 'twitterArchiver__post_title', 'Author tweeted' );
			}
      if ( Options::get( 'twitterArchiver__multiple_posts' ) == null ) {
				Options::set( 'twitterArchiver__multiple_posts', 0 );
			}
      if ( Options::get( 'twitterArchiver__post_tags' ) == null ) {
				Options::set( 'twitterArchiver__post_tags', 'twitter' );
			}
      if ( Options::get( 'twitterArchiver__link_to_tweet' ) == null ) {
				Options::set( 'twitterArchiver__link_to_tweet', 1 );
			}
      if ( Options::get( 'twitterArchiver__include_RT' ) == null ) {
				Options::set( 'twitterArchiver__include_RT', 1 );
			}
      if ( Options::get( 'twitterArchiver__include_reply' ) == null ) {
				Options::set( 'twitterArchiver__include_reply', 0 );
			}
      if ( Options::get( 'twitterArchiver__query_count' ) == null ) {
				Options::set( 'twitterArchiver__query_count', 20 );
			}
      if ( Options::get( 'twitterArchiver__last_run' ) == null ) {
        Options::set( 'twitterArchiver__last_id' , 0 );
      }

		}
	}

	/**
     * Add the Configure, Authorize and De-Authorize options for the plugin
     *
     * @access public
     * @param array $actions
     * @param string $plugin_id
     * @return array
     */
    public function filter_plugin_config( $actions, $plugin_id )
    {
    	
		if ( $plugin_id == $this->plugin_id() ) {
		
			if ( Options::get( 'twitterArchiver__access_token' ) !=NULL && Options::get( 'twitterArchiver__access_token' ) != '' ) {
				$actions['configure'] = _t( 'Configure' );
				$actions['deauthorize'] = _t( 'De-Authorize' );
			}
			else {
				$actions['authorize'] = _t( 'Authorize' );
			}
			
		}
		return $actions;
    }
	
	public function action_plugin_ui ( $plugin_id, $action ) {
		
		if ( $plugin_id == $this->plugin_id() ) {
			
			switch ( $action ) {
				
				case _t('Configure'):
					$this->action_plugin_ui_configure();
					break;
					
				case _t('De-Authorize'):
					$this->action_plugin_ui_deauthorize();
					break;
					
				case _t('Authorize'):
					$this->action_plugin_ui_authorize();
					break;
					
				// confirm is called by the return request from twitter, it's not ordinarily user-accessible
				case _t('Confirm'):
					$this->action_plugin_ui_confirm();
					break;
				
			}
			
		}
		
	}
	
	/**
     * Plugin UI - Displays the 'authorize' config option
     *
     * @access public
     * @return void
     */
    public function action_plugin_ui_authorize()
    {	
		require_once dirname( __FILE__ ) . '/lib/twitteroauth/twitteroauth.php';
		unset( $_SESSION['TwitterArchiverReqToken'] ); // Just being safe.
		
		$oauth = new TwitterOAuth(TwitterArchiver::CONSUMER_KEY, TwitterArchiver::CONSUMER_SECRET );
		$oauth_token = $oauth->getRequestToken( URL::get( 'admin', array( 'page' => 'plugins', 'configure' => $this->plugin_id(), 'configaction' => 'confirm' ) ) );
	echo "<pre>";
		print_r($oauth_token);
		echo "</pre>";	
		$request_link = $oauth->getAuthorizeURL( $oauth_token );
		$reqToken = array( "request_link" => $request_link, "request_token" => $oauth_token['oauth_token'], "request_token_secret" => $oauth_token['oauth_token_secret'] );
		$_SESSION['TwitterArchiverReqToken'] = serialize( $reqToken );
		
		$ui = new FormUI( strtolower( __CLASS__ ) );
		$ui->append( 'static', 'nocontent', '<h3>Authorize the Twitter Archiver Plugin</h3>
											 <p>Authorize your blog to have access to your Twitter account.</p>
											 <p>Click the button below, and you will be taken to Twitter.com. If you\'re already logged in, you will be presented with the option to authorize your blog. Press the "Allow" button to do so, and you will come right back here.</p>
											 <br><p style="text-align:center"><a href="'.$reqToken['request_link'].'"><img src="'. URL::get_from_filesystem( __FILE__ ) .'/lib/twitter_connect.png" alt="Sign in with Twitter" /></a></p>
					');
		$ui->out();
	}
			
	/**
     * Plugin UI - Displays the 'confirm' config option.
     *
     * @access public
     * @return void
     */
    public function action_plugin_ui_confirm()
	{
		require_once dirname( __FILE__ ) . '/lib/twitteroauth/twitteroauth.php';
		$user = User::identify();
		$ui = new FormUI( strtolower( __CLASS__ ) );
		if( !isset( $_SESSION['TwitterArchiverReqToken'] ) ){
			$auth_url = URL::get( 'admin', array( 'page' => 'plugins', 'configure' => $this->plugin_id(), 'configaction' => 'Authorize' ) );
			$ui->append( 'static', 'nocontent', '<p>'._t( 'Either you have already authorized Habari to access your Twitter account, or you have not yet done so.  Please ' ).'<strong><a href="' . $auth_url . '">'._t( 'try again' ).'</a></strong>.</p>');
			$ui->out();
		}
		else {
			$reqToken = unserialize( $_SESSION['TwitterArchiverReqToken'] );
			$oauth = new TwitterOAuth( TwitterArchiver::CONSUMER_KEY, TwitterArchiver::CONSUMER_SECRET, $reqToken['request_token'], $reqToken['request_token_secret'] );
			$token = $oauth->getAccessToken($_GET['oauth_verifier']);
			$config_url = URL::get( 'admin', array( 'page' => 'plugins', 'configure' => $this->plugin_id(), 'configaction' => 'Configure' ) );

			if( ! empty( $token ) && isset( $token['oauth_token'] ) ){
				Options::set( 'twitterArchiver__access_token', $token['oauth_token'] );
				Options::set( 'twitterArchiver__access_token_secret', $token['oauth_token_secret'] );
				Options::set( 'twitterArchiver__user_id', $token['user_id'] );
				Session::notice( _t( 'Habari Twitter Archiver plugin successfully authorized.', 'twitter' ) );
				Utils::redirect( $config_url );
			}
			else{
				// TODO: We need to fudge something to report the error in the event something fails.  Sadly, the Twitter OAuth class we use doesn't seem to cater for errors very well and returns the Twitter XML response as an array key.
				// TODO: Also need to gracefully cater for when users click "Deny"
				echo '<form><p>'._t( 'There was a problem with your authorization.' ).'</p></form>';
			}
			unset( $_SESSION['TwitterArchiverReqToken'] );
		}
	}
				
				
	/**
     * Plugin UI - Displays the 'deauthorize' config option.
     *
     * @access public
     * @return void
     */
    public function action_plugin_ui_deauthorize()
	{
		Options::set( 'twitterArchiver__access_token', '' );
		Options::set( 'twitterArchiver__access_token_secret', '' );
		Options::set( 'twitterArchiver__user_id', '' );
		$reauth_url = URL::get( 'admin', array( 'page' => 'plugins', 'configure' => $this->plugin_id(), 'configaction' => 'Authorize' ) ) . '#plugin_options';
		Session::notice( _t( 'Twitter Archiver plugin authorization revoked. <br>Don\'t forget to revoke access on Twitter itself.', 'twitter' ) );
		Utils::redirect( $reauth_url );
	}

	
	/**
     * Plugin UI - Displays the 'configure' config option.
     *
     * @access public
     * @return void
     */
    public function action_plugin_ui_configure()
	{
		$ui = new FormUI( strtolower( __CLASS__ ) );
    
    $post_fieldset = $ui->append( 'fieldset', 'twitterArchiver_post_settings', _t( 'Posting Options', 'twitterArchiver' ) );
    $twitterArchiver_post = $post_fieldset->append( 'text', 'post_title', 'twitterArchiver__post_title', _t( 'Title of Posts:', 'twitterArchiver' ) );
    $twitterArchiver_post = $post_fieldset->append( 'text', 'post_tags', 'twitterArchiver__post_tags', _t( 'Tags to apply to posts (comma separated):', 'twitterArchiver' ) );
    $twitterArchiver_post = $post_fieldset->append( 'checkbox', 'multiple_posts', 'twitterArchiver__multiple_posts', _t( 'Create individual posts for each tweet:', 'twitterArchiver' ) );
    $twitterArchiver_post = $post_fieldset->append( 'checkbox', 'link_to_tweet', 'twitterArchiver__link_to_tweet', _t( 'Provide link to tweet:', 'twitterArchiver' ) );

		$tweet_fieldset = $ui->append( 'fieldset', 'twitterArchiver_pull_settings', _t( 'Tweet Options', 'twitterArchiver' ) );
		$twitterArchiver_show = $tweet_fieldset->append( 'select', 'frequency', 'twitterArchiver__frequency', _t( 'How often should it archive:', 'twitterArchiver' ) );
    $twitterArchiver_show->options = array( 'daily' => 'daily', 'hourly' => 'hourly' );
		$twitterArchiver_show = $tweet_fieldset->append( 'checkbox', 'hide_replies', 'twitterArchiver__include_replies', _t( 'Do not include @replies', 'twitterArchiver' ) );
		$twitterArchiver_show = $tweet_fieldset->append( 'checkbox', 'hide_retweets', 'twitterArchiver__include_RT', _t( 'Include retweets', 'twitterArchiver' ) );
    $twitterArchiver_show = $tweet_fieldset->append( 'checkbox', 'linkify_urls', 'twitterArchiver__linkify_urls', _t('Linkify URLs') );
    $twitterArchiver_show = $tweet_fieldset->append( 'text', 'hashtags_query', 'twitterArchiver__hashtags_query', _t( '#hashtags query link:', 'twitterArchiver' ) );
    $twitterArchiver_show = $tweet_fieldset->append( 'text', 'query_count', 'twitterArchiver__query_count', _t( 'Number of tweets to query (20 default; adjust to reflect amount of tweets in your selected period):', 'twitterArchiver' ) );

		$ui->on_success( array( $this, 'updated_config' ) );
		$ui->append( 'submit', 'save', _t( 'Save', 'twitterArchiver' ) );
		$ui->out();
	}

	/**
	 * Give the user a session message to confirm options were saved.
	 **/
	public function updated_config( FormUI $ui )
	{
		Session::notice( _t( 'Twitter Archiver options saved.', 'twitterArchiver' ) );
		CronTab::delete_cronjob('twitterArchiver');
    if ( Options::get( 'twitterArchiver__frequency' ) == 'daily' ) {
      $frequency = 86400;
    }
    else { $frequency = 3600; }
    $params = array (
      'name' => 'twitterArchiver',
      'callback' => 'create_archive',
      'start_time' => strtotime('midnight'),
      'increment' => $frequency,
      'description' => 'Twitter Archiver'
    );
		CronTab::add_cron( $params );
    $foo = CronTab::get_cronjob('twitterArchiver');
    //$logText = 
		EventLog::log('creat archive update', 'info', 'default', 'TwitterArchiver', '');
		$ui->save();
	}
	
	/**
	* Run from crontab to grab the appropriate tweets and load them into posts.
	**/
	public function filter_create_archive()
	{
		EventLog::log('creat archive start', 'info', 'default', 'TwitterArchiver', '');
		require_once dirname( __FILE__ ) . '/lib/twitteroauth/twitteroauth.php';	
		$connection = new TwitterOAuth( TwitterArchiver::CONSUMER_KEY, TwitterArchiver::CONSUMER_SECRET, Options::get( 'twitterArchiver__access_token' ), Options::get( 'twitterArchiver__access_token_secret' ) ); //OAUTH_TOKEN, OAUTH_SECRET);
		//EventLog::log( $connection->http_code, 'info', 'default', 'Twitter Archiver', '' );
		$content = $connection->get('account/verify_credentials');
		
		$parameters = array(
			'count' => Options::get( 'twitterArchiver__query_count' ),
      'since_id' => Options::get( 'twitterArchiver__last_id' ),   //CHANGE to reflect last time run, etc.
			'include_rts' => Options::get( 'twitterArchiver__include_RT'),
			'include_entities' => 1,
			'exclude_replies' => Options::get( 'twitterArchiver__include_reply' )
		);
    $fp = fopen(dirname( __FILE__ ) .'/data.txt', 'w');
	
		$response = $connection->get('statuses/user_timeline',$parameters);
    //fwrite( $fp, print_r( $response, TRUE ) );
    //twitter returns tweets in descending order, we need them in ascending (the order in which they appeared)
    $response = array_reverse( $response );
    
    if ( Options::get( 'twitterArchiver__linkify_urls' ) == 1 ) {
			foreach ( $response as $tweet ) {
				/* link to all http: */
				$tweet->text = preg_replace( '%https?://\S+?(?=(?:[.:?"!$&\'()*+,=]|)(?:\s|$))%i', "<a href=\"$0\">$0</a>", $tweet->text ); 
				/* link to usernames */
				$tweet->text = preg_replace( '/(?<!\w)@([\w-_.]{1,64})/', '@<a href="http://twitter.com/$1">$1</a>', $tweet->text ); 
				/* link to hashtags np-- added break in the regex b/c the ? > combo broke vim's syntax hilighting and it drove me batty*/
				$tweet->text = preg_replace( '/(?<!\w)#((?'.'>\d{1,64}|)[\w-.]{1,64})/', '<a href="' . Options::get( 'twitterArchiver__hashtags_query' ) . '$1">#$1</a>', $tweet->text );
			}
		}
    if ( Options::get( 'twitterArchiver__multiple_posts' ) == 0 ) {
      $post = new Post();
      $post_content = '';
      foreach ( $response as $tweet ) {
        $post_content .= '<p class="tweetarchive_text">'.$tweet->text.'</p>';
        if( Options::get( 'twitterArchiver__link_to_tweet' ) == 1 ) {
          $post_content .= '<p class="tweetarchive_date"><a href="http://www.twitter.com/#!/'.$content->screen_name.'/status/'.$tweet->id_str.'">'.strtotime('h:i:s A', $tweet->created_at).'</a></p>';
        }
        else {
         $post_content .= '<p class="tweetarchive_date">'.strtotime('h:i:s A', $tweet->created_at).'</p>';
 
        }
        $lastID = $tweet->id_str;
      }
     $postdata = array(
        'slug' => 'twitter-'.date('Y-m-d'),
        'user_id' => 1,
        'pubdate' => strtotime( "now" ),
        'status' => 2, // 1=draft, 2=published
        'content_type' => 1,
        'title' => Options::get( 'twitterArchiver__post_title' ),
        'tags' => Options::get( 'twitterArchiver__post_tags' ),
        'content' => '<p class="tweetarchive_text">'.$post_content.'</p>',
      );
      fwrite( $fp, print_r( $postdata, TRUE ) ); 
      $post = Post::create( $postdata );
    }
    else {
      $i= 0;
    
      foreach ( $response as $tweet ) {
        $post = new Post();
        $post_content = '<p class="tweetarchive_text">' . $tweet->text . '</p>';
        if( Options::get( 'twitterArchiver__link_to_tweet' ) == 1 ) {
          $post_content .= '<p class="tweetarchive_date"><a href="http://www.twitter.com/#!/'.$content->screen_name.'/status/'.$tweet->id_str.'">'.strtotime('h:i:s A', $tweet->created_at).'</a></p>';
        }
        else {
          $post_content .= '<p class="tweetarchive_date">'.strtotime('h:i:s A', $tweet->created_at).'</p>';
        } 
        $i++;
        $postdata = array(
          'slug' => 'twitter-'.date('Y-m-d', strtotime($tweet->created_at)).'-'.$i,
          'user_id' => 1,
          'pubdate' => strtotime( $tweet->created_at ),
          'status' => 2, // 1=draft, 2=published
          'content_type' => 1,
          'title' => Options::get( 'twitterArchiver__post_title' ),
          'tags' => Options::get( 'twitterArchiver__post_tags' ),
          'content' => $post_content,
        );
          
        $lastID = $tweet->id_str;
        $post = Post::create( $postdata );
        unset( $post );
        fwrite( $fp, print_r($postdata, TRUE) );
      }
    }
    
    fclose($fp);
    //Options::set( 'twitterArchiver__last_id', $lastID );
		EventLog::log('Twitter Archiver completed', 'info', 'default', 'TwitterArchiver', '');

	}	
	
	
}

?>
