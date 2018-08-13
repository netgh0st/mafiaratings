<?php

require_once __DIR__ . '/game_state.php';
require_once __DIR__ . '/game_rules.php';

class Game
{
	private function get_round($number)
	{
		while (count($this->rounds) <= $number)
		{
			$this->rounds[] = new stdClass();
		}
		return $this->rounds[$number];
	}
	
	private function init_with_gamestate($gs)
	{
		$this->id = (int)$gs->id;
		$this->club_id = $gs->club_id;
		if (!is_null($gs->event_id))
		{
			$this->event_id = $gs->event_id;
		}
		else if (isset($this->event_id))
		{
			unset($this->event_id);
		}
		
		$this->start_time = $gs->start_time;
		$this->end_time = $gs->end_time;
		$this->language = (int)$gs->lang;
		switch ($gs->gamestate)
		{
			case GAME_MAFIA_WON:
				$this->winner = 'maf';
				break;
			case GAME_CIVIL_WON:
				$this->winner = 'civ';
				break;
			default:
				if (isset($this->winner))
				{
					unset($this->winner);
				}
				break;
		}
		$this->moderator_id = $gs->moder_id;
		
		if ($gs->best_player >= 0 && $gs->best_player < 10)
		{
			$this->best_player = $gs->best_player + 1;
		}
		else if (isset($this->best_player))
		{
			unset($this->best_player);
		}
		
		if ($gs->best_move >= 0 && $gs->best_move < 10)
		{
			$this->best_move = $gs->best_move + 1;
		}
		else if (isset($this->best_move))
		{
			unset($this->best_move);
		}
		
		if ($gs->guess3 != NULL && count($gs->guess3) > 0)
		{
			$this->guess = array();
			foreach ($gs->guess3 as $guess)
			{
				$this->guess[] = $guess + 1;
			}
		}
		else if (isset($this->guess))
		{
			unset($this->guess);
		}
		
		// rules
		if (isset($this->event_id))
		{
			list ($rules_id) = Db::record(get_label('event'), 'SELECT rules_id FROM events WHERE id = ?', $this->event_id);
		}
		else
		{
			list ($rules_id) = Db::record(get_label('club'), 'SELECT rules_id FROM clubs WHERE id = ?', $this->club_id);
		}
		$rules = new GameRules();
		$rules->load($rules_id);
		$this->rules = $rules->standard_object();
		
		// players
		$this->players = array();
		for ($i = 0; $i < 10; ++$i)
		{
			$player = new stdClass();
			$gs_player = $gs->players[$i];
			
			$player->user_id = (int)$gs_player->id;
			$player->nickname = $gs_player->nick;
			switch ($gs_player->role)
			{
				case PLAYER_ROLE_CIVILIAN:
					$player->role = 'civ';
					break;
				case PLAYER_ROLE_SHERIFF:
					$player->role = 'sheriff';
					break;
				case PLAYER_ROLE_MAFIA:
					$player->role = 'maf';
					break;
				case PLAYER_ROLE_DON:
					$player->role = 'don';
					break;
			}
			switch ($gs_player->kill_reason)
			{
				case KILL_REASON_NORMAL:
					$player->killed = new stdClass();
					$player->killed->round = (int)$gs_player->kill_round;
					if ($gs_player->state == PLAYER_STATE_KILLED_NIGHT)
					{
						$player->killed->type = 'night';
					}
					else
					{
						$player->killed->type = 'day';
					}
					break;
				case KILL_REASON_SUICIDE:
					$player->killed = new stdClass();
					$player->killed->round = (int)$gs_player->kill_round;
					$player->killed->type = 'give-up';
					break;
				case KILL_REASON_WARNINGS:
					$player->killed = new stdClass();
					$player->killed->round = (int)$gs_player->kill_round;
					$player->killed->type = 'warnings';
					break;
				case KILL_REASON_KICK_OUT:
					$player->killed = new stdClass();
					$player->killed->round = (int)$gs_player->kill_round;
					$player->killed->type = 'kick-out';
					break;
			}
			
			if ($gs_player->arranged >= 0)
			{
				$player->arranged = (int)$gs_player->arranged;
			}
			
			$this->players[$i] = $player;
		}
		
		$this->rounds = array();
		
		// voting
		foreach ($gs->votings as $voting)
		{
			$nominants_count = count($voting->nominants);
			if ($voting->voting_round == 0)
			{
				$nominants = array();
				for ($i = 0; $i < $nominants_count; ++$i)
				{
					$nominant = $voting->nominants[$i];
					
					$n = new stdClass();
					$n->player = $nominant->player_num + 1;
					$n->nominated_by = $nominant->nominated_by + 1;
					$v = array();
					for ($j = 0; $j < 10; ++$j)
					{
						if ($voting->votes[$j] == $i)
						{
							$v[] = $j + 1;
						}
					}
					$n->votes = $v;
					$nominants[] = $n;
				}
				
				if (count($nominants) > 0)
				{
					$round = $this->get_round($voting->round);
					$round->voting = $nominants;
				}
			}
			else
			{
				$round = $this->get_round($voting->round);
				for ($i = 0; $i < $nominants_count; ++$i)
				{
					$nominant = $voting->nominants[$i];
					foreach ($round->voting as $n)
					{
						if ($n->player == $nominant->player_num + 1)
						{
							break;
						}
					}
					
					$v = array();
					for ($j = 0; $j < 10; ++$j)
					{
						if ($voting->votes[$j] == $i)
						{
							$v[] = $j + 1;
						}
					}
					$votes_name = 'votes_' . $voting->voting_round;
					$n->$votes_name = $v;
				}
			}
		}
		
		// shooting
		$round_num = 0;
		foreach ($gs->shooting as $shots)
		{
			$round = $this->get_round($round_num++);
			$round->shooting = new stdClass();
			foreach ($shots as $shoter => $shooted)
			{
				$prop_name = 'player_' . ($shoter + 1);
				$round->shooting->$prop_name = $shooted + 1;
			}
		}
		
		// don and sheriff checks, and kills
		for ($i = 0; $i < 10; ++$i)
		{
			$player = $this->players[$i];
			$gs_player = $gs->players[$i];
			if ($gs_player->don_check >= 0)
			{
				$round = $this->get_round($gs_player->don_check);
				if (!isset($round->checking))
				{
					$round->checking = new stdClass();
				}
				$round->checking->don = $i + 1;
				$player->don_check = $gs_player->don_check;
			}
			
			if ($gs_player->sheriff_check >= 0)
			{
				$round = $this->get_round($gs_player->sheriff_check);
				if (!isset($round->checking))
				{
					$round->checking = new stdClass();
				}
				$round->checking->sheriff = $i + 1;
				$player->sheriff_check = $gs_player->don_check;
			}
			
			if ($gs_player->kill_round >= 0)
			{
				$round = $this->get_round($gs_player->kill_round);
				if (!isset($round->killed))
				{
					$round->killed = array();
				}
				$round->killed[] = $i + 1;
			}
		}
		
		// warnings
		$this->warnings = array();
		foreach ($gs->log as $logrec)
		{
			if ($logrec->type < LOGREC_WARNING)
			{
				continue;
			}
			
			$warning = new stdClass();
			$warning->player = $logrec->player + 1;
			$warning->round = $logrec->round;
			switch ($logrec->gamestate)
			{
				case GAME_NOT_STARTED:
				case GAME_NIGHT0_START:
					$warning->moment = 'start';
					break;
				case GAME_NIGHT0_ARRANGE:
					$warning->moment = 'arranging';
					break;
				case GAME_DAY_START:
					$warning->moment = 'day';
					break;
				case GAME_DAY_KILLED_SPEAKING: // deprecated the code should reach this only for the old logs
				case GAME_DAY_PLAYER_SPEAKING:
					$warning->moment = 'day';
					$warning->speaking = $logrec->player_speaking + 1;
					break;
				case GAME_VOTING_START:
					$warning->moment = 'voting';
					break;
				case GAME_VOTING_KILLED_SPEAKING:
					$warning->moment = 'killed-speach';
					break;
				case GAME_VOTING:
					$warning->moment = 'voting';
					$warning->on = $logrec->player_speaking + 1;
					break;
				case GAME_VOTING_MULTIPLE_WINNERS:
					$warning->moment = 'voting1';
					break;
				case GAME_VOTING_NOMINANT_SPEAKING:
					$warning->moment = 'voting';
					$warning->speaking = $logrec->player_speaking + 1;
					break;
				case GAME_NIGHT_START:
					$warning->moment = 'night';
					break;
				case GAME_NIGHT_SHOOTING:
					$warning->moment = 'shooting';
					break;
				case GAME_NIGHT_DON_CHECK:
				case GAME_NIGHT_DON_CHECK_END: // deprecated the code should reach this only for the old logs
					$warning->moment = 'don';
					break;
				case GAME_NIGHT_SHERIFF_CHECK:
				case GAME_NIGHT_SHERIFF_CHECK_END: // deprecated the code should reach this only for the old logs
					$warning->moment = 'sheriff';
					break;
				case GAME_MAFIA_WON:
				case GAME_CIVIL_WON:
					$warning->moment = 'end';
					break;
				case GAME_DAY_FREE_DISCUSSION:
					$warning->moment = 'day';
					break;
				case GAME_DAY_GUESS3:
					$warning->moment = 'guessing';
					break;
				case GAME_CHOOSE_BEST_PLAYER:
				case GAME_CHOOSE_BEST_MOVE:
					$warning->moment = 'end';
					break;
			}
			
			$player = $this->players[$logrec->player];
			$death = NULL;
			switch ($logrec->type)
			{
				case LOGREC_WARNING:
					if (!isset($player->warnings))
					{
						$player->warnings = 0;
					}
					
					if ($player->warnings < 4)
					{
						++$player->warnings;
						if ($player->warnings == 4)
						{
							$death = 'warnings';
						}
						// $warning->num = $player->warnings;
						$this->warnings[] = $warning;
					}
					break;
					
				case LOGREC_SUICIDE:
					$death = 'give-up';
					break;
				
				case LOGREC_KICK_OUT:
					$death = 'kick-out';
					break;
			}
			
			if ($death != NULL)
			{
				if (!isset($player->killed))
				{
					$player->killed = new stdClass();
				}
				
				$player->killed->type = $death;
				foreach (get_object_vars($warning) as $key => $value)
				{
					if ($key != 'player')
					{
						$player->killed->$key = $value;
					}
				}				
			}
		}
	}
	
