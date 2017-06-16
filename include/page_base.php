<?php

require_once 'include/session.php';
require_once 'include/user.php';

class PageBase
{
	private $_state;
	
	protected $_title;
	protected $_permissions;
	
	private $_err_message;
	
	protected $_facebook;
	
	function __construct()
	{
		initiate_session();
	}
	
	final function run($title, $permissions)
	{
		$this->_err_message = NULL;
		$title_shown = false;
		$this->_facebook = true;
		$this->_permissions = $permissions;
		$this->_title = $title;
		$this->_state = PAGE_STATE_EMPTY;
		try
		{
			if (!check_permissions($this->_permissions))
			{
				throw new FatalExc(get_label('No permissions'));
			}
			
			try
			{
				$this->prepare();
			}
			catch (Exc $e)
			{
				Db::rollback();
				Exc::log($e);
				$this->error($e);
			}
			
			if ($this->show_header())
			{
				if (is_dir('lock'))
				{
					$this->show_lock_page();
				}
				else
				{
					$this->show_title();
					$title_shown = true;
					$this->show_body();
				}
			}
			$this->show_footer();
		}
		catch (RedirectExc $e)
		{
			$url = $e->get_url();
			header('location: ' . $url);
			echo get_label('Redirecting to [0]', $url);
		}
		catch (Exception $e)
		{
			Db::rollback();
			Exc::log($e);
			$this->error($e);
			try
			{
				$this->show_header();
			}
			catch (Exception $e)
			{
				Exc::log($e);
			}
			try
			{
				$this->show_footer();
			}
			catch (Exception $e)
			{
				Exc::log($e);
			}
		}
	}
	
	private function show_lock_page()
	{
		global $_profile;
		
		echo '<h3>' . get_label('Mafia Ratings is under maintenance. Please come again later.') . '</h3>';
		echo '<img src="images/repairs.png" width="160">';
		if ($_profile != NULL && $_profile->is_admin())
		{
			echo '<p><input type="submit" class="btn norm" value="Unlock the site" onclick="mr.lockSite(false)"></p>';
		}
	}
	
