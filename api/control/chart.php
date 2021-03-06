<?php

require_once '../../include/api.php';
require_once '../../include/chart.php';
require_once '../../include/scoring.php';
require_once '../../include/club.php';

define('MAX_POINTS_ON_GRAPH', 50);
define('MIN_PERIOD', 24*60*60);

class ApiPage extends ControlApiPageBase
{
	protected function prepare_response()
	{
		global $_chart_colors;
		
		if (!isset($_REQUEST['type']))
		{
			throw new Exc(get_label('Unknown [0]', get_label('chart type')));
		}
		$type = $_REQUEST['type'];
		
		$chart_count = MAX_CHARTS_COUNT;
		if (isset($_REQUEST['charts']))
		{
			$chart_count = (int)$_REQUEST['charts'];
			if ($chart_count <= 0 || $chart_count > MAX_CHARTS_COUNT)
			{
				$chart_count = MAX_CHARTS_COUNT;
			}
		}
		
		if (!isset($_REQUEST['players']))
		{
			throw new Exc(get_label('Unknown [0]', get_label('player')));
		}
		$player_list = $_REQUEST['players'];
		$user_ids = chart_list_to_array($player_list, $chart_count);
		$player_list = chart_array_to_list($user_ids, 0);
		
		$name = '';
		if (isset($_REQUEST['name']))
		{
			$name = $_REQUEST['name'];
		}
		
		if (!empty($player_list))
		{
			$current_color = 0;
			if ($type == 'rating')
			{
				date_default_timezone_set(get_timezone());
				
				foreach ($user_ids as $user_id)
				{
					$this->response[] = new ChartData('', $_chart_colors[$current_color++]);
				}
			
				list($min_time, $max_time) = Db::record(get_label('game'), 'SELECT MIN(g.end_time), MAX(g.end_time) FROM players p JOIN games g ON p.game_id = g.id WHERE p.user_id IN (' . $player_list . ')');
				if ($min_time != NULL && $max_time != NULL) // || $max_time - $min_time < MIN_PERIOD_ON_GRAPH)
				{
					$period = floor(($max_time - $min_time) / MAX_POINTS_ON_GRAPH);
					if ($period <= 0)
					{
						$period = MIN_PERIOD;
					}
					$query = new DbQuery('SELECT u.id, CEILING(g.end_time/' . $period . ') * ' . $period . ' as period, u.name, SUM(p.rating_earned) FROM players p JOIN games g ON p.game_id = g.id JOIN users u ON p.user_id = u.id WHERE u.id IN (' . $player_list . ') GROUP BY u.id, period ORDER BY u.id, period');
					
					$current_user_id = -1;
					while ($row = $query->next())
					{
						list ($user_id, $timestamp, $user_name, $rating) = $row;
						if ($current_user_id != $user_id)
						{
							for ($index = 0; $index < $chart_count; ++$index)
							{
								if ($user_ids[$index] == $user_id)
								{
									break;
								}
							}
							
							if ($index < $chart_count)
							{
								$data = $this->response[$index];
								$data->label = $user_name;
								$data->add_point($timestamp - $period, 0);
							}
							else
							{
								$data = NULL;
							}
							$current_user_id = $user_id;
						}
						
						if ($data != NULL)
						{
							$data->add_point($timestamp, $rating);
						}
					}
				}
			}
			else if ($type == 'event')
			{
				if (!isset($_REQUEST['id']))
				{
					throw new FatalExc(get_label('Unknown [0]', get_label('event')));
				}
				$event_id = (int)$_REQUEST['id'];
				
				list($scoring_id, $scoring_weight, $timezone) = Db::record(get_label('event'), 'SELECT e.scoring_id, e.scoring_weight, c.timezone FROM events e JOIN addresses a ON a.id = e.address_id JOIN cities c ON c.id = a.city_id WHERE e.id = ?', $event_id);
				if (isset($_REQUEST['scoring']))
				{
					$sid = (int)$_REQUEST['scoring'];
					if ($sid > 0)
					{
						$scoring_id = $sid;
					}
				}
				date_default_timezone_set($timezone);
				
				$rounds = array();
				$round = new stdClass();
				$round->scoring_weight = $scoring_weight;
				$round->scoring_id = $scoring_id;
				$rounds[] = $round;
				$query = new DbQuery('SELECT scoring_id, scoring_weight FROM rounds r WHERE event_id = ? ORDER BY num', $event_id);
				while ($row = $query->next())
				{
					$round = new stdClass();
					list($round->scoring_id, $round->scoring_weight) = $row;
					$rounds[] = $round;
				}
				
				$scoring_system = new ScoringSystem($scoring_id);
				$scores = new Scores($scoring_system, $rounds, new SQL(' AND g.event_id = ?', $event_id), new SQL(' AND p.user_id IN(' . $player_list . ')'), MAX_POINTS_ON_GRAPH);
		
				$players_count = count($scores->players);
				foreach ($user_ids as $user_id)
				{
					if ($user_id > 0)
					{
						$player = NULL;
						for ($i = 0; $i < $players_count; ++$i)
						{
							if ($scores->players[$i]->id == $user_id)
							{
								$player = $scores->players[$i];
								break;
							}
						}
						
						if ($player != NULL)
						{
							$data = new ChartData($player->name, $_chart_colors[$current_color]);
							foreach ($player->history as $point)
							{
								$data->data[] = new ChartPoint($point->timestamp, $point->points);
							}
							$this->response[] = $data;
						}
					}
					++$current_color;
				}
			}
			else if ($type == 'club')
			{
				if (!isset($_REQUEST['id']))
				{
					throw new FatalExc(get_label('Unknown [0]', get_label('event')));
				}
				$club_id = (int)$_REQUEST['id'];
				
				list($scoring_id, $timezone) = Db::record(get_label('event'), 'SELECT c.scoring_id, ct.timezone FROM clubs c JOIN cities ct ON ct.id = c.city_id WHERE c.id = ?', $club_id);
				if (isset($_REQUEST['scoring']))
				{
					$sid = (int)$_REQUEST['scoring'];
					if ($sid > 0)
					{
						$scoring_id = $sid;
					}
				}
				date_default_timezone_set($timezone);
				
				if (isset($_REQUEST['scoring']))
				{
					$sid = (int)$_REQUEST['scoring'];
					if ($sid > 0)
					{
						$scoring_id = $sid;
					}
				}
				
				$season = 0;
				if (isset($_REQUEST['season']))
				{
					$season = (int)$_REQUEST['season'];
				}
				if ($season == 0)
				{
					$season = get_current_club_season($club_id);
				}
				
				$scoring_system = new ScoringSystem($scoring_id);
				$scores = new Scores($scoring_system, NULL, new SQL(' AND g.club_id = ?', $club_id), new SQL(' AND p.user_id IN(' . $player_list . ')', get_club_season_condition($season, 'g.start_time', 'g.end_time')), MAX_POINTS_ON_GRAPH);
		
				$players_count = count($scores->players);
				foreach ($user_ids as $user_id)
				{
					if ($user_id > 0)
					{
						$player = NULL;
						for ($i = 0; $i < $players_count; ++$i)
						{
							if ($scores->players[$i]->id == $user_id)
							{
								$player = $scores->players[$i];
								break;
							}
						}
						
						if ($player != NULL)
						{
							$data = new ChartData($player->name, $_chart_colors[$current_color]);
							foreach ($player->history as $point)
							{
								$data->data[] = new ChartPoint($point->timestamp, $point->points);
							}
							$this->response[] = $data;
						}
					}
					++$current_color;
				}
			}
		}
	}
	
