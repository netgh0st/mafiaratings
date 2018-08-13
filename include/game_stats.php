<?php

require_once __DIR__ . '/game_state.php';
require_once __DIR__ . '/localization.php';
require_once __DIR__ . '/scoring.php';

class GamePlayerStats
{
    public $game;
    public $player_num;
	public $won;
	public $scoring_flags;

    public $rating_before;
    public $rating_earned;
   
    public $voted_civil;
    public $voted_mafia;
    public $voted_sheriff;
    public $nominated_civil;
    public $nominated_mafia;
    public $nominated_sheriff;
    public $nominated_by_mafia;
    public $nominated_by_civil;
    public $nominated_by_sheriff;
    public $voted_by_mafia;
    public $voted_by_civil;
    public $voted_by_sheriff;
    public $kill_type;

    // Sheriff only
    public $civil_found;
    public $mafia_found;

    // Mafia only
    public $shots1_ok;
    public $shots1_miss;
    public $shots2_ok;        // 2 mafia players are alive: successful shots
    public $shots2_miss;      // 2 mafia players are alive: missed shot
    public $shots2_blank;     // 2 mafia players are alive: this player didn't shoot
    public $shots2_rearrange; // 2 mafia players are alive: killed a player who was not arranged
    public $shots3_ok;        // 3 mafia players are alive: successful shots
    public $shots3_miss;      // 3 mafia players are alive: missed shot
    public $shots3_blank;     // 3 mafia players are alive: this player didn't shoot
    public $shots3_fail;      // 3 mafia players are alive: missed because of this player (others shoot the same person)
    public $shots3_rearrange; // 3 mafia players are alive: killed a player who was not arranged

    // Don only
    public $sheriff_found;
    public $sheriff_arranged;

