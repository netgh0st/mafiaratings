<?php

require_once __DIR__ . '/page_base.php';
require_once __DIR__ . '/league.php';

define('ALL_CLUBS', -1);
define('MY_CLUBS', 0);

define('SEASON_ALL_TIME', -1);
define('SEASON_LAST_YEAR', -2);

function show_club_pic($club_id, $club_name, $flags, $dir, $width = 0, $height = 0, $attributes = NULL)
{
	global $_lang_code;
	$w = $width;
	$h = $height;
	if ($dir == ICONS_DIR)
	{
		if ($w <= 0)
		{
			$w = ICON_WIDTH;
		}
		if ($h <= 0)
		{
			$h = ICON_HEIGHT;
		}
	}
	else if ($dir == TNAILS_DIR)
	{
		if ($w <= 0)
		{
			$w = TNAIL_WIDTH;
		}
		if ($h <= 0)
		{
			$h = TNAIL_HEIGHT;
		}
	}
	
	if ($width <= 0 && $height <= 0)
	{
		$width = $w;
		$height = $h;
	}
	
	$origin = CLUB_PICS_DIR . $dir . $club_id . '.png';
	echo '<span style="position:relative;"><img code="' . CLUB_PIC_CODE . $club_id . '" origin="' . $origin . '" src="';
	if (($flags & CLUB_ICON_MASK) != 0)
	{
		echo $origin . '?' . (($flags & CLUB_ICON_MASK) >> CLUB_ICON_MASK_OFFSET);
	}
	else if ($club_id == NULL)
	{
		echo 'images/transp.png';
	}
	else
	{
		echo 'images/' . $dir . 'club.png';
	}
	echo '" title="' . $club_name . '" border="0"';

	if ($width > 0)
	{
		echo ' width="' . $width . '"';
	}
	if ($height > 0)
	{
		echo ' height="' . $height . '"';
	}
	if ($attributes != NULL)
	{
		echo ' ' . $attributes;
	}
	echo '>';
	if ($flags & CLUB_FLAG_RETIRED)
	{
		echo '<img src="images/' . $dir . $_lang_code . '/closed.png" title="' . $club_name . ' (' . get_label('closed') . ')" style="position:absolute; left:50%; margin-left:-' . ($w / 2) . 'px;"';
		if ($width > 0)
		{
			echo ' width="' . $width . '"';
		}
		if ($height > 0)
		{
			echo ' height="' . $height . '"';
		}
		echo '>';
	}
	echo '</span>';
}

function has_club_buttons($id, $flags, $memb_flags)
{
	global $_profile;
	return $_profile != NULL;
}

function show_club_buttons($id, $name, $flags, $memb_flags)
{
	global $_profile;

	$no_buttons = true;
	if ($_profile != NULL && $id > 0)
	{
		$can_manage = false;
		if (($flags & CLUB_FLAG_RETIRED) != 0)
		{
			if ($_profile->is_admin() || ($memb_flags != NULL && ($memb_flags & USER_CLUB_PERM_MANAGER) != 0))
			{
				echo '<button class="icon" onclick="mr.restoreClub(' . $id . ')" title="' . get_label('Restore [0]', $name) . '"><img src="images/undelete.png" border="0"></button>';
				$no_buttons = false;
			}
		}
		else 
		{
			$can_manage = $_profile->is_admin();
			if ($memb_flags != NULL)
			{
				$quit_params = $id;
				if ($memb_flags & USER_CLUB_PERM_MANAGER)
				{
					$quit_params .= ', \'' . get_label('You are a manager of this club. You loose your status once you leave it. Are you sure you want to quit?') . '\'';
				}
				else if ($memb_flags & USER_CLUB_PERM_MODER)
				{
					$quit_params .= ', \'' . get_label('You are a moderator of this club. You loose your status once you leave it. Are you sure you want to quit?') . '\'';
				}
			
				echo '<button class="icon" onclick="mr.quitClub(' . $quit_params . ')" title="' . get_label('Quit [0]', $name) . '"><img src="images/accept.png" border="0"></button>';
				$no_buttons = false;
				if ($memb_flags & USER_CLUB_PERM_MANAGER)
				{
					$can_manage = true;
				}
			}
			else
			{
				echo '<button class="icon" onclick="mr.joinClub(' . $id . ')" title="' . get_label('Join [0]', $name) . '"><img src="images/empty.png" border="0"></button>';
				$no_buttons = false;
			}
			
			if ($can_manage)
			{
				echo '<button class="icon" onclick="mr.editClub(' . $id . ')" title="' . get_label('Edit [0]', $name) . '"><img src="images/edit.png" border="0"></button>';
				echo '<button class="icon" onclick="mr.retireClub(' . $id . ')" title="' . get_label('Retire [0]', $name) . '"><img src="images/delete.png" border="0"></button>';
				$no_buttons = false;
			}
			
			if ($_profile->is_admin() || ($memb_flags & USER_CLUB_PERM_MODER) != 0)
			{
				echo '<button class="icon" onclick="mr.playClub(' . $id . ')" title="' . get_label('Play the game') . '"><img src="images/game.png" border="0"></button>';
				$no_buttons = false;
			}
		}
	}
	
	if ($no_buttons)
	{
		echo '<img src="images/transp.png" height="26">';
	}
}

