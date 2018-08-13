<?php

require_once '../../include/api.php';
require_once '../../include/game_state.php';
require_once '../../include/game.php';

define('CURRENT_VERSION', 0);

class ApiPage extends GetApiPageBase
{
	protected function prepare_response()
	{
		$after = 0;
		if (isset($_REQUEST['after']))
		{
			$after = (int)$_REQUEST['after'];
		}
		
		$before = 0;
		if (isset($_REQUEST['before']))
		{
			$before = (int)$_REQUEST['before'];
		}
		
		$club = 0;
		if (isset($_REQUEST['club']))
		{
			$club = (int)$_REQUEST['club'];
		}
		
		$event = 0;
		if (isset($_REQUEST['event']))
		{
			$event = (int)$_REQUEST['event'];
		}
		
		$user = 0;
		if (isset($_REQUEST['user']))
		{
			$user = (int)$_REQUEST['user'];
		}
		
		$langs = LANG_ALL;
		if (isset($_REQUEST['langs']))
		{
			$langs = (int)$_REQUEST['langs'];
		}
		
		$game = 0;
		if (isset($_REQUEST['game']))
		{
			$game = (int)$_REQUEST['game'];
		}
		
		$address = 0;
		if (isset($_REQUEST['address']))
		{
			$address = (int)$_REQUEST['address'];
		}
		
		$country = 0;
		if (isset($_REQUEST['country']))
		{
			$country = (int)$_REQUEST['country'];
		}
		
		$area = 0;
		if (isset($_REQUEST['area']))
		{
			$area = (int)$_REQUEST['area'];
		}
		
		$city = 0;
		if (isset($_REQUEST['city']))
		{
			$city = (int)$_REQUEST['city'];
		}
		
		$page_size = 16;
		if (isset($_REQUEST['page_size']))
		{
			$page_size = (int)$_REQUEST['page_size'];
		}
		
		$page = 0;
		if (isset($_REQUEST['page']))
		{
			$page = (int)$_REQUEST['page'];
		}
		
		$count_only = isset($_REQUEST['count']);
		
		$condition = new SQL('');
		if ($before > 0)
		{
			$condition->add(' AND g.start_time < ?', $before);
		}

		if ($after > 0)
		{
			$condition->add(' AND g.start_time >= ?', $after);
		}

		if ($club > 0)
		{
			$condition->add(' AND g.club_id = ?', $club);
		}

		if ($game > 0)
		{
			$condition->add(' AND g.id = ?', $game);
		}
		else if ($event > 0)
		{
			$condition->add(' AND g.event_id = ?', $event);
		}
		else if ($address > 0)
		{
			$condition->add(' AND g.event_id IN (SELECT id FROM events WHERE address_id = ?)', $address);
		}
		else if ($city > 0)
		{
			$condition->add(' AND g.event_id IN (SELECT e1.id FROM events e1 JOIN addresses a1 ON e1.address_id = a1.id WHERE a1.city_id = ?)', $city);
		}
		else if ($area > 0)
		{
			$condition->add(' AND g.event_id IN (SELECT e1.id FROM events e1 JOIN addresses a1 ON e1.address_id = a1.id JOIN cities c1 ON a1.city_id = c1.id WHERE c1.area_id = (SELECT area_id FROM cities WHERE id = ?))', $area);
		}
		else if ($country > 0)
		{
			$condition->add(' AND g.event_id IN (SELECT e1.id FROM events e1 JOIN addresses a1 ON e1.address_id = a1.id JOIN cities c1 ON a1.city_id = c1.id WHERE c1.country_id = ?)', $country);
		}
		
		if ($langs != LANG_ALL)
		{
			$condition->add(' AND (g.language & ?) <> 0', $langs);
		}
		
		$condition->add(' ORDER BY g.start_time DESC');
		
		if ($user > 0)
		{
			$count_query = new DbQuery('SELECT count(*) FROM players p JOIN games g ON p.game_id = g.id WHERE g.result IN(1,2) AND p.user_id = ?', $user, $condition);
			$query = new DbQuery('SELECT g.id, g.log FROM players p JOIN games g ON  p.game_id = g.id WHERE g.result IN(1,2) AND p.user_id = ?', $user, $condition);
		}
		else
		{
			$count_query = new DbQuery('SELECT count(*) FROM games g WHERE g.result IN(1,2)', $condition);
			$query = new DbQuery('SELECT g.id, g.log FROM games g WHERE g.result IN(1,2)', $condition);
		}
		
		list ($count) = $count_query->record('game');
		$this->response['count'] = (int)$count;
		if (!$count_only)
		{
			if ($page_size > 0)
			{
				$query->add(' LIMIT ' . ($page * $page_size) . ',' . $page_size);
			}
			
			$games = array();
			while ($row = $query->next())
			{
				list ($id, $log) = $row;
				$gs = new GameState();
				$gs->init_existing($id, $log);
				$game = new Game($gs);
				$games[] = $game;
			}
			$this->response['games'] = $games;
		}
	}
	