	private function calculate_scoring_flags()
	{
		$game = $this->game;
        $player = $game->players[$this->player_num];
		$this->scoring_flags = SCORING_FLAG_PLAY;

		$maf_day_kills = 0;
		$civ_day_kills = 0;
		foreach ($game->players as $p)
		{
			if (isset($p->killed) && $p->killed->type == 'day')
			{
				if ($p->role == 'don' || $p->role == 'maf')
				{
					++$maf_day_kills;
				}
				else
				{
					++$civ_day_kills;
				}
			}
		}
		
		if (isset($game->winner))
		{
			if ($game->winner == 'civ')
			{
				if ($player->role == 'don' || $player->role == 'maf')
				{
					$this->scoring_flags |= SCORING_FLAG_LOOSE;
					if ($civ_day_kills == 0)
					{
						$this->scoring_flags |= SCORING_FLAG_CLEAR_LOOSE;
					}
				}
				else
				{
					$this->scoring_flags |= SCORING_FLAG_WIN;
					if ($civ_day_kills == 0)
					{
						$this->scoring_flags |= SCORING_FLAG_CLEAR_WIN;
					}
				}
			}
			else if ($player->role == 'don' || $player->role == 'maf')
			{
				$this->scoring_flags |= SCORING_FLAG_WIN;
				if ($maf_day_kills == 0)
				{
					$this->scoring_flags |= SCORING_FLAG_CLEAR_WIN;
				}
			}
			else
			{
				$this->scoring_flags |= SCORING_FLAG_LOOSE;
				if ($maf_day_kills == 0)
				{
					$this->scoring_flags |= SCORING_FLAG_CLEAR_LOOSE;
				}
			}
		}
		
		if ($game->best_player == $this->player_num + 1)
		{
			$this->scoring_flags |= SCORING_FLAG_BEST_PLAYER;
		}
		
		if ($game->best_move == $this->player_num + 1)
		{
			$this->scoring_flags |= SCORING_FLAG_BEST_MOVE;
		}
		
		if (isset($player->killed))
		{
			switch ($player->killed->type)
			{
				case 'night':
					if ($player->killed->round == 0)
					{
						$this->scoring_flags |= SCORING_FLAG_KILLED_FIRST_NIGHT;
						if (isset($game->guess) && $player->role != 'maf' && $player->role != 'don')
						{
							$mafs_guessed = 0;
							foreach ($game->guess as $guess)
							{
								$p = $game->players[$guess - 1];
								if ($p->role == 'maf' || $p->role == 'don')
								{
									++$mafs_guessed;
								}
							}
							
							if ($mafs_guessed >= 3)
							{
								$this->scoring_flags |= SCORING_FLAG_GUESSED_3;
							}
							else if ($mafs_guessed >= 2)
							{
								$this->scoring_flags |= SCORING_FLAG_GUESSED_2;
							}
						}
					}
					$this->scoring_flags |= SCORING_FLAG_KILLED_NIGHT;
					break;
					
				case 'give-up':
					$this->scoring_flags |= SCORING_FLAG_SURRENDERED;
					break;
					
				case 'warnings':
					$this->scoring_flags |= SCORING_FLAG_WARNINGS_4;
					break;
					
				case 'kick-out':
					$this->scoring_flags |= SCORING_FLAG_KICK_OUT;
					break;
			}
		}
		else
		{
			$this->scoring_flags |= SCORING_FLAG_SURVIVE;
		}

		if ($this->voted_civil + $this->voted_sheriff == 0 && $this->voted_mafia >= 3)
		{
			$this->scoring_flags |= SCORING_FLAG_ALL_VOTES_VS_MAF;
		}
		
		if ($this->voted_mafia == 0 && $this->voted_civil + $this->voted_sheriff >= 3)
		{
			$this->scoring_flags |= SCORING_FLAG_ALL_VOTES_VS_CIV;
		}
		
		for  ($i = 0; $i < count($game->players); ++i)
		{
			$p = $players[$i];
			if ($p->role == 'sheriff')
			{
				if (isset($p->killed) && $p->killed->type == 'night')
				{
					$don_check = -1;
					if (isset($game->rounds))
					{
						for ($j = 0; $j < count($game->rounds); ++$j)
						{
							$round = $game->rounds[$j];
							if (isset($round->don) && $round->don == $i + 1)
							{
								$don_check = $j;
								break;
							}
						}
					}
					
					if ($p->killed->round == $don_check + 1 && $p->arranged != $p->killed->round)
					{
						$this->scoring_flags |= SCORING_FLAG_SHERIFF_KILLED_AFTER_FINDING;
					}
					
					if ($p->killed->round == 0)
					{
						$this->scoring_flags |= SCORING_FLAG_SHERIFF_KILLED_FIRST_NIGHT;
					}
					
					if ($don_check == 0)
					{
						$this->scoring_flags |= SCORING_FLAG_SHERIFF_FOUND_FIRST_NIGHT;
					}
				}
				break;
			}
		}
		
		if (isset($game->rounds))
		{
			$black_checks = 0;
			$red_checks = 0;
			foreach ($game->rounds as $round)
			{
				if (!isset($round->sheriff))
				{
					continue;
				}
				
				$p = $game->players[$round->sheriff - 1];
				if ($p->role = 'maf' || $p->role = 'don')
				{
					++$black_checks;
				}
				else
				{
					++$red_checks;
				}
				
				if ($black_checks + $red_checks >= 3)
				{
					break;
				}
			}
			
			if ($black_checks >= 3)
			{
				$this->scoring_flags |= SCORING_FLAG_BLACK_CHECKS;
			}
			else if ($red_checks >= 3)
			{
				$this->scoring_flags |= SCORING_FLAG_RED_CHECKS;
			}
		}
	}