	final function show_header()
	{
		global $_session_state, $_profile, $_lang_code;
		global $brand;
		
		if ($this->_state != PAGE_STATE_EMPTY)
		{
			return true;
		}
		
		echo '<!DOCTYPE HTML>';
		echo '<html>';
		echo '<head>';
		echo '<title>' . $brand->titleprefix . ' ' . $this->_title . '</title>';
		echo '<META content="text/html; charset=utf-8" http-equiv=Content-Type>';
		echo '<script src="js/jquery.min.js"></script>';
		echo '<script src="js/jquery-ui.min.js"></script>';
		echo '<script src="js/jquery.ui.menubar.js"></script>';
		echo '<script src="js/labels_' . $_lang_code . '.js"></script>';
		echo '<script src="js/common.js"></script>';
		echo '<script src="js/md5.js"></script>';
		echo '<script src="js/mr.js"></script>';
		echo '<link rel="stylesheet" href="jquery-ui.css" />';
		if (is_mobile())
		{
			echo '<link rel="stylesheet" href="mobile.css" type="text/css" media="screen" />';
		}
		else
		{
			echo '<meta property="og:title" content="' . $brand->metatitle . '" />';
			echo '<meta property="og:type" content="activity" />';
			echo '<meta property="og:url" content="' . $brand->metaurl . '" />';
			echo '<meta property="og:site_name" content="' . $brand->metasitename . '" />';
			echo '<meta property="fb:admins" content="' . $brand->metafbadmins . '" />';
			echo '<link rel="stylesheet" href="desktop.css" type="text/css" media="screen" />';
		}
		echo '<link rel="stylesheet" href="common.css" type="text/css" media="screen" />';
		$this->add_headers();
		$this->_js();
		echo '</head>';
		
		$uri = get_page_url();
		if (count($_POST) == 0)
		{
			$_SESSION['last_page'] = array($this->_title, $uri);
			if (isset($_SESSION['back_list']))
			{
				$list = $_SESSION['back_list'];
				$last_back = count($list) - 1;
				if ($last_back >= 0 && $list[$last_back][1] == $uri)
				{
					unset($_SESSION['back_list'][$last_back]);
				}
	//			print_r($_SESSION['back_list']);
			}
		}

		$permissions = PERM_STRANGER;
		if ($_session_state == SESSION_OK)
		{
			$permissions = PERM_USER | ($_profile->user_flags & U_PERM_MASK) | ($_profile->user_club_flags & UC_PERM_MASK);
		}
		
		if (is_mobile())
		{
			echo '<body class="main">';
			if ($_session_state == SESSION_NO_USER || $_session_state == SESSION_LOGIN_FAILED)
			{
				echo '<table class="transp" width="100%"><tr>';
				
				echo '<td class="login">';
				echo '<form action="javascript:login()">';
				echo get_label('User name') . ':&nbsp;<input id="username" class="login_txt">&nbsp;';
				echo get_label('Password') . ':&nbsp;<input type="password" class="login_txt" id="password">&nbsp;';
				echo '<input value="remember" type="checkbox" id="remember" class="login_chk" checked>'.get_label('remember me').'&nbsp;';
				echo '<input value="Login" type="submit" class="login_btn"><br>';
				echo '<a href="reset_pwd.php?bck=0">'.get_label('Forgot your password?').'</a></form></td>';
				
				echo '<td align="right"><a href="create_account.php?bck=0" title="'.get_label('Create user account').'">'.get_label('Create account').'</a></td>';
				
				echo '</tr></table>';
			}
		}
		else
		{
			echo '<body>';
			echo '<table border="0" cellpadding="5" cellspacing="0" width="' . PAGE_WIDTH . '" align="center">';
			echo '<tr class="header">';
			if (is_ratings_server())
			{
				echo '<td class="header"><img src="images/title_r.png" /></td>';
			}
			else
			{
				echo '<td class="header"><img src="images/title.png" /></td>';
			}
			if ($_session_state == SESSION_NO_USER || $_session_state == SESSION_LOGIN_FAILED)
			{
				echo '<td align="right"><form action="javascript:login()">';
				echo get_label('User name') . ':&nbsp;<input id="username" class="in-header short">&nbsp;';
				echo get_label('Password') . ':&nbsp;<input type="password" id="password" class="in-header short">&nbsp;';
				echo '<input class="in-header" type="checkbox" id="remember" checked>'.get_label('remember me').'&nbsp;';
				echo '<input value="Login" class="in-header" type="submit"><br>';
				echo '<a href="reset_pwd.php?bck=0">'.get_label('Forgot your password?').'</a></form></td>';
			}
			// else if ($_profile != NULL)
			// {
				// echo '<td valign="middle" align="right">';
				// show_user_pic($_profile->user_id, $_profile->user_flags, ICONS_DIR, 48);
				// echo '<img src="images/clubs.png" /></td>';
			// }
			echo '</tr></table>';

			echo '<table class="main" border="0" cellpadding="5" cellspacing="0" width="' . PAGE_WIDTH . '" align="center">';
			echo '<tr><td class="menu" width="' . MENU_WIDTH . '" valign="top">';

			if (($permissions & PERM_STRANGER) != 0)
			{
				echo '<table class="menu" width="100%">';
				echo '<tr><td class="menu"><a href="create_account.php?bck=0" title="'.get_label('Create user account').'">'.get_label('Create account').'</a></td></tr>';
				echo '<tr><td class="menu"><a href="index.php?bck=0">'.get_label('Home').'</a></td></tr>';
				echo '</table><br>';
			
				echo '<table class="menu" width="100%"><tr><th class="menu">'.get_label('General').'</th></tr>';
				echo '<tr><td class="menu"><a href="calendar.php?bck=0" title="'.get_label('Where and when can I play').'">'.get_label('Calendar').'</a></td></tr>';
				echo '<tr><td class="menu"><a href="ratings.php?bck=0" title="'.get_label('Players ratings').'">'.get_label('Ratings').'</a></td></tr>';
				echo '<tr><td class="menu"><a href="clubs.php?bck=0" title="'.get_label('Clubs list').'">'.get_label('Clubs').'</a></td></tr>';
				echo '<tr><td class="menu"><a href="photo_albums.php?bck=0" title="'.get_label('Photo albums').'">'.get_label('Photo albums').'</a></td></tr>';
				echo '<tr><td class="menu"><a href="history.php?bck=0" title="'.get_label('Events history').'">'.get_label('History').'</a></td></tr>';
//				echo '<tr><td class="menu"><a href="welcome.php?bck=0" title="'.get_label('About Mafia: rules, tactics, general information.').'">'.get_label('About').'</a></td></tr>';
				echo '</table><br>';
			}
			else
			{
				echo '<table class="menu" width="100%"><tr><th class="menu">' . cut_long_name($_profile->user_name, 15) . '</th></tr>';
				echo '<tr><td class="menu"><a href="index.php?bck=0">'.get_label('Home').'</a></td></tr>';
				echo '<tr><td class="menu"><a href="profile.php?bck=0" title="'.get_label('Change my profile options').'">'.get_label('My profile').'</a></td></tr>';
				echo '<tr><td class="menu"><a href="inbox.php?bck=0" title="'.get_label('Private messages to me').'">'.get_label('Messages').'</a></td></tr>';
				echo '<tr><td class="menu"><a href="#" onclick="logout()" title="'.get_label('Logout from Mafia Ratings').'">'.get_label('Log out').'</a></td></tr>';
				echo '</table><br>';
				
				if (!$_profile->is_admin() && count($_profile->clubs) > 0)
				{
					echo '<table class="menu" width="100%"><tr><th class="menu">'.get_label('My clubs').'</th></tr>';
					foreach ($_profile->clubs as $club)
					{
						echo '<tr><td class="menu"><a href="club_main.php?bck=0&id=' . $club->id . '" title="' . $club->name . '">' . cut_long_name($club->name, 16) . '</a></td></tr>';
					}
					echo '</table><br>';
				}
				
				echo '<table class="menu" width="100%"><tr><th class="menu">'.get_label('General').'</th></tr>';
				echo '<tr><td class="menu"><a href="calendar.php?bck=0" title="'.get_label('Where and when can I play').'">'.get_label('Calendar').'</a></td></tr>';
				echo '<tr><td class="menu"><a href="ratings.php?bck=0" title="'.get_label('Players ratings').'">'.get_label('Ratings').'</a></td></tr>';
				echo '<tr><td class="menu"><a href="clubs.php?bck=0" title="'.get_label('Clubs list').'">'.get_label('Clubs').'</a></td></tr>';
				echo '<tr><td class="menu"><a href="photo_albums.php?bck=0" title="'.get_label('Photo albums').'">'.get_label('Photo albums').'</a></td></tr>';
				echo '<tr><td class="menu"><a href="history.php?bck=0" title="'.get_label('Events history').'">'.get_label('History').'</a></td></tr>';
				echo '<tr><td class="menu"><a href="welcome.php?bck=0" title="'.get_label('About Mafia: rules, tactics, general information.').'">'.get_label('About').'</a></td></tr>';
				echo '</table><br>';
				
				if (count($_profile->clubs) > 0)
				{
					echo '<table class="menu" width="100%"><tr><th class="menu">'.get_label('Game').'</th></tr>';
					echo '<tr><td class="menu"><a href="game.php?bck=0" title="'.get_label('Start the game').'">'.get_label('The game').'</a></td></tr>';
					echo '</table><br>';
				}
			}

			echo '</td><td valign="top">';
		}
		$this->_state = PAGE_STATE_HEADER;

		switch ($_session_state)
		{
/*			case SESSION_TIMEOUT:
				echo get_label('[0], your session has been expired. Please login to continue', cut_long_name($_profile->user_name, 110)) . ':<br>';
				echo '<input type="hidden" id="username" value="' . $_profile->user_name . '">';
				echo 'Password:&nbsp;<input type="password" id="password"><br>';
				echo '<input type="checkbox" id="remember" checked> ' . get_label('remember me') . '<br>';
				echo '<input value="Login" type="submit" class="btn norm" onclick="login()">';
				return false;*/
			case SESSION_LOGIN_FAILED:
				throw new FatalExc(get_label('Login attempt failed. Wrong username or password.'));
		}

		if (($permissions & $this->_permissions) == 0)
		{
			if (($permissions & PERM_STRANGER) == 0)
			{
				throw new FatalExc(get_label('No permissions'));
			}
			
			echo '<h3>'.get_label('You have to login to view this page').'.</h3>';
			return false;
		}
		return true;
	}