	public function __construct($value)
	{
		if (is_string($value))
		{
			$this->set_json($value);
		}
		else if (is_int($value))
		{
			list ($log, $json) = Db::record(get_label('game'), 'SELECT log, json FROM games WHERE id = ?', $value);
			if (is_null($json))
			{
				$gs = new GameState();
				$gs->init_existing($value, $log);
				$this->init_with_gamestate($gs);
			}
			else
			{
				$this->set_json($json);
			}
			
			if ($this->data->id != value)
			{
				throw new Exc(get_label('The game is corrupted: [0]', 'Game id does not match the id in the database.'));
			}
		}
		else if ($value instanceof GameState)
		{
			$this->init_with_gamestate($value);
		}
	}
	
	public function set_json($json)
	{
		$data = json_decode($json, true);
		foreach (get_object_vars($data) as $key => $value)
		{
			$this->$key = $value;
		}				
	}
	
	public function get_json()
	{
		return json_encode($this);
	}
	
	public function save_results()
	{
		if ($this->id <= 0)
		{
			throw new Exc(get_label('Unknown [0]', get_label('game')));
		}

		$game_result = 0;
		if (isset($this->winner))
		{
			switch ($this->winner)
			{
				case 'maf';
					$game_result = 2;
					break;
				case 'civ';
					$game_result = 1;
					break;
			}
		}
		if ($game_result < 1 || $game_result > 2)
		{
			throw new Exc(get_label('The game [0] is not finished yet.', $gs->id));
		}
		
		try
		{
			$best_player_id = NULL;
			if (isset($this->best_player) && $this->best_player >= 1 && $this->best_player <= 10)
			{
				$best_player_id = $this->players[$this->best_player - 1]->id;
				if ($best_player_id <= 0)
				{
					$best_player_id = NULL;
				}
			}
			
			Db::exec(get_label('user'), 'UPDATE users u, games g SET u.games_moderated = u.games_moderated + 1 WHERE u.id = g.moderator_id AND g.id = ?', $this->id);
			Db::exec(get_label('player'), 'DELETE FROM dons WHERE game_id = ?', $this->id);
			Db::exec(get_label('player'), 'DELETE FROM sheriffs WHERE game_id = ?', $this->id);
			Db::exec(get_label('player'), 'DELETE FROM mafiosos WHERE game_id = ?', $this->id);
			Db::exec(get_label('player'), 'DELETE FROM players WHERE game_id = ?', $this->id);
			
			$stats = array();
			for ($i = 0; $i < 10; ++$i)
			{
				$stats[] = new GamePlayerStats($this, $i);
			}
			
			// calculate ratings
			$maf_sum = 0;
			$maf_count = 0;
			$civ_sum = 0;
			$civ_count = 0;
			for ($i = 0; $i < 10; ++$i)
			{
				$player = $gs->players[$i];
				if ($player->id > 0)
				{
					switch ($player->role)
					{
						case PLAYER_ROLE_CIVILIAN:
						case PLAYER_ROLE_SHERIFF:
							$civ_sum += $stats[$i]->rating_before;
							++$civ_count;
							break;
						case PLAYER_ROLE_MAFIA:
						case PLAYER_ROLE_DON:
							$maf_sum += $stats[$i]->rating_before;
							++$maf_count;
							break;
					}
				}
			}
			
			$civ_odds = NULL;
			if ($maf_count > 0 && $civ_count > 0)
			{
				$civ_odds = 1.0 / (1.0 + pow(10.0, ($maf_sum / $maf_count - $civ_sum / $civ_count) / 400));
				for ($i = 0; $i < 10; ++$i)
				{
					$stats[$i]->calculate_rating($civ_odds);
				}
			}
			
			// save stats
			for ($i = 0; $i < 10; ++$i)
			{
				$stats[$i]->save();
			}
			Db::exec(get_label('game'), 'UPDATE games SET result = ?, best_player_id = ?, flags = ?, civ_odds = ? WHERE id = ?', $game_result, $best_player_id, $gs->flags, $civ_odds, $gs->id);
		}
		catch (FatalExc $e)
		{
			throw new Exc(get_label('Unable to save game [2] stats for player #[0]: [1]', $i + 1, $e->getMessage(), $gs->id), $e->get_details());
		}
		catch (Exception $e)
		{
			throw new Exc(get_label('Unable to save game [2] stats for player #[0]: [1]', $i + 1, $e->getMessage(), $gs->id));
		}
	}
}

?>