	function __construct($game, $player_num)
    {
		if ($game instanceof GameState)
		{
			$game = new Game($game);
		}
		
        $player = $game->players[$player_num];
		$this->game = $game;
        $this->player_num = $player_num;
		
		$this->won = 0;
		if (isset($game->winner))
		{
			switch ($player->role)
			{
				case 'maf':
				case 'don':
					if ($game->winner == 'maf')
					{
						$this->won = 1;
					}
					break;

				default:
					if ($game->winner == 'civ')
					{
						$this->won = 1;
					}
					break;
			}
		}

        $this->nominated_civil = 0;
        $this->nominated_mafia = 0;
        $this->nominated_sheriff = 0;
        $this->nominated_by_mafia = 0;
        $this->nominated_by_civil = 0;
        $this->nominated_by_sheriff = 0;
        $this->voted_civil = 0;
        $this->voted_mafia = 0;
        $this->voted_sheriff = 0;
        $this->voted_by_mafia = 0;
        $this->voted_by_civil = 0;
        $this->voted_by_sheriff = 0;
		if (isset($game->rounds))
		{
			for ($i = 1; $i < count($game->rounds); ++$i)
			{
				$round = $game->rounds[$i];
				if (!isset($round->voting))
				{
					continue;
				}
				
				foreach ($round->voting as $voting)
				{
					if ($voting->player == $player_num + 1)
					{
						switch ($game->players[$voting->nominated_by - 1]->role)
						{
							case 'sheriff':
								++$this->nominated_by_sheriff;
								break;
							case 'maf':
							case 'don':
								++$this->nominated_by_mafia;
								break;
							default:
								++$this->nominated_by_civil;
								break;
						}
						
						$vote_num = 0;
						$votes_name = 'votes';
						while (isset($voting->$votes_name))
						{
							foreach ($voting->$votes_name as $vote)
							{
								switch ($game->players[$vote - 1]->role)
								{
									case 'sheriff':
										++$this->voted_by_sheriff;
										break;
									case 'maf':
									case 'don':
										++$this->voted_by_mafia;
										break;
									default:
										++$this->voted_by_civil;
										break;
								}
							}
							++$vote_num;
							$votes_name = 'votes_' . $vote_num;
						}
					}
				}
				
				if ($voting->nominated_by == $player_num + 1)
				{
					switch ($game->players[$voting->player - 1]->role)
					{
						case 'sheriff':
							++$this->nominated_sheriff;
							break;
						case 'maf':
						case 'don':
							++$this->nominated_mafia;
							break;
						default:
							++$this->nominated_civil;
							break;
					}
				}
				
				$vote_num = 0;
				$votes_name = 'votes';
				while (isset($voting->$votes_name))
				{
					foreach ($voting->$votes_name as $vote)
					{
						if ($vote == $player_num + 1)
						{
							switch ($game->players[$voting->player - 1]->role)
							{
								case 'sheriff':
									++$this->voted_sheriff;
									break;
								case 'maf':
								case 'don':
									++$this->voted_mafia;
									break;
								default:
									++$this->voted_civil;
									break;
							}
							break;
						}
					}
					++$vote_num;
					$votes_name = 'votes_' . $vote_num;
				}
			}
        }

		$this->kill_type = 0;
		if (isset($player->killed))
		{
			switch ($player->killed->type)
			{
				case 'day':
                    $this->kill_type = 1;
					break;
				case 'night':
                    $this->kill_type = 2;
					break;
				case 'warnings':
					$this->kill_type = 3;
					break;
				case 'give-up':
					$this->kill_type = 4;
					break;
				case 'kick-out':
					$this->kill_type = 5;
					break;
			}
		}
		
		// Sheriff
		$this->civil_found = 0;
        $this->mafia_found = 0;
		if ($player->role == 'sheriff')
		{
			foreach ($game->players as $player)
			{
				if (isset($player->sheriff_check))
				{
					if ($player->role == 'maf' || $player->role == 'don')
					{
						++$this->mafia_found;
					}
					else
					{
						++$this->civil_found;
					}
				}
			}
		}

		// Mafia
		$this->shots1_ok = 0;
		$this->shots1_miss = 0;
		$this->shots2_ok = 0;
		$this->shots2_miss = 0;
		$this->shots2_blank = 0;
		$this->shots2_rearrange = 0;
		$this->shots3_ok = 0;
		$this->shots3_miss = 0;
		$this->shots3_blank = 0;
		$this->shots3_fail = 0;
		$this->shots3_rearrange = 0;
		if (isset($game->rounds) && ($player->role == 'maf' || $player->role == 'don'))
		{
            $partner1 = 0;
            $partner2 = 0;
			for ($i = 0; $i < 10; ++$i)
			{
				if ($i != $player_num)
				{
					$p = $game->players[$i];
					if ($p->role == 'maf' || $p->role == 'don')
					{
						if ($partner1 < 0)
						{
							$partner1 = $i + 1;
						}
						else
						{
							$partner2 = $i + 1;
							break;
						}
					}
				}
			}
			
			$maf_1 = 'player_' . ($player_num + 1);
			$maf_2 = 'player_' . $partner1;
			$maf_3 = 'player_' . $partner2;
			for ($i = 0; $i < count($game->rounds); ++$i)
			{
				$round = $game->rounds[$i];
				if (!isset($round->shooting))
				{
					break;
				}
				
				$shooting = $round->shooting;
				if (!isset($shooting->$maf1))
				{
					break;
				}
				
				if (isset($shooting->$maf2))
				{
					if (isset($shooting->$maf3))
					{
						if ($shooting->$maf1 <= 0)
						{
							++$this->shots3_blank;
							++$this->shots3_miss;
						}
						else if ($shooting->$maf1 == $shooting->$maf2)
						{
							if ($shooting->$maf1 == $shooting->$maf3)
							{
								++$this->shots3_ok;
								$p = $game->players[$shooting->$maf1 - 1];
								if (isset($p->arranged) && $p->arranged != $i)
								{
									++$this->shots3_rearrange;
								}
							}
							else
							{
								++$this->shots3_miss;
							}
						}
						else 
						{
							++$this->shots3_miss;
							if ($shooting->$maf2 == $shooting->$maf3 && $shooting->$maf2 > 0)
							{
								++$this->shots3_fail;
							}
						}
					}
					else if ($shooting->$maf1 <= 0)
					{
						++$this->shots2_miss;
						++$this->shots2_blank;
					}
					else if ($shooting->$maf1 == $shooting->$maf2)
					{
						++$this->shots2_ok;
					}
					else
					{
						++$this->shots2_miss;
					}
				}
				else if (isset($shooting->$maf3))
				{
					if ($shooting->$maf1 <= 0)
					{
						++$this->shots2_miss;
						++$this->shots2_blank;
					}
					else if ($shooting->$maf1 == $shooting->$maf3)
					{
						++$this->shots2_ok;
					}
					else
					{
						++$this->shots2_miss;
					}
				}
				else if ($shooting->$maf1 > 0)
				{
					++$this->shots1_ok;
				}
				else
				{
					++$this->shots1_miss;
				}
			}
		}

        // Don
        $this->sheriff_found = -1;
        $this->sheriff_arranged = -1;
		if ($player->role == 'don')
		{
			foreach ($game->players as $player)
			{
				if ($player->role == 'sheriff')
				{
					if (isset($player->don_check))
					{
						$this->sheriff_found = $player->don_check;
					}
					
					if (isset($player->arranged))
					{
						$this->sheriff_arranged = $player->arranged;
					}
				}
			}
		}
		
		$this->calculate_scoring_flags();

		// init points and ratings
		$this->rating_before = 0;
		$this->rating_earned = 0;
		if ($player->user_id <= 0)
		{
			return;
		}
		
		$query = new DbQuery('SELECT p.rating_before + p.rating_earned FROM players p JOIN games g ON p.game_id = g.id WHERE (g.start_time < ? OR (g.start_time = ? AND g.id < ?)) AND p.user_id = ? ORDER BY g.end_time DESC, g.id DESC LIMIT 1', $game->end_time, $game->end_time, $game->id, $player->id);
		if ($row = $query->next())
		{
			list($this->rating_before) = $row;
		}
		else
		{
			$this->rating_before = USER_INITIAL_RATING;
		}
		
		$query = new DbQuery('SELECT p.rating_earned FROM players p JOIN games g ON p.game_id = g.id WHERE g.id = ? AND p.user_id = ?', $game->id, $player->id);
		if ($row = $query->next())
		{
			list($this->rating_earned) = $row;
		}
    }
	