	final function show_footer()
	{
		global $brand, $_lang_code, $_agent;
		
		if ($this->_state != PAGE_STATE_HEADER)
		{
			return;
		}
		
		if (is_mobile())
		{
			if (!isset($this->no_selectors))
			{
				echo '<table class="transp" width="100%"><tr><td align="right" valign="top">';
				if (!isset($_SESSION['mobile']))
				{
					if ($_agent == AGENT_BROWSER)
					{
						$style = SITE_STYLE_DESKTOP;
					}
					else
					{
						$style = SITE_STYLE_MOBILE;
					}
				}
				else if ($_SESSION['mobile'])
				{
					$style = SITE_STYLE_MOBILE;
				}
				else
				{
					$style = SITE_STYLE_DESKTOP;
				}
				echo get_label('Site style') . ':&nbsp;<select id="mobile" onChange="mr.mobileStyleChange()">';
				show_option(SITE_STYLE_DESKTOP, $style, get_label('Desktop'));
				show_option(SITE_STYLE_MOBILE, $style, get_label('Mobile'));
				echo '</select>&nbsp;&nbsp;&nbsp;';
				
				echo get_label('Language') . ':&nbsp;<select id="browser_lang" onChange="mr.browserLangChange()">';
				$lang = LANG_NO;
				while (($lang = get_next_lang($lang)) != LANG_NO)
				{
					$code = get_lang_code($lang);
					echo '<option value="' . $code . '"';
					if ($code == $_lang_code)
					{
						echo ' selected';
					}
					echo '>' . get_lang_str($lang) . '</option>';
				 }
				echo '</select></td>';
				echo '</tr></table>';
			}
		}
		else
		{
			echo '</td></tr></table>';
			
			echo '<table border="0" cellpadding="5" cellspacing="0" width="' . PAGE_WIDTH . '" align="center">';
			echo '<tr><td class="header">';
			
			echo '<img src="images/transp.png" width="1" height="24">';
			
			// facebook like button
/*			if ($this->_facebook)
			{
				// echo '<script src="http://connect.facebook.net/en_US/all.js#xfbml=1"></script><fb:like href="' . $brand->metaurl . '" layout="button_count" show_faces="false" width="' . MENU_WIDTH . '"></fb:like>';
				echo '<iframe src="http://www.facebook.com/plugins/like.php?href=www.mafiaworld.ca&amp;layout=button_count&amp;show_faces=false&amp;width=450&amp;action=like&amp;font&amp;colorscheme=light&amp;height=21" scrolling="no" frameborder="0" style="border:none; overflow:hidden; width:450px; height:21px;" allowTransparency="true"></iframe>';
			}*/
			
			if (empty($_POST) && !isset($this->no_selectors))
			{
				echo '<td class="header" align="right" valign="top">';
				foreach ($_GET as $key => $value)
				{
					echo '<input type="hidden" name="' . $key . '" value="' . $value . '">';
				}
				
				if (!isset($_SESSION['mobile']))
				{
					if ($_agent == AGENT_BROWSER)
					{
						$style = SITE_STYLE_DESKTOP;
					}
					else
					{
						$style = SITE_STYLE_MOBILE;
					}
				}
				else if ($_SESSION['mobile'])
				{
					$style = SITE_STYLE_MOBILE;
				}
				else
				{
					$style = SITE_STYLE_DESKTOP;
				}
				echo get_label('Site style') . ':&nbsp;<select id="mobile" class="in-header short" onChange="mr.mobileStyleChange()">';
				show_option(SITE_STYLE_DESKTOP, $style, get_label('Desktop'));
				show_option(SITE_STYLE_MOBILE, $style, get_label('Mobile'));
				echo '</select>&nbsp;&nbsp;&nbsp;';
				
				echo get_label('Language') . ':&nbsp;<select id="browser_lang" class="in-header short" onChange="mr.browserLangChange()">';
				$lang = LANG_NO;
				while (($lang = get_next_lang($lang)) != LANG_NO)
				{
					show_option(get_lang_code($lang), $_lang_code, get_lang_str($lang));
				}
				echo '</select>';
			}
			
			echo '</td></tr></table>';
			// Correct fb_xd_fragment bug in IE
			// echo '<script>document.getElementsByTagName(\'html\')[0].style.display=\'block\';</script>';
			// ...
		}
		echo '<div id="dlg"></div>';
		echo '<div id="loading"><img style="margin-top:20px;" src="images/loading.gif" alt="' . get_label('Loading..') . '"/><h4>' . get_label('Please wait..') . '</h4></div>';
		echo '</body></html>';
		$this->_state = PAGE_STATE_FOOTER;
	}
	