	protected function show_help()
	{
		$this->show_help_title();
		$this->show_help_request_params_head();
?>
		<dt>type</dt>
			<dd>
				Type of the chart. Possible values are:
				<ul>
					<li>rating - returns chart data for global ratings. For example: <a href="chart.php?type=rating&players=264"><?php echo PRODUCT_URL; ?>/api/control/chart.php?type=rating&players=264</a> returns Tigra rating all time chart data.</li>
					<li>event - returns chart data for event points. For example: <a href="chart.php?type=event&id=7927&players=264"><?php echo PRODUCT_URL; ?>/api/control/chart.php?type=event&id=7927&players=264</a> returns Tigra scoring chart data during VaWaCa-2017.</li>
					<li>club - returns chart data for club points. For example: <a href="chart.php?type=club&id=1&players=264"><?php echo PRODUCT_URL; ?>/api/control/chart.php?type=club&id=1&players=264</a> returns Tigra current season scoring chart data in Vancouver Mafia Club.</li>
				</ul>
			</dd>
		<dt>players</dt>
			<dd>Comma separated list of player ids. For example: <a href="chart.php?type=rating&players=264,25,137"><?php echo PRODUCT_URL; ?>/api/control/chart.php?type=rating&players=264,25,137</a> returns all time rating chart data for three players: Tigra, Fantomas, and Alena respectively.</dd>
		<dt>id</dt>
			<dd>When the type is "event" or "club", this param must contain id of the respective object.</dd>
		<dt>scoring</dt>
			<dd>When the type is "event" or "club", this param can contain id of the alternative scoring system. </dd>
		<dt>season</dt>
			<dd>When the type "club", this param can contain season id to limit the chart with this season only.
				<ul>
					<li>If the value is positive, it is treated as a season id. For example: <a href="chart.php?type=club&id=41&season=4&players=851"><?php echo PRODUCT_URL; ?>/api/control/chart.php?type=club&id=41&season=4&players=851</a> returns Eluha score progress in The Black Cat club in season 2016-2017.</li>
					<li>If the value is 0 (default), current season is used. For example: <a href="chart.php?type=club&id=41&season=0&players=851"><?php echo PRODUCT_URL; ?>/api/control/chart.php?type=club&id=41&season=0&players=851</a> returns Eluha score progress in The Black Cat club in the current season.</li>
					<li>If the value is -1, all time data is returned. For example: <a href="chart.php?type=club&id=41&season=-1&players=851"><?php echo PRODUCT_URL; ?>/api/control/chart.php?type=club&id=41&season=-1&players=851</a> returns Eluha all time score progress in The Black Cat club.</li>
					<li>If the value is -2, the data sinse the same day a year ago is returned. For example: <a href="chart.php?type=club&id=41&season=-2&players=851"><?php echo PRODUCT_URL; ?>/api/control/chart.php?type=club&id=41&season=-2&players=851</a> returns Eluha annual score progress in The Black Cat club.</li>
					<li>If the value is another negative value, this value is used as a calendar year. For example: <a href="chart.php?type=club&id=41&season=-2017&players=851"><?php echo PRODUCT_URL; ?>/api/control/chart.php?type=club&id=41&season=-2017&players=851</a> returns Eluha score progress in The Black Cat club in 2017.</li>
				</ul>
			</dd>
<?php
	}
}

$page = new ApiPage();
$page->run('Chart Data');

?>