	function calculate_rating($civ_odds)
	{
        $game = $this->game;
        $player = $game->players[$this->player_num];
		if ($player->user_id <= 0)
		{
			return;
		}

		$WINNING_K = 20;
		$LOOSING_K = 15;
		switch ($player->role)
		{
			case 'civ':
			case 'sheriff':
				if ($game->winner == 'civ')
				{
					$this->rating_earned = $WINNING_K * (1 - $civ_odds);
				}
				else
				{
					$this->rating_earned = - $LOOSING_K * $civ_odds;
				}
				break;
			case 'maf':
			case 'don':
				if ($game->winner == 'civ')
				{
					$this->rating_earned = $LOOSING_K * ($civ_odds - 1);
				}
				else
				{
					$this->rating_earned = $WINNING_K * $civ_odds;
				}
				break;
		}
		
		// $this->rating_earned += 1;
		if ($this->rating_before + $this->rating_earned < USER_INITIAL_RATING)
		{
			$this->rating_earned = USER_INITIAL_RATING - $this->rating_before;
		}
	}
	
    function save()
    {
        $game = $this->game;
        $player = $game->players[$this->player_num];
		if ($player->user_id <= 0)
		{
			return NULL;
		}
		
        Db::exec(
			get_label('player'), 
            'INSERT INTO players (game_id, user_id, nick_name, number, role, rating_before, rating_earned, flags, ' .
				'voted_civil, voted_mafia, voted_sheriff, voted_by_civil, voted_by_mafia, voted_by_sheriff, ' .
				'nominated_civil, nominated_mafia, nominated_sheriff, nominated_by_civil, nominated_by_mafia, nominated_by_sheriff, ' .
				'kill_round, kill_type, warns, was_arranged, checked_by_don, checked_by_sheriff, won) ' .
				'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
			$game->user_id, $player->user_id, $player->nickname, $this->player_num + 1, $player->role, $this->rating_before, $this->rating_earned, $this->scoring_flags,
			$this->voted_civil, $this->voted_mafia, $this->voted_sheriff, $this->voted_by_civil, $this->voted_by_mafia, $this->voted_by_sheriff,
			$this->nominated_civil, $this->nominated_mafia, $this->nominated_sheriff, $this->nominated_by_civil, $this->nominated_by_mafia, $this->nominated_by_sheriff,
			$player->kill_round, $this->kill_type, $player->warnings, $player->arranged, $player->don_check, $player->sheriff_check, $this->won);

		switch ($player->role)
		{
			case 'civ':
				break;
			case 'sheriff':
				Db::exec(
					get_label('sheriff'), 
					'INSERT INTO sheriffs VALUES (?, ?, ?, ?)',
					$game->id, $player->id, $this->civil_found, $this->mafia_found);
				break;
			case 'don':
				Db::exec(
					get_label('mafioso'), 
					'INSERT INTO mafiosos VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ' . ($player->role == PLAYER_ROLE_DON ? 'true' : 'false') . ')',
					$game->id, $player->id, $this->shots1_ok, $this->shots1_miss, $this->shots2_ok,
					$this->shots2_miss, $this->shots2_blank, $this->shots2_rearrange, $this->shots3_ok, $this->shots3_miss,
					$this->shots3_blank, $this->shots3_fail, $this->shots3_rearrange);
				Db::exec(
					get_label('don'), 
					'INSERT INTO dons VALUES (?, ?, ?, ?)',
					$game->id, $player->id, $this->sheriff_found, $this->sheriff_arranged);
				break;
			case 'maf':
				Db::exec(
					get_label('mafioso'), 
					'INSERT INTO mafiosos VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ' . ($player->role == PLAYER_ROLE_DON ? 'true' : 'false') . ')',
					$game->id, $player->id, $this->shots1_ok, $this->shots1_miss, $this->shots2_ok,
					$this->shots2_miss, $this->shots2_blank, $this->shots2_rearrange, $this->shots3_ok, $this->shots3_miss,
					$this->shots3_blank, $this->shots3_fail, $this->shots3_rearrange);
				break;
		}
		
		$query = new DbQuery('UPDATE users SET rating = ?, games = games + 1, games_won = games_won + ?', $this->rating_before + $this->rating_earned, $this->won);
		if ($player->kill_round == 0 && $player->state == PLAYER_STATE_KILLED_NIGHT)
		{
			$query->add(', flags = (flags | ' . U_FLAG_IMMUNITY . ')');
		}
		else
		{
			$query->add(', flags = (flags & ' . ~U_FLAG_IMMUNITY . ')');
		}
		$query->add(' WHERE id = ?', $player->id);
		Db::exec(get_label('user'), $query);
    }
	