class ClubPageBase extends PageBase
{
	protected $id;
	protected $name;
	protected $flags;
	protected $langs;
	protected $rules_code;
	protected $url;
	protected $email;
	protected $phone;
	protected $price;
	protected $country;
	protected $city;
	protected $scoring_id;
	protected $memb_flags;
	protected $is_manager;
	protected $is_moder;
	protected $timezone;
	protected $parent_id;
	protected $parent_name;
	protected $parent_flags;
	
	protected function prepare()
	{
		global $_lang_code, $_profile;
	
		if (!isset($_REQUEST['id']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('club')));
		}
		$this->id = $_REQUEST['id'];

		$this->is_manager = false;
		$this->is_moder = false;
		$user_id = -1;
		if ($_profile != NULL)
		{
			$user_id = $_profile->user_id;
			$this->is_manager = $_profile->is_club_manager($this->id);
			$this->is_moder = $_profile->is_club_moder($this->id);
		}
		
		list ($this->name, $this->flags, $this->url, $this->langs, $this->rules_code, $this->email, $this->phone, $this->price, $this->country, $this->city, $this->memb_flags, $this->scoring_id, $this->timezone, $this->parent_id, $this->parent_name, $this->parent_flags) = 
			Db::record(
				get_label('club'),
				'SELECT c.name, c.flags, c.web_site, c.langs, c.rules, c.email, c.phone, c.price, cr.name_' . $_lang_code . ', ct.name_' . $_lang_code . ', u.flags, c.scoring_id, ct.timezone, p.id, p.name, p.flags FROM clubs c ' .
					'JOIN cities ct ON ct.id = c.city_id ' .
					'JOIN countries cr ON cr.id = ct.country_id ' .
					'LEFT OUTER JOIN user_clubs u ON u.club_id = c.id AND u.user_id = ? ' .
					'LEFT OUTER JOIN clubs p ON c.parent_id = p.id ' .
					'WHERE c.id = ?',
				$user_id, $this->id);
	}

	protected function show_title()
	{
		global $_profile;
		
		$menu = array
		(
			new MenuItem('club_main.php?id=' . $this->id, get_label('Club'), get_label('[0] main page', $this->name))
			, new MenuItem('club_standings.php?id=' . $this->id, get_label('Standings'), get_label('[0] standings', $this->name))
			, new MenuItem('club_competition.php?id=' . $this->id, get_label('Competition chart'), get_label('How players were competing in the club.'))
			, new MenuItem('club_events.php?id=' . $this->id, get_label('Events'), get_label('[0] events history', $this->name))
			, new MenuItem('club_games.php?id=' . $this->id, get_label('Games'), get_label('Games list of [0]', $this->name))
			, new MenuItem('#stats', get_label('Stats'), NULL, array
			(
				new MenuItem('club_stats.php?id=' . $this->id, get_label('General stats'), get_label('General statistics. How many games played, mafia winning percentage, how many players, etc.', PRODUCT_NAME))
				, new MenuItem('club_by_numbers.php?id=' . $this->id, get_label('By numbers'), get_label('Statistics by table numbers. What is the most winning number, or what number is shot more often.'))
				, new MenuItem('club_nominations.php?id=' . $this->id, get_label('Nomination winners'), get_label('Custom nomination winners. For example who had most warnings, or who was checked by sheriff most often.'))
				, new MenuItem('club_moderators.php?id=' . $this->id, get_label('Moderators'), get_label('Moderators statistics of [0]', $this->name))
			))
			, new MenuItem('#resources', get_label('Resources'), NULL, array
			(
				new MenuItem('club_rules.php?id=' . $this->id, get_label('Rulebook'), get_label('Rules of the game in [0]', $this->name))
				, new MenuItem('club_albums.php?id=' . $this->id, get_label('Photos'), get_label('Photo albums'))
				, new MenuItem('club_videos.php?id=' . $this->id . '&vtype=' . VIDEO_TYPE_GAME, get_label('Game videos'), get_label('Game videos from various tournaments.'))
				, new MenuItem('club_videos.php?id=' . $this->id . '&vtype=' . VIDEO_TYPE_LEARNING, get_label('Learning videos'), get_label('Masterclasses, lectures, seminars.'))
				// , new MenuItem('club_tasks.php?id=' . $this->id, get_label('Tasks'), get_label('Learning tasks and puzzles.'))
				// , new MenuItem('club_articles.php?id=' . $this->id, get_label('Articles'), get_label('Books and articles.'))
				// , new MenuItem('club_links.php?id=' . $this->id, get_label('Links'), get_label('Links to custom mafia web sites.'))
			))
		);
			
		if ($this->is_manager || $this->is_moder)
		{
			$managment_menu = array(new MenuItem('club_users.php?id=' . $this->id, get_label('Members'), get_label('[0] members', $this->name)));
			if ($this->is_manager)
			{
				$managment_menu[] = new MenuItem('club_upcoming_tournaments.php?id=' . $this->id, get_label('Tournaments'), get_label('[0] upcoming tournaments', $this->name));
				$managment_menu[] = new MenuItem('club_upcoming_events.php?id=' . $this->id, get_label('Events'), get_label('[0] upcoming events', $this->name));
				$managment_menu[] = new MenuItem('club_addresses.php?id=' . $this->id, get_label('Addresses'), get_label('[0] addresses', $this->name));
				$managment_menu[] = new MenuItem('club_seasons.php?id=' . $this->id, get_label('Seasons'), get_label('[0] seasons', $this->name));
				$managment_menu[] = new MenuItem('club_adverts.php?id=' . $this->id, get_label('Adverts'), get_label('[0] adverts', $this->name));
				$managment_menu[] = new MenuItem('club_custom_rules.php?id=' . $this->id, get_label('Rules'), get_label('[0] game rules', $this->name));
				$managment_menu[] = new MenuItem('club_scorings.php?id=' . $this->id, get_label('Scoring systems'), get_label('Alternative methods of calculating points for [0]', $this->name));
				$managment_menu[] = new MenuItem('club_emails.php?id=' . $this->id, get_label('Emails'), get_label('[0] email templates', $this->name));
				$managment_menu[] = new MenuItem('club_log.php?id=' . $this->id, get_label('Log'), get_label('[0] log', $this->name));
			}
			$menu[] = new MenuItem('#other', get_label('Management'), NULL, $managment_menu);
		}
		
		echo '<table class="head" width="100%">';
		
		echo '<tr><td colspan="3">';
		PageBase::show_menu($menu);
		echo '</td></tr>';
		if (($this->flags & CLUB_FLAG_RETIRED) != 0)
		{
			$dark = 'darker';
			$light = 'dark';
		}
		else
		{
			$dark = 'dark';
			$light = 'light';
		}
		
		echo '<tr><td width="1" rowspan="2"><table class="bordered"><tr><td class="' . $dark . '" valign="top" style="min-width:28px; padding:4px;">';
		show_club_buttons($this->id, $this->name, $this->flags, $this->memb_flags);
		echo '</td><td class="' . $light . '" style="min-width:' . TNAIL_WIDTH . 'px; padding: 4px 3px 1px 4px;">';
		if ($this->url != '')
		{
			echo '<a href="' . $this->url . '" target="blank">';
			show_club_pic($this->id, $this->name, $this->flags, TNAILS_DIR);
			echo '</a>';
		}
		else
		{
			show_club_pic($this->id, $this->name, $this->flags, TNAILS_DIR);
		}
		echo '</td></tr></table><td valign="top" rowspan="2"><h2 class="club">' . get_label('Club [0]', $this->_title) . '</h2><br><h3>' . $this->name . '</h3><p class="subtitle">' . $this->city . ', ' . $this->country . '</p></td><td valign="top" align="right">';
		show_back_button();
		echo '</td></tr>';
		echo '</tr><td align="right" valign="bottom">';
		if ($this->parent_id != NULL)
		{
			echo '<a href="club_main.php?bck=1&id=' . $this->parent_id . '">';
			show_club_pic($this->parent_id, get_label('[0] is a member of [1] club system.', $this->name, $this->parent_name), $this->parent_flags, ICONS_DIR, 36, 36);
			echo '</a>';
		}
		$query = new DbQuery('SELECT l.id, l.name, l.flags FROM league_clubs c JOIN leagues l ON l.id = c.league_id WHERE c.flags = 0 AND c.club_id = ? ORDER BY l.name', $this->id);
		while ($row = $query->next())
		{
			list($league_id, $league_name, $league_flags) = $row;
			echo ' <a href="league_main.php?bck=1&id=' . $league_id . '">';
			show_league_pic($league_id, get_label('[0] is a member of [1].', $this->name, $league_name), $league_flags, ICONS_DIR, 36, 36);
			echo '</a>';
		}
		echo '</td></tr>';
		echo '</table>';
	}
}