	// service functions
	static function show_menu($menu, $id = 'menubar')
	{
		$url = substr(strtolower(get_page_name()), 1);
		if ($id != NULL)
		{
			echo '<ul id="' . $id . '" style="display:none;">';
		}
		else
		{
			echo '<ul style="display:none;">';
		}
		
		foreach ($menu as $item)
		{
			echo '<li';
			if (strpos($item->page, $url) !== false)
			{
				echo ' class="ui-state-disabled"';
			}
			echo '><a href="' . $item->page . '"';
			if ($item->title != NULL)
			{
				echo ' title="' . $item->title . '"';
			}
			echo '>' . $item->text . '</a>';
			if ($item->submenu != NULL)
			{
				PageBase::show_menu($item->submenu, NULL);
			}
			echo '</li>';
		}
		echo '</ul>';
	}
	
	// virtual section these functions should be overriden
	protected function prepare()
	{
	}
	
	protected function standard_title()
	{
		return '<h3>' . $this->_title . '</h3>';
	}
	
	protected function show_title()
	{
		echo '<table class="head" width="100%"><tr><td>' . $this->standard_title() . '</td><td align="right" valign="top">';
		show_back_button();
		echo '</td></tr></table>';	
	}
	
	protected function show_body()
	{
	}
	
	private function error($exc)
	{
		$message = str_replace('"', '\\"', $exc->getMessage());
		if ($this->_state != PAGE_STATE_EMPTY)
		{
			echo '<script> $(function() { dlg.error("' . $message . '"); }); </script>';
		}
		else
		{
			$this->_err_message = $message;
		}
	}
	
