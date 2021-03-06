<?php

require_once '../../include/api.php';
require_once '../../include/rules.php';

define('CURRENT_VERSION', 0);

class ApiPage extends GetApiPageBase
{
	protected function prepare_response()
	{
		$rules_code = get_optional_param('rules_code');
		$game_id = (int)get_optional_param('game_id');
		$event_id = (int)get_optional_param('event_id');
		$tournament_id = (int)get_optional_param('tournament_id');
		$club_id = (int)get_optional_param('club_id');
		$page_size = (int)get_optional_param('page_size', 16);
		$page = (int)get_optional_param('page');
		$detailed = isset($_REQUEST['detailed']);
		
		if (!is_valid_rules_code($rules_code))
		{
			if ($game_id > 0)
			{
				list($rules_code) = Db::record('rules', 'SELECT rules FROM games WHERE id = ?', $game_id);
			}
			else if ($event_id > 0)
			{
				list($rules_code) = Db::record('rules', 'SELECT rules FROM events WHERE id = ?', $event_id);
			}
			else if ($tournament_id > 0)
			{
				list($rules_code) = Db::record('rules', 'SELECT rules FROM tournaments WHERE id = ?', $tournament_id);
			}
			else if ($club_id > 0)
			{
				list($rules_code) = Db::record('rules', 'SELECT rules FROM clubs WHERE id = ?', $club_id);
			}
			else
			{
				throw new Exc(get_label('Unknown [0]', get_label('rules')));
			}
		}
		$this->response['rules'] = rules_code_to_object($rules_code, $detailed);
	}
	
	protected function get_help()
	{
		$help = new ApiHelp(PERMISSION_EVERYONE);
		$help->request_param('rules_code', 'Search pattern. For example: <a href="rules.php?rules_code=000000000000010200000000000"><?php echo PRODUCT_URL; ?>/api/get/rules.php?rules_code=000000000000010200000000000</a> returns details about the rules with this id.', '-');
		$help->request_param('game_id', 'Search pattern. For example: <a href="rules.php?game_id=140"><?php echo PRODUCT_URL; ?>/api/get/rules.php?game_id=140</a> returns the rules of the game #140.', '-');
		$help->request_param('event_id', 'Search pattern. For example: <a href="rules.php?event_id=8095"><?php echo PRODUCT_URL; ?>/api/get/rules.php?event_id=8095</a> returns the rules of VaWaCa-2018.', '-');
		$help->request_param('tournament_id', 'Search pattern. For example: <a href="rules.php?tournament_id=1"><?php echo PRODUCT_URL; ?>/api/get/rules.php?tournament_id=1</a> returns the rules of tournament 1.', '-');
		$help->request_param('club_id', 'Search pattern. For example: <a href="rules.php?club_id=50"><?php echo PRODUCT_URL; ?>/api/get/rules.php?club_id=50</a> returns the rules of New Yourk Mafia Club.', '-');
		$help->request_param('detailed', 'If set all the default params are shown explicitly. For example: <a href="rules.php?club_id=1&detailed"><?php echo PRODUCT_URL; ?>/api/get/rules.php?club_id=1&detailed</a> shows detailed rules for Russian Mafia of Vancouver.', '-');

		$param = $help->response_param('rules', 'Game rules.');
		api_rules_help($param, true);
		return $help;
	}
}

$page = new ApiPage();
$page->run('Get Scores', CURRENT_VERSION);

?>