	protected function get_help()
	{
		$help = new ApiHelp();
		$help->request_param('before', 'Unix timestamp for the latest game to return. For example: <a href="game_test.php?before=1483228800"><?php echo PRODUCT_URL; ?>/api/get/game_test.php?before=1483228800</a> returns all games started before 2017', '-');
		$help->request_param('after', 'Unix timestamp for the earliest game to return. For example: <a href="game_test.php?after=1483228800"><?php echo PRODUCT_URL; ?>/api/get/game_test.php?after=1483228800</a> returns all games started after January 1, 2017 inclusive; <a href="game_test.php?after=1483228800&before=1485907200"><?php echo PRODUCT_URL; ?>/api/get/game_test.php?after=1483228800&before=1485907200</a> returns all games played in January 2017. (Using start time - if the game ended in February but started in January it is still a January game).', '-');
		$help->request_param('club', 'Club id. For example: <a href="game_test.php?club=1"><?php echo PRODUCT_URL; ?>/api/get/game_test.php?club=1</a> returns all games for Vancouver Mafia Club. If missing, all games for all clubs are returned.', '-');
		$help->request_param('game', 'Game id. For example: <a href="game_test.php?game=1299"><?php echo PRODUCT_URL; ?>/api/get/game_test.php?game=1299</a> returns only one game played in VaWaCa-2017 tournament.', '-');
		$help->request_param('event', 'Event id. For example: <a href="game_test.php?event=7927"><?php echo PRODUCT_URL; ?>/api/get/game_test.php?event=7927</a> returns all games for VaWaCa-2017 tournament. If missing, all games for all events are returned.', '-');
		$help->request_param('address', 'Address id. For example: <a href="game_test.php?address=10"><?php echo PRODUCT_URL; ?>/api/get/game_test.php?address=10</a> returns all games played in Tafs Cafe by Vancouver Mafia Club.', '-');
		$help->request_param('city', 'City id. For example: <a href="game_test.php?city=49"><?php echo PRODUCT_URL; ?>/api/get/game_test.php?city=49</a> returns all games played in Seattle. List of the cities and their ids can be obtained using <a href="cities.php?help"><?php echo PRODUCT_URL; ?>/api/get/cities.php</a>.', '-');
		$help->request_param('area', 'City id. The difference with city is that when area is set, the games from all nearby cities are also returned. For example: <a href="game_test.php?area=1"><?php echo PRODUCT_URL; ?>/api/get/game_test.php?area=1</a> returns all games played in Vancouver and nearby cities. Though <a href="game_test.php?city=1"><?php echo PRODUCT_URL; ?>/api/get/game_test.php?city=1</a> returns only the games played in Vancouver itself.', '-');
		$help->request_param('country', 'Country id. For example: <a href="game_test.php?country=2"><?php echo PRODUCT_URL; ?>/api/get/game_test.php?country=2</a> returns all games played in Russia. List of the countries and their ids can be obtained using <a href="countries.php?help"><?php echo PRODUCT_URL; ?>/api/get/countries.php</a>.', '-');
		$help->request_param('user', 'User id. For example: <a href="game_test.php?user=25"><?php echo PRODUCT_URL; ?>/api/get/game_test.php?user=25</a> returns all games where Fantomas played. If missing, all games for all users are returned.', '-');
		$help->request_param('langs', 'Languages filter. 1 for English; 2 for Russian. Bit combination - 3 - means both (this is a default value). For example: <a href="game_test.php?langs=1"><?php echo PRODUCT_URL; ?>/api/get/game_test.php?langs=1</a> returns all games played in English; <a href="game_test.php?club=1&langs=3"><?php echo PRODUCT_URL; ?>/api/get/game_test.php?club=1&langs=3</a> returns all English and Russian games of Vancouver Mafia Club', '-');
		$help->request_param('count', 'Returns game count but does not return the games. For example: <a href="game_test.php?user=25&count"><?php echo PRODUCT_URL; ?>/api/get/game_test.php?user=25&count</a> returns how many games Fantomas have played; <a href="game_test.php?event=7927&count"><?php echo PRODUCT_URL; ?>/api/get/game_test.php?event=7927&count</a> returns how many games were played in VaWaCa-2017 tournament.', '-');
		$help->request_param('page', 'Page number. For example: <a href="game_test.php?club=1&page=1"><?php echo PRODUCT_URL; ?>/api/get/game_test.php?club=1&page=1</a> returns the second page for Vancouver Mafia Club.', '-');
		$help->request_param('page_size', 'Page size. Default page_size is 16. For example: <a href="game_test.php?club=1&page_size=32"><?php echo PRODUCT_URL; ?>/api/get/game_test.php?club=1&page_size=32</a> returns last 32 games for Vancouver Mafia Club; <a href="game_test.php?club=6&page_size=0"><?php echo PRODUCT_URL; ?>/api/get/game_test.php?club=6&page_size=0</a> returns all games for Empire of Mafia club in one page; <a href="game_test.php?club=1"><?php echo PRODUCT_URL; ?>/api/get/game_test.php?club=1</a> returns last 16 games for Vancouver Mafia Club;', '-');

		$param = $help->response_param('games', 'The array of games. Games are always sorted from latest to oldest. There is no way to change sorting order in the current version of the API.');
			$param->sub_param('id', 'Game id. Unique game identifier.');
			$param->sub_param('club_id', 'Club id. Unique club identifier.');
			$param->sub_param('event_id', 'Event id. Unique event identifier.');
			$param->sub_param('start_time', 'Unix timestamp for the game start.');
			$param->sub_param('end_time', 'Unix timestamp for the game end.');
			$param->sub_param('language', 'Language of the game. Possible values are: 1 for English; 2 for Russian. Other languages are not supported in the current version.');
			$param->sub_param('moderator_id', 'User id of the user who moderated the game.');
			$param->sub_param('winner', 'Who won the game. Possible values: "civ" or "maf". Tie is not supported in the current version.');
			$param1 = $param->sub_param('players', 'The array of players who played. Array size is always 10. Players index in the array matches their number at the table.');
				$param1->sub_param('user_id', 'User id. <i>Optional:</i> missing when someone not registered in mafiaratings played.');
				$param1->sub_param('nick_name', 'Nick name used in this game.');
				$param1->sub_param('role', 'One of: "civ", "maf", "srf", or "don".');
				$param1->sub_param('death_round', 'The round number (starting from 0) when this player was killed. <i>Optional:</i> missing if the player survived.');
				$param1->sub_param('death_type', 'How this player was killed. Possible values: "day" - killed by day votings; "night" - killed by night shooting; "warning" - killed by 4th warning; "suicide" - left the game by theirself; "kick-out" - kicked out by the moderator. <i>Optional:</i> missing if the player survived.');
				$param1->sub_param('warnings', 'Number of warnings. <i>Optional:</i> missing when 0.');
				$param1->sub_param('arranged_for_round', 'Was arranged by mafia to be shooted down in the round (starting from 0). <i>Optional:</i> missing when the player was not arranged.');
				$param1->sub_param('checked_by_don', 'The round (starting from 0) when the don checked this player. <i>Optional:</i> missing when the player was not checked by the don.');
				$param1->sub_param('checked_by_srf', 'The round (starting from 0) when the sheriff checked this player. <i>Optional:</i> missing when the player was not checked by the sheriff.');
				$param1->sub_param('best_player', 'True if this is the best player. <i>Optional:</i> missing when false.');
				$param1->sub_param('best_move', 'True if the player did the best move of the game. <i>Optional:</i> missing when false.');
				$param1->sub_param('mafs_guessed', 'Number of mafs guessed right by the player killed the first night. <i>Optional:</i> missing when player was not killed in night 0, or when they guessed wrong.');
				$param1->sub_param('voting', 'How the player was voting. An assotiated array in the form <i>round_N: M</i>. Where N is day number (starting from 0); M is the number of player for whom he/she voted (0 to 9).');
				$param1->sub_param('nominating', 'How the player was nominating. An assotiated array in the form <i>round_N: M</i>. Where N is day number (starting from 0); M is the number of player who was nominated (0 to 9).');
				$param1->sub_param('shooting', 'For mafia only. An assotiated array in the form <i>round_N: M</i>. . Where N is day number (starting from 0); M is the number of player who was nominated (0 to 9). For example: { round_0: 0, round_1: 8, round_2: 9 } means that this player was shooting player 1(index 0) the first night; player 9 the second night; and player 10 the third night.');
		$help->response_param('count', 'The total number of games sutisfying the request parameters. It is set only when the parameter <i>count</i> is set.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Get Games', CURRENT_VERSION);

?>