	private function _js()
	{
		global $_profile;
	
		echo "\n<script>";
		echo "\n\t$(function()";
		echo "\n\t{\n";
		if ($this->_err_message != NULL)
		{
			echo "\n\t\tdlg.error(\"" . $this->_err_message . "\");";
		}
		echo "\n\t\tshowMenuBar();\n\n";
		if ($_profile != NULL)
		{
			if ($_profile->user_flags & U_FLAG_NO_PASSWORD)
			{
				echo "\n\t\tmr.initProfile();\n\n";
			}
			else if ($_profile->user_flags & U_FLAG_DEACTIVATED)
			{
				echo "\n\t\tmr.activateProfile();\n\n";
			}
		}
		$this->js_on_load();
		echo "\n\t});\n";
		$this->js();
		echo "\n</script>";
	}
	
	protected function js_on_load()
	{
	}
	
	protected function js()
	{
	}
	
	protected function add_headers()
	{
	}
}

class MenuItem
{
	public $page;
	public $title;
	public $text;
	public $submenu;
	
	function __construct($page, $text, $title, $submenu = NULL)
	{
		$this->page = $page;
		$this->title = $title;
		$this->text = $text;
		$this->submenu = $submenu;
	}
}

class OptionsPageBase extends PageBase
{
	protected $user_flags;
	
	protected function prepare()
	{
		global $_profile;
		$this->user_flags = $_profile->user_flags;
	}

	protected function show_title()
	{
		global $_profile;
		
		$menu = array(
			new MenuItem('profile.php', get_label('My profile'), get_label('Change profile settings')),
			new MenuItem('change_email.php', get_label('Email'), get_label('Change profile email')),
			new MenuItem('change_password.php', get_label('Password'), get_label('Change password')));
			
		echo '<table class="transp" width="100%">';
		
		echo '<tr><td colspan="4">';
		PageBase::show_menu($menu);
		echo '</td></tr>';	
		
		echo '<tr><td valign="top">' . $this->standard_title() . '</td><td align="right" valign="top">';
		show_back_button();
		echo '</td><td valign="top" align="right" width="' . ICON_WIDTH . '">';	
		show_user_pic($_profile->user_id, $this->user_flags, ICONS_DIR);
		echo '</td></tr>';
		
		echo '</table>';
	}
}

class MailPageBase extends PageBase
{
	protected function show_title()
	{
		$menu = array(
			new MenuItem('inbox.php', get_label('Inbox'), get_label('Private messages to you')),
			new MenuItem('outbox.php', get_label('Outbox'), get_label('Your private messages')),
			new MenuItem('send_private.php', get_label('Send'), get_label('Send a private message')));

		echo '<table class="head" width="100%">';
		
		echo '<tr><td colspan="2">';
		PageBase::show_menu($menu);
		echo '</td></tr>';	
		
		echo '<tr><td valign="top">' . $this->standard_title() . '</td><td align="right" valign="top">';
		show_back_button();
		echo '</td></tr></table>';	
	}
}
	
/* template

class Page extends PageBase
{
	protected function prepare()
	{
		global $_profile;
	}
	
	protected function show_body()
	{
		global $_profile;
	}
}

$page = new Page();
$page->run(get_label(''), PERM_ALL);

*/

?>