	public function get_title()
	{
		$game = $this->game;
        $player = $game->players[$this->player_num];
		return get_label('Game [0], player [1] - [2]', $game->id, $this->player_num + 1, cut_long_name($player->nick, 66));
	}
}

function save_game_results($game)
{
	if ($game->id <= 0)
	{
		return NULL;
	}

	$update_stats = true;
    $game_result = 0;
	if (!isset($game->winner))
	{
		throw new Exc(get_label('The game [0] is not finished yet.', $game->id));
	}
	
	if ($game->winner == 'civ')
	{
		$game_result = 1;
	}
	else
    {
		$game_result = 2;
    }

	try
	{
		Db::begin();
		$best_player_id = NULL;
		if (isset($game->best_player)
		{
			$best_player_id = $game->players[$game->best_player - 1]->user_id;
			if ($best_player_id <= 0)
			{
				$best_player_id = NULL;
			}
		}
		
		if ($update_stats)
		{
			Db::exec(get_label('user'), 'UPDATE users u, games g SET u.games_moderated = u.games_moderated + 1 WHERE u.id = g.moderator_id AND g.id = ?', $game->id);
			Db::exec(get_label('player'), 'DELETE FROM dons WHERE game_id = ?', $game->id);
			Db::exec(get_label('player'), 'DELETE FROM sheriffs WHERE game_id = ?', $game->id);
			Db::exec(get_label('player'), 'DELETE FROM mafiosos WHERE game_id = ?', $game->id);
			Db::exec(get_label('player'), 'DELETE FROM players WHERE game_id = ?', $game->id);
			$stats = array();
			for ($i = 0; $i < 10; ++$i)
			{
				$stats[] = new GamePlayerStats($game, $i);
			}
			
			// calculate ratings
			$maf_sum = 0;
			$maf_count = 0;
			$civ_sum = 0;
			$civ_count = 0;
			for ($i = 0; $i < 10; ++$i)
			{
				$player = $game->players[$i];
				if ($player->user_id > 0)
				{
					switch ($player->role)
					{
						case 'civ':
						case 'sheriff':
							$civ_sum += $stats[$i]->rating_before;
							++$civ_count;
							break;
						case 'maf':
						case 'don':
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
			Db::exec(get_label('game'), 'UPDATE games SET result = ?, best_player_id = ?, civ_odds = ? WHERE id = ?', $game_result, $best_player_id, $civ_odds, $game->id);
		}
		else
		{
			Db::exec(get_label('game'), 'UPDATE games SET result = ?, best_player_id = ? WHERE id = ?', $game_result, $best_player_id, $game->id);
		}
		Db::commit();
	}
	catch (FatalExc $e)
	{
		Db::rollback();
		throw new Exc(get_label('Unable to save game [2] stats for player #[0]: [1]', $i + 1, $e->getMessage(), $game->id), $e->get_details());
	}
	catch (Exception $e)
	{
		Db::rollback();
		throw new Exc(get_label('Unable to save game [2] stats for player #[0]: [1]', $i + 1, $e->getMessage(), $game->id));
	}
}

function rebuild_game_stats($gs)
{
	if ($gs->id <= 0)
    {
		return;
	}

	Db::begin();
	Db::exec(get_label('user'), 'UPDATE users SET games_moderated = games_moderated - 1 WHERE id = (SELECT moderator_id FROM games WHERE id = ?)', $gs->id);
	Db::exec(get_label('player'), 'DELETE FROM dons WHERE game_id = ?', $gs->id);
	Db::exec(get_label('player'), 'DELETE FROM sheriffs WHERE game_id = ?', $gs->id);
	Db::exec(get_label('player'), 'DELETE FROM mafiosos WHERE game_id = ?', $gs->id);
	Db::exec(get_label('player'), 'DELETE FROM players WHERE game_id = ?', $gs->id);
	
	$gs->save();
	save_game_results(new Game($gs));
	db_log('game', 'Stats rebuilt', NULL, $gs->id, $gs->club_id);
	Db::commit();
}

?>