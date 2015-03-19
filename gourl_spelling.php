<?php
/*
Plugin Name: 		GoUrl Spelling Notifications
Plugin URI: 		https://gourl.io/php-spelling-notifications.html
Description: 		Plugin allows site visitors to send reports to the webmaster / website owner about any spelling or grammatical errors which may be found by readers. Visitors should select text with a mouse, press Ctrl+Enter, enter comments and the webmaster will be notified about any such errors. Nice and simple plugin - no external websites needed and fully customizable; easily change the language !
Version: 			1.0.0
Author: 			GoUrl.io
Author URI: 		https://gourl.io
License: 			GPLv2
License URI: 		http://www.gnu.org/licenses/gpl-2.0.html
GitHub Plugin URI: 	https://github.com/cryptoapi/Wordpress-Spelling-Notifications
*/


if (!defined( 'ABSPATH' )) exit;  // Exit if accessed directly in wordpress


if (!class_exists('GoUrl_Spelling')) 
{

	DEFINE('GOURLSPL', "spelling-gourl");
	

	class GoUrl_Spelling
	{
		private $save_flag  = false;
		 
		private $from_email = "";
		private $to_email 	= "";
		private $form_style = "";
		
		// localization
		private $fields = array( "form_line1" 		=> "Spelling or Grammar Error",
								 "form_line2"		=> "Webpage",
								 "form_line3"		=> "Describe the error and offer a solution",
								 "form_width"		=> "470px",
				
								 "form_button1"		=> "Send to Author",
								 "form_button2"		=> "Cancel",
								 "form_button3"		=> "Close Window",
				
								 "plugin_name"		=> "GoUrl Spelling Notifications",
				
								 "form_message1" 	=> "Please select no more than 400 characters!",
								 "form_message2"	=> "Please select the spelling error!",
								 "form_message3"	=> "Thank you! Your message has been successfully sent, we highly appreciate your support!",
								 "form_message4"	=> "Error! Message not sent, Please try again!",
				
								 "email_subject"	=> "Spelling Error on",
								 "email_line1"		=> "Webpage",
								 "email_line2"		=> "Error",
								 "email_line3"		=> "Comments",
								 "email_line4"		=> "User",
								 "email_line5"		=> "IP",
								 "email_line6"		=> "Agent"
							);
		
		
		/*
		 *  1
		*/
		public function __construct()
		{
			$this->fields["email_subject"] .= " " . ucfirst($_SERVER["SERVER_NAME"]); 
			$this->def = $this->fields;
			
			if ( is_admin() ) 
			{
				if (isset($_GET["page"]) && $_GET["page"] == "spelling_notifications" && strpos($_SERVER["SCRIPT_NAME"], "options-general.php"))
				{
					if (isset($_POST["from_email"]) && isset($_POST["to_email"])) $this->save_settings();
					add_action( 'admin_footer_text', array(&$this, 'admin_footer_text'), 25);
				}
				
				add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
				add_action( 'admin_head', array( &$this, 'html_header' ));
			}
			else
			{
				add_action("wp_head", array( &$this, 'html_header' ));
			}
			
			$this->get_settings();
			
			add_filter('plugin_action_links', array(&$this, 'plugin_action_links'), 10, 2 );
			
			add_action('parse_request', array(&$this, 'notification_window'));
			
			return true;
		}

		
		
		/*
		 * 2
		*/
		public function plugin_action_links($links, $file)
		{
			static $this_plugin;
		
			if (false === isset($this_plugin) || true === empty($this_plugin)) {
				$this_plugin = plugin_basename(__FILE__);
			}
		
			if ($file == $this_plugin) {
				$settings_link = '<a href="'.admin_url('options-general.php?page=spelling_notifications').'">'.__( 'Settings', GOURLSPL ).'</a>';
				array_unshift($links, $settings_link);
			}
		
			return $links;
		}
		
		
		/*
		 *  3
		*/
		public function admin_menu()
		{
			add_options_page(__('Spelling Notifications', GOURLSPL), __('Spelling Notifications', GOURLSPL), 'add_users', 'spelling_notifications', array(&$this, 'settings_page'));
			
			return true;
		}
		
		
			
		/*
		 *  4
		*/
		public function html_header()
		{
			echo '<script type="text/javascript" charset="UTF-8">spl_path="'.trim(site_url(),' /').'";spl_txt1="'.esc_html($this->fields["form_message1"]).'";spl_txt2="'.esc_html($this->fields["form_message2"]).'"</script>';
			echo '<script src="'.plugins_url('/gourl_spelling.js', __FILE__).'" type="text/javascript"></script>
				 <link rel="stylesheet" type="text/css" href="'.plugins_url('/gourl_spelling'.($this->form_style=='noshadow'?'2':'').'.css', __FILE__).'" media="all" />';
			if ($this->fields["form_width"] != "470px") echo "<style>#splwin #splwindow {width:".esc_html($this->fields["form_width"])."}</style>";
			
			return true;
		}
		
		
		
		/*
		 *  5
		*/
		private function save_settings()
		{
			$arr = array_keys($this->fields);
			$arr[] = "from_email";
			$arr[] = "to_email";
			$arr[] = "form_style";
				
			foreach ($arr as $k)
				if (isset($_POST[$k]))
				{
					if (isset($_POST["restore_default"])) $v = (isset($this->def[$k])) ? $this->def[$k] : "";
					else $v = $_POST[$k];
					
					if (is_string($v)) $v = trim(stripslashes($v));
					update_option(GOURLSPL.$k, $v);
					$this->save_flag = true;
				}
		
				return true;
		}
		
		
		
		/*
		 *  6
		*/
		private function get_settings()
		{
			$arr = array_keys($this->fields);
			$arr[] = "from_email";
			$arr[] = "to_email";
			$arr[] = "form_style";
			
			foreach ($arr as $k)
			{
				$v = get_option(GOURLSPL.$k);
				if (is_string($v)) $v = trim(stripslashes($v));
				
				if (isset($this->fields[$k]))
				{
					if (mb_strlen(trim($v)) < 3) $v = $this->def[$k];
					$this->fields[$k] = $v;
				}
				else $this->$k = $v; 
			} 
			
			// Validate form width
			$this->fields["form_width"] = str_replace("px", "", $this->fields["form_width"]);
			if (!is_numeric($this->fields["form_width"]) || $this->fields["form_width"] < 350 || $this->fields["form_width"] > 1000) $this->fields["form_width"] = 470;
			$this->fields["form_width"] .= "px";
			
			// validate emails
			$admin_email = get_option('admin_email');
				
			if (!$this->from_email || !is_email($this->from_email))
			{
				$this->from_email = 'server@'.$_SERVER["SERVER_NAME"];
				if (!is_email($this->from_email)) $this->from_email = $admin_email;
			}
			
			if (!$this->to_email || !is_email($this->to_email)) $this->to_email = $admin_email;

			// validate form style
			if (!in_array($this->form_style, array("shadow", "noshadow"))) $this->form_style = "shadow";
			
			return true;
		}
		
		
		
		
		
		/*
		 *  7
		*/
		public function settings_page()
		{
			$this->get_settings();
			
			$tmp  = "<div style='margin:30px 20px'>";
			$tmp .= "<form accept-charset='utf-8' action='".admin_url('options-general.php?page=spelling_notifications')."' method='post'>";
			
			$tmp .= "<h2>".__('GoUrl Spelling Notifications - Settings', GOURLSPL);
			$tmp .= "<div style='float:right; margin-top:-20px'><a href='https://gourl.io/' target='_blank'><img title='".__('Bitcoin Payment Gateway for Your Website', GOURLSPL)."' src='".plugins_url('/images/gourl.png', __FILE__)."' border='0'></a></div>";
			$tmp .= "</h2>";
			
			if ($this->save_flag) $tmp .= "<br><div class='updated'><p>".__('Settings has been '.(isset($_POST["restore_default"])?'restored':'saved').' <strong>successfully</strong>', GOURLSPL)."</p></div><br>";
				
			$tmp .= "<table class='widefat' cellspacing='20' style='padding:10px 25px'>";

			
			// I
			$tmp .= "<tr valign='top'>";
			$tmp .= "<th colspan='2'>";
			$tmp .= "<p>";
			$tmp .= __('The plugin allows site visitors to send reports to the webmaster / website owner about any spelling or grammatical errors which may be found by readers. ', GOURLSPL).'<br>';
			$tmp .= __('Visitors should select text with a mouse, press Ctrl+Enter, enter comments and the webmaster will be notified about any such errors.', GOURLSPL).'<br>';
			$tmp .= "</p>";
			$tmp .= "<p>";
			$tmp .= __('<b>Plugin Ready to use:</b> &#160; Select any text on this webpage and press CTRL+ENTER on your keyboard :)', GOURLSPL);
			$tmp .= " &#160; ".sprintf(__('<a href="%s">See plugin homepage &#187;', GOURLSPL), "https://gourl.io/php-spelling-notifications.html");
			$tmp .= "</p><br>";
			$tmp .= "</th>";
			$tmp .= "</tr>";

			
			// II			
			$tmp .= "<tr valign='top'>";
            $tmp .= "<th scope='row' width='150'><label for='from_email'><b>".__( 'Email From:', GOURLSPL )."</b></label></th>";
            $tmp .= "<td><input type='text' size='50' value='".esc_html($this->from_email)."' name='from_email' id='from_email'>";
			$tmp .= "<p class='description'>".__('Please enter your email address for spelling error notifications', GOURLSPL )."</p>";
			$tmp .= "</tr>";
			
			
			// III
			$tmp .= "<tr valign='top'>";
			$tmp .= "<th scope='row' width='150'><label for='to_email'><b>".__( 'Email To:', GOURLSPL )."</b></label></th>";
			$tmp .= "<td><input type='text' size='50' value='".esc_html($this->to_email)."' name='to_email' id='to_email'>";
			$tmp .= "<p class='description'>".__('Please enter your email address for spelling error notifications', GOURLSPL )."</p>";
			$tmp .= "</tr>";
				

			// IV
			$tmp .= "<tr valign='top'>";
			$tmp .= "<th scope='row' width='150'><label for='form_style'><b>".__( 'Style:', GOURLSPL )."</b></label></th>";
			$tmp .= "<td><input type='radio' name='form_style' value='shadow'".$this->chk($this->form_style, "shadow").">".__('Shadow', GOURLSPL )." &#160; &#160; &#160; <input type='radio' name='form_style' value='noshadow'".$this->chk($this->form_style, "noshadow").">".__('No Shadow', GOURLSPL );
			$tmp .= "<p class='description'>".__('Full screen overlay with a pop-up notification form', GOURLSPL )."</p>";
			$tmp .= "</tr>";
				
			
			// V
			$banner = '<a href="https://gourl.io/php-spelling-notifications.html" target="_blank"><img title="'.esc_html($this->fields["plugin_name"]).'" alt="'.esc_html($this->fields["plugin_name"]).'" src="'.plugins_url('/images/gourlspelling.png', __FILE__).'" border="0" width="95" height="95"></a>';
			
			$tmp .= "<tr valign='top'>";
			$tmp .= "<th colspan='2' style='padding-left:20px'><br><h3 class='title'>".__('Banner', GOURLSPL )."</h3>";
			$tmp .= "<table><tr>";
			$tmp .= "<td><textarea rows='5' class='large-text' style='font-size:12px' readonly='readonly'>".esc_html($banner)."</textarea>";
			$tmp .= "<p class='description'>".sprintf(__('Copy and paste the banner code to the bottom of your webpages. <a target="_blank" href="%s">More info</a>', GOURLSPL ), "https://wordpress.org/support/topic/how-do-i-add-a-banner-code-to-the-bottom-of-my-page")."</p>";
			$tmp .= "</td><td> &#160; ".$banner."</td>";
			$tmp .= "</tr></table>";
			$tmp .= "</th>";
			$tmp .= "</tr>";
			
			
			// VI
			$tmp .= "<tr valign='top'>";
			$tmp .= "<th colspan='2'><br><br><br><h3 class='title'>".__('Text Localization and customization (optional)', GOURLSPL )."</h3></th>";
			$tmp .= "</tr>";
			

			
			// VII
			foreach ($this->fields as $k => $v)
			{
				$tmp .= "<tr valign='top'>";
				$tmp .= "<th scope='row' width='150'><label for='".$k."'><b>".ucwords(str_replace("_", " ", __( $k, GOURLSPL )))."</b></label></th>";
				$tmp .= "<td><input type='text' class='widefat' value='".esc_html($v)."' name='".$k."' id='".$k."'>";
				$tmp .= "<p class='description'>".__('Default: ', GOURLSPL ).$this->def[$k]."</p>";
				$tmp .= "</tr>";
			}
			
			
			// VIII			
			$tmp .= "<tr valign='top'>";
			$tmp .= "<th colspan='2'><br>";
			$tmp .= "<input type='submit' class='button button-primary' name='submit' value='".__('Save Settings', GOURLSPL)."'> &#160; &#160; &#160; ";
			$tmp .= "<input type='submit' class='button button-default' name='restore_default' value='".__('Restore Default', GOURLSPL)."'>";
			$tmp .= "<br><br></th>";
			$tmp .= "</tr>";
			
			
			// IX
			if (!defined('GOURL'))
			{
				$tmp .= "<tr valign='top'>";
				$tmp .= "<th colspan='2'><br><h3 class='title'>".__('See our other free plugin (monetize your website) -', GOURLSPL )."</h3>";
				$tmp .= "<div><a href='".admin_url("plugin-install.php?tab=search&type=term&s=GoUrl+Bitcoin+Payment+Gateway+Downloads")."'><img style='max-width:100%' title='".__('Free Bitcoin Payment Gateway for Your Website', GOURLSPL)."' alt='".__('Free Bitcoin Payment Gateway for Your Website', GOURLSPL)."' src='".plugins_url('/images/bitcoin-payments.png', __FILE__)."' border='0'></a></div>";
				$tmp .= "</th></tr>";
			}	
				
			$tmp .= "</table>";
			$tmp .= "</form>";
			$tmp .= "</div>";
				
			echo $tmp;
			
			return true;
		}
		
		
		
		/*
		 *  8
		*/
		public function notification_window( &$wp )
		{
			global $wp, $current_user;
		
			if (in_array(strtolower($this->right($_SERVER["REQUEST_URI"], "/", false)), array("?spelling.notification.php", "index.php?spelling.notification.php")))
			{
				ob_clean();

				if(isset($_POST['submit']) && $_POST['submit'])
				{
					$df 	 = get_option( 'date_format' );
					$tf 	 = get_option( 'time_format' );
					$dt		 = date( "{$df} {$tf}", current_time( 'timestamp' ) );
					$title 	 = $this->fields["email_subject"] . ', ' . $dt;
					$url 	 = esc_html(mb_substr(trim($_POST['url']), 0, 2000));
					$spl 	 = mb_substr(trim(stripslashes($_POST['spl'])), 0, 2000);
					$comment = esc_html(mb_substr(trim(esc_html(stripslashes($_POST['comment']))), 0, 20000));
					$agent	 = esc_html(trim($_SERVER['HTTP_USER_AGENT']));
					$user    = (!$current_user->ID) ? __('Guest', GOURLSPL) : "<a style='color:#007cb9' href='".admin_url("user-edit.php?user_id=".$current_user->ID)."'>user".$current_user->ID."</a>, ".$current_user->user_login.", ".$current_user->user_firstname." ".$current_user->user_lastname.", ".$current_user->user_email;
				
				
					$body = '<html>
							<head>
							<title>'.esc_html($this->fields["form_line1"]).'</title>
							</head>
							<body style="font-size: 13px; margin: 5px; color: #333333; line-height: 25px; font-family: Verdana, Arial, Helvetica">
							'.$dt.'<br><br>
							<strong>'.esc_html($this->fields["email_line1"]).':</strong> &#160;<a style="color:#007cb9" href='.$url.'>'.$url.'</a>
							<br><br><br>
							<strong>'.esc_html($this->fields["email_line2"]).':</strong><br>---------<br>'.str_replace("<strong>", "<strong style='color:red'>", $spl).(!mb_strpos($spl, '</strong>')?'</strong>':'').'
							<br><br><br>
							<strong>'.esc_html($this->fields["email_line3"]).':</strong><br>---------<br>'.$comment.'
							<br><br><br><br>
							<strong>'.esc_html($this->fields["email_line4"]).':</strong> '.str_replace(',  ,', ', ', $user).'
							<br>
							<strong>'.esc_html($this->fields["email_line5"]).':</strong> <a style="color:#007cb9" href="http://myip.ms/info/whois'.(filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)?'6':'').'/'.esc_html($_SERVER['REMOTE_ADDR']).'">'.esc_html($_SERVER['REMOTE_ADDR']).'</a>
							<br>
							<strong>'.esc_html($this->fields["email_line6"]).':</strong> '.esc_html($_SERVER['HTTP_USER_AGENT']).'
							</body>
							</html>
						';
				
					$from = "From: =?utf-8?B?".base64_encode($this->from_email)."?= <".$this->from_email.">\n";
					$from .= "X-Sender: <".$this->from_email.">\n";
					$from .= "Content-Type: text/html; charset=utf-8\n";
						
					$result = mail($this->to_email, $title, $body, $from);
				}

				$tmp = '
				<!DOCTYPE HTML>
				<html>
				<head>
					<meta charset="utf-8">
					<title>'.esc_html($this->fields["plugin_name"]).'</title>
					<link href="'.plugins_url('/bootstrap/css/bootstrap.min.css', __FILE__).'" rel="stylesheet">
					<link href="'.plugins_url('/bootstrap/css/bootstrap-theme.min.css', __FILE__).'" rel="stylesheet">
					<script>var p=top;function loaddata(){null!=p&&(document.forms.splwin.url.value=p.splloc);null!=p&&(document.forms.splwin.spl.value=p.spl);if("undefined"==typeof p.spl || "undefined"==typeof p.splloc) {document.getElementById("submit").disabled = true;document.getElementById("cancel").disabled = true;}}function hide(){var a=p.document.getElementById("splwin");a.parentNode.removeChild(a)};window.onkeydown=function(event){if(event.keyCode===27){hide()}};</script>
					<style>#m strong{color:red}.container{margin-top:20px}</style>
				</head>
				<body onload=loaddata()>
					<div class="container">
						<p><b>'.esc_html($this->fields["form_line1"]).'</b></p>
						<div class="pull-right" style="margin:-44px -10px 0 0;"><a href="javascript:void(0)" onclick="hide()" title="'.esc_html($this->fields["form_button3"]).'" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-remove"></span></a></div>';
						
						if(isset($_POST['submit']) && $_POST['submit'])
						{
							$tmp .= '<br><br><br>';
							
							if($result)
							{
								$tmp .= '<div class="alert alert-success" role="alert"><span class="glyphicon glyphicon-ok"></span>
											&#160;'.esc_html($this->fields["form_message3"]).'
										</div>';
							}
							else
							{
								$tmp .= '<br>
										<div class="alert alert-danger" role="alert"><span class="glyphicon glyphicon-remove-sign"></span>
											&#160;'.esc_html($this->fields["form_message4"]).'
										</div>';
							}
							
							$tmp .= '<br><br><div style="text-align:center; margin-left:-20px"><a target="_blank" href="https://gourl.io/php-spelling-notifications.html"><img alt="'.esc_html($this->fields["plugin_name"]).'" title="'.esc_html($this->fields["plugin_name"]).'" src="'.plugins_url('/images/gourlspelling2.png', __FILE__).'"></a><a href="https://gourl.io/php-spelling-notifications.html" target="_blank" title="">'.esc_html($this->fields["plugin_name"]).' &#187;</a></div>
									 <br><br><br><div style="text-align:center"><input class="btn btn-danger btn-sm" onclick="hide()" type="button" value="'.esc_html($this->fields["form_button3"]).'" id="cancel" name="cancel"></div>';
						}
						else
						{
							$tmp .= '<form method="post" action="'.site_url('/index.php?spelling.notification.php').'" name="splwin">
									<div id="m" class="alert alert-warning" style="margin-bottom:7px;"><script>document.write(p.spl);</script></div>
									<input class="form-control" type="hidden" id="spl" name="spl">
									<label style="font-weight:lighter;">'.esc_html($this->fields["form_line2"]).':</label>
									<input class="form-control input-sm" id="url" type="text" name="url" size="35" readonly="readonly">
									<label style="font-weight:lighter;margin-top:8px;">'.esc_html($this->fields["form_line3"]).':</label>
									<textarea class="form-control" style="margin-bottom:11px;" id="comment" rows="4" name="comment" required="required" autofocus="autofocus"></textarea>
									<input class="btn btn-success btn-sm" type="submit" value="'.esc_html($this->fields["form_button1"]).'" id="submit" name="submit"> &#160;
									<input class="btn btn-danger btn-sm" onclick="hide()" type="button" value="'.esc_html($this->fields["form_button2"]).'" id="cancel" name="cancel">
									<div style="position:absolute;right:30px;bottom:5px;width:60px"><a target="_blank" href="https://gourl.io/php-spelling-notifications.html"><img alt="'.esc_html($this->fields["plugin_name"]).'" title="'.esc_html($this->fields["plugin_name"]).'" src="'.plugins_url('/images/gourlspelling2.png', __FILE__).'"></a></div>
									</form>';
						}
				
				$tmp .= '
						</div>
					</body>
				</html>';
					
				echo $tmp;
				
				ob_flush();
					
				die;
			}
		
			return true;
		}
		

		
		/*
		 * 9
		*/
		public function admin_footer_text()
		{
			return sprintf( __( 'If you like <strong>GoUrl Spelling Notifications</strong> please leave us a <a href="%1$s" target="_blank">&#9733;&#9733;&#9733;&#9733;&#9733;</a> rating on <a href="%1$s" target="_blank">WordPress.org</a>. A huge thank you from GoUrl.io in advance!', GOURLSPL ), 'https://wordpress.org/support/view/plugin-reviews/gourl-spelling-notifications?filter=5#postform');
		}
		
		
		
		/*
		 *  10
		*/
		public function right($str, $findme, $firstpos = true)
		{
			$pos = ($firstpos)? mb_stripos($str, $findme) : mb_strripos($str, $findme);
		
			if ($pos === false) return $str;
			else return mb_substr($str, $pos + mb_strlen($findme));
		}
		
		
		
		/*
		 *  11
		*/
		private function chk($val1, $val2)
		{
			$tmp = (strval($val1) == strval($val2)) ? ' checked="checked"' : '';
		
			return $tmp;
		}
			
	}
	// end class                             
	
	new GoUrl_Spelling;
}