function get_current_club_season($club_id)
{
	$condition = new SQL();
	if ($club_id > 0)
	{
		$query = new DbQuery('SELECT id, name, start_time, end_time FROM seasons WHERE club_id = ? AND start_time < UNIX_TIMESTAMP() ORDER BY end_time DESC', $club_id);
		while ($row = $query->next())
		{
			return (int)$row[0];
		}
		$condition->add(' AND g.club_id = ?', $club_id);
	}
	
	$query = new DbQuery('SELECT g.end_time, c.timezone FROM games g JOIN events e ON e.id = g.event_id JOIN addresses a ON a.id = e.address_id JOIN cities c ON c.id = a.city_id WHERE result > 0', $condition);
	$query->add(' ORDER BY g.end_time DESC LIMIT 1');
	if ($row = $query->next())
	{
		list($timestamp, $timezone) = $row;
		date_default_timezone_set($timezone);
		$last_year = (int)date('Y', $timestamp);
		return -$last_year;
	}
	return SEASON_LAST_YEAR;
}

function show_club_seasons_select($club_id, $option, $on_change, $title)
{
	$seasons = array();
	$condition = new SQL();
	if ($club_id > 0)
	{
		$query = new DbQuery('SELECT id, name, start_time, end_time FROM seasons WHERE club_id = ? AND start_time < UNIX_TIMESTAMP() ORDER BY end_time DESC', $club_id);
		while ($row = $query->next())
		{
			$seasons[] = $row;
		}
		$condition->add(' AND g.club_id = ?', $club_id);
	}
	
	if ($option == 0 && count($seasons) > 0)
	{
		$option = $seasons[0][0];
	}
	echo '<select name="season" id="season" onChange="' . $on_change . '" title="' . $title . '">';
	show_option(SEASON_ALL_TIME, $option, get_label('All time'));
	show_option(SEASON_LAST_YEAR, $option, get_label('Last year'), get_label('Since the same day a year ago.'));
	if (count($seasons) > 0)
	{
		foreach ($seasons as $season)
		{
			list($id, $name, $start, $end) = $season;
			show_option($id, $option, $name);
		}
	}
	else
	{
		$query = new DbQuery('SELECT g.start_time, c.timezone FROM games g JOIN events e ON e.id = g.event_id JOIN addresses a ON a.id = e.address_id JOIN cities c ON c.id = a.city_id WHERE result > 0', $condition);
		$query->add(' ORDER BY g.start_time LIMIT 1');
		if ($row = $query->next())
		{
			list($timestamp, $timezone) = $row;
			date_default_timezone_set($timezone);
			$first_year = (int)date('Y', $timestamp);
			
			$query = new DbQuery('SELECT g.end_time, c.timezone FROM games g JOIN events e ON e.id = g.event_id JOIN addresses a ON a.id = e.address_id JOIN cities c ON c.id = a.city_id WHERE result > 0', $condition);
			$query->add(' ORDER BY g.end_time DESC LIMIT 1');
			if ($row = $query->next())
			{
				list($timestamp, $timezone) = $row;
				date_default_timezone_set($timezone);
				$last_year = (int)date('Y', $timestamp);
				if ($option == 0)
				{
					$option = -$last_year;
				}
				for ($y = $last_year; $y >= $first_year; --$y)
				{
					show_option(-$y, $option, $y);
				}
			}
		}
	}
	echo '</select> ';
	return $option;
}

function get_club_season_condition($season, $start_field, $end_field)
{
	$condition = new SQL('');
	if ($season > 0)
	{
		$condition->add(' AND EXISTS(SELECT _s.id FROM seasons _s WHERE _s.start_time <= ' . $end_field . ' AND _s.end_time > ' . $start_field . ' AND _s.id = ?)', $season);
	}
	else if ($season < SEASON_ALL_TIME)
	{
		if ($season == SEASON_LAST_YEAR)
		{
			$condition->add(' AND ' . $end_field . ' >= UNIX_TIMESTAMP() - 31536000');
		}
		else
		{
			$start = mktime(0, 0, 0, 1, 1, -$season);
			$end = mktime(0, 0, 0, 1, 1, 1 - $season);
			$condition->add(' AND ' . $end_field . ' >= ? AND ' . $start_field . ' < ?', $start, $end);
		}
	}
	return $condition;
}

function get_club_request_action($name, $club_id, $club_name, $parent_id, $parent_name)
{
	if ($club_id != NULL)
	{
		if ($parent_id > 0)
		{
			return get_label('Move [0] to [1] club system', $club_name, $parent_name);
		}
		return get_label('Make [0] a top level club', $club_name);
	}
	else if ($parent_id > 0)
	{
		return get_label('Create a club [0] in [1] club system', $name, $parent_name);
	}
	return get_label('Create a top level club [0]', $name);
}

?>