<?php

require_once 'include/session.php';
require_once 'include/city.php';
require_once 'include/country.php';
require_once 'include/event.php';
require_once 'include/timespan.php';
require_once 'include/scoring.php';

initiate_session();

try
{
	dialog_title(get_label('Create [0]', get_label('event')));

	if (!isset($_REQUEST['club']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('club')));
	}
	$club_id = $_REQUEST['club'];
	if ($_profile == NULL || !$_profile->is_club_manager($club_id))
	{
		throw new FatalExc(get_label('No permissions'));
	}
	
	$club = $_profile->clubs[$club_id];
	$event = new Event();
	$event->set_club($club);

	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="160">'.get_label('Event name').':</td><td><input id="form-name" value="' . htmlspecialchars($event->name, ENT_QUOTES) . '"></td></tr>';
	
	echo '<tr><td>'.get_label('Date').':</td><td>';
	echo '<input type="checkbox" id="form-multiple" onclick="multipleChange()"> ' . get_label('multiple events');
	echo '<div id="form-single_date">';
	show_date_controls($event->day, $event->month, $event->year, 'form-');
	echo '</div><div id="form-multiple_date" style="display:none;">';
	echo '<p>' . get_label('Every') . ': ';
	$weekday_names = array(get_label('sun'), get_label('mon'), get_label('tue'), get_label('wed'), get_label('thu'), get_label('fri'), get_label('sat'));
	for ($i = 0; $i < 7; ++$i)
	{
		echo '<input type="checkbox" id="form-wd' . $i . '"> ' . $weekday_names[$i] . ' ';
	}
	echo '</p>';
	echo '<p>' . get_label('From') . ' ';
	show_date_controls($event->day, $event->month, $event->year, 'form-from_');
	echo ' ' . get_label('to') . ' ';
	show_date_controls($event->day, $event->month, $event->year, 'form-to_');
	echo '</td></tr>';
	echo '</div></td></tr>';
		
	echo '<tr><td>'.get_label('Time').':</td><td>';
	show_time_controls($event->hour, $event->minute, 'form-');
	echo '</td></tr>';
		
	echo '<tr><td>'.get_label('Duration').':</td><td><input value="' . timespan_to_string($event->duration) . '" placeholder="' . get_label('eg. 3w 4d 12h') . '" id="form-duration" onkeyup="checkDuration()"></td></tr>';
		
	$query = new DbQuery('SELECT id, name FROM addresses WHERE club_id = ? AND (flags & ' . ADDR_FLAG_NOT_USED . ') = 0 ORDER BY name', $event->club_id);
	echo '<tr><td>'.get_label('Address').':</td><td>';
	echo '<select id="form-addr_id" onChange="addressClick()">';
	echo '<option value="-1">' . get_label('New address') . '</option>';
	$selected_address = '';
	while ($row = $query->next())
	{
		if (show_option($row[0], $event->addr_id, $row[1]))
		{
			$selected_address = $row[1];
		}
	}
	echo '</select><div id="form-new_addr_div">';
//	echo '<button class="icon" onclick="mr.createAddr(' . $club_id . ')" title="' . get_label('Create [0]', get_label('address')) . '"><img src="images/create.png" border="0"></button>';
	echo '<input id="form-new_addr" onkeyup="newAddressChange()"> ';
	show_country_input('form-country', $club->country, 'form-city');
	echo ' ';
	show_city_input('form-city', $club->city, 'form-country');
	echo '</span></td></tr>';
	
	echo '<tr><td>'.get_label('Admission rate').':</td><td><input id="form-price" value="' . $event->price . '"></td></tr>';
		
	$query = new DbQuery('SELECT rules, name FROM club_rules WHERE club_id = ? ORDER BY name', $event->club_id);
	if ($row = $query->next())
	{
		$custom_rules = true;
		echo '<tr><td>' . get_label('Game rules') . ':</td><td><select id="form-rules"><option value="' . $club->rules_code . '"';
		if ($club->rules_code == $event->rules_code)
		{
			echo ' selected';
			$custom_rules = false;
		}
		echo '>' . $club->name . '</option>';
		do
		{
			list ($rules_code, $rules_name) = $row;
			echo '<option value="' . $rules_code . '"';
			if ($custom_rules && $rules_code == $event->rules_code)
			{
				echo ' selected';
			}
			echo '>' . $rules_name . '</option>';
		} while ($row = $query->next());
		echo '</select>';
		echo '</td></tr>';
	}
	else
	{
		echo '<input type="hidden" id="form-rules" value="' . $club->rules_code . '">';
	}
	
	echo '<tr><td class="dark">' . get_label('Rounds') . ':</td><td><table width="100%" class="transp">';
	echo '<tr><td width="48"><a href="javascript:addRound()" title="' . get_label('Add round') . '"><img src="images/create.png"></a></td>';
	echo '<td width="90">' . get_label('Name') . '</td>';
	echo '<td>' . get_label('Scoring system') . '</td>';
	echo '<td width="70">' . get_label('Scoring weight') . '</td>'; 
	echo '<td width="70" align="center">' . get_label('Planned games count') . '</td></tr>';
	echo '<tr><td></td>';
	echo '<td>' . get_label('Main round') . '</td>';
	echo '<td>';
	show_scoring_select($event->club_id, $event->scoring_id, '', get_label('Scoring system'), 'form-scoring', false);
	echo '</td>';
	echo '<td><input id="form-scoring_weight" value="' . $event->scoring_weight . '"></td>';
	echo '<td><input id="form-planned_games" value="' . ($event->planned_games > 0 ? $event->planned_games : '') . '"></td></tr>';
	echo '</table><span id="form-rounds"></span></td></tr>';
	
	if (is_valid_lang($club->langs))
	{
		echo '<input type="hidden" id="form-langs" value="' . $club->langs . '">';
	}
	else
	{
		echo '<tr><td>'.get_label('Languages').':</td><td>';
		langs_checkboxes($event->langs, $club->langs, NULL, '<br>', 'form-');
		echo '</td></tr>';
	}
		
	echo '<tr><td>'.get_label('Notes').':</td><td><textarea id="form-notes" cols="80" rows="4">' . htmlspecialchars($event->notes, ENT_QUOTES) . '</textarea></td></tr>';
		
	echo '<tr><td colspan="2">';
	echo '<input type="checkbox" id="form-reg_att"';
	if (($event->flags & EVENT_FLAG_REG_ON_ATTEND) != 0)
	{
		echo ' checked';
	}
	echo '> '.get_label('allow users to register for the event when they click Attend button').'<br>';
		
	echo '<input type="checkbox" id="form-pwd_req"';
	if (($event->flags & EVENT_FLAG_PWD_REQUIRED) != 0)
	{
		echo ' checked';
	}
	echo '> '.get_label('user password is required when moderator is registering him for this event.').'<br>';

	echo '<input type="checkbox" id="form-all_mod"';
	if (($event->flags & EVENT_FLAG_ALL_MODERATE) != 0)
	{
		echo ' checked';
	}
	echo '> '.get_label('everyone can moderate games.').'</td></tr>';
	
	echo '</table>';
	
	echo '<table class="transp" width="100%"><tr>';
	echo '<td align="right">';
	$query = new DbQuery(
		'SELECT e.id, e.name, e.start_time, c.timezone FROM events e' .
			' JOIN addresses a ON e.address_id = a.id' . 
			' JOIN cities c ON a.city_id = c.id' . 
			' WHERE e.club_id = ?' .
			' AND e.start_time < UNIX_TIMESTAMP() ORDER BY e.start_time DESC LIMIT 30',
		$event->club_id);
	echo get_label('Copy event data from') . ': <select id="form-copy" onChange="copyEvent()"><option value="0"></option>';
	while ($row = $query->next())
	{
		echo '<option value="' . $row[0] . '">';
		echo $row[1] . format_date(' D F d H:i', $row[2], $row[3]);
		echo '</option>';
	}
	echo '</select>';
	echo '</td></tr></table>';
?>	
	<script>
	function multipleChange()
	{
		if ($('#form-multiple').attr('checked'))
		{
			$('#form-multiple_date').show();
			$('#form-single_date').hide();
		}
		else
		{
			$('#form-multiple_date').hide();
			$('#form-single_date').show();
		}
	}
	
	var old_address_value = "<?php echo $selected_address; ?>";
	function newAddressChange()
	{
		var text = $("#form-new_addr").val();
		if ($("#form-name").val() == old_address_value)
		{
			$("#form-name").val(text);
		}
		old_address_value = text;
	}
	
	function addressClick()
	{
		var text = '';
		if ($("#form-addr_id").val() <= 0)
		{
			$("#form-new_addr_div").show();
		}
		else
		{
			$("#form-new_addr_div").hide();
			text = $("#form-addr_id option:selected").text();
		}
		
		if ($("#form-name").val() == old_address_value)
		{
			$("#form-name").val(text);
		}
		old_address_value = text;
	}
	addressClick();
	
	var rounds = [];
	var roundRow = 
		'<tr><td width="48"><a href="javascript:deleteRound({num})" title="<?php echo get_label('Delete round'); ?>"><img src="images/delete.png"></a></td>' +
		'<td width="90"><input id="form-round{num}_name" class="short" onchange="setRoundValues({num})"></td>' +
		'<td><?php show_scoring_select($event->club_id, $event->scoring_id, 'setRoundValues({num})', get_label('Scoring system'), 'form-round{num}_scoring', false); ?></td>' +
		'<td width="70"><input id="form-round{num}_weight" onchange="setRoundValues({num})"></td>' +
		'<td width="70"><input id="form-round{num}_games" onchange="setRoundValues({num})"></td></tr>';
	
	function refreshRounds()
	{
		var html = '<table width="100%" class="transp">';
		for (var i = 0; i < rounds.length; ++i)
		{
			html += roundRow.replace(new RegExp('\\{num\\}', 'g'), i);
		}
		html += '</table>';
		$('#form-rounds').html(html);
		
		for (var i = 0; i < rounds.length; ++i)
		{
			var round = rounds[i];
			$('#form-round' + i + '_name').val(round.name);
			$('#form-round' + i + '_scoring').val(round.scoring_id);
			$('#form-round' + i + '_weight').spinner({ step:0.1, max:100, min:0.1, change:setAllRoundValues }).width(30).val(round.scoring_weight);
			$('#form-round' + i + '_games').spinner({ step:1, max:1000, min:0, change:setAllRoundValues }).width(30).val(round.planned_games > 0 ? round.planned_games : '');
		}
	}

	function addRound()
	{
		rounds.push({ name: "", scoring_id: <?php echo $event->scoring_id; ?>, scoring_weight: 1, planned_games: 0});
		refreshRounds();
	}

	function deleteRound(roundNumber)
	{
		rounds = rounds.slice(0, roundNumber).concat(rounds.slice(roundNumber + 1));
		refreshRounds();
	}
	
	function setRoundValues(roundNumber)
	{
		var round = rounds[roundNumber];
		round.name = $('#form-round' + roundNumber + '_name').val();
		round.scoring_id = $('#form-round' + roundNumber + '_scoring').val();
		round.scoring_weight = $('#form-round' + roundNumber + '_weight').val();
		round.planned_games = $('#form-round' + roundNumber + '_games').val();
		if (round.planned_games == 0)
		{
			$('#form-round' + roundNumber + '_games').val('');
		}
		else if (isNaN(round.planned_games))
		{
			round.planned_games = 0;
		}
	}
	
	function setAllRoundValues()
	{
		for (var i = 0; i < rounds.length; ++i)
		{
			setRoundValues(i);
		}
	}
	
	function eventGamesChange()
	{
		if ($('#form-planned_games').val() <= 0)
		{
			$('#form-planned_games').val('');
		}
	}
	
	function copyEvent()
	{
		json.get("api/ops/event.php?op=get&event_id=" + $("#form-copy").val(), function(json)
		{
			rounds = [];
			if (typeof json.rounds != "undefined")
			{
				for (var i in json.rounds)
				{
					var round = json.rounds[i];
					rounds.push(round);
				}
			}
			refreshRounds();
			
			$("#form-name").val(json.name);
			$("#form-hour").val(json.hour);
			$("#form-minute").val(json.minute);
			$("#form-duration").val(timespanToStr(json.duration));
			$("#form-addr_id").val(json.addr_id);
			$("#form-price").val(json.price);
			$("#form-rules").val(json.rules_code);
			$("#form-scoring").val(json.scoring_id);
			$('#form-scoring_weight').val(json.scoring_weight);
			if (json.planned_games > 0)
			{
				$('#form-planned_games').val(json.planned_games);
			}
			else
			{
				$('#form-planned_games').val('');
			}
			$("#form-notes").val(json.notes);
			$("#form-reg_att").prop('checked', (json.flags & <?php echo EVENT_FLAG_REG_ON_ATTEND; ?>) != 0);
			$("#form-pwd_req").prop('checked', (json.flags & <?php echo EVENT_FLAG_PWD_REQUIRED; ?>) != 0);
			$("#form-all_mod").prop('checked', (json.flags & <?php echo EVENT_FLAG_ALL_MODERATE; ?>) != 0);
			mr.setLangs(json.langs, "form-");
			addressClick();
		});
		$("#form-copy").val(0);
	}
	
	function commit(onSuccess)
	{
		var _langs = mr.getLangs('form-');
		var _addr = $("#form-addr_id").val();
		
		var _flags = 0;
		if ($("#form-reg_att").attr('checked')) _flags |= <?php echo EVENT_FLAG_REG_ON_ATTEND; ?>;
		if ($("#form-pwd_req").attr('checked')) _flags |= <?php echo EVENT_FLAG_PWD_REQUIRED; ?>;
		if ($("#form-all_mod").attr('checked')) _flags |= <?php echo EVENT_FLAG_ALL_MODERATE; ?>;
		
		var params =
		{
			op: "create"
			, club_id: <?php echo $club_id; ?>
			, name: $("#form-name").val()
			, hour: $("#form-hour").val()
			, minute: $("#form-minute").val()
			, duration: strToTimespan($("#form-duration").val())
			, price: $("#form-price").val()
			, address_id: _addr
			, rules_code: $("#form-rules").val()
			, scoring_id: $("#form-scoring").val()
			, scoring_weight: $("#form-scoring_weight").val()
			, planned_games: $("#form-planned_games").val()
			, notes: $("#form-notes").val()
			, flags: _flags
			, langs: _langs
			, rounds: rounds
		};
		
		if (_addr <= 0)
		{
			params['address'] = $("#form-new_addr").val();
			params['country'] = $("#form-country").val();
			params['city'] = $("#form-city").val();
		}
		
		if ($('#form-multiple').attr('checked'))
		{
			var weekdays = 0;
			if ($("#form-wd0").attr('checked')) weekdays |= <?php echo WEEK_FLAG_SUN; ?>;
			if ($("#form-wd1").attr('checked')) weekdays |= <?php echo WEEK_FLAG_MON; ?>;
			if ($("#form-wd2").attr('checked')) weekdays |= <?php echo WEEK_FLAG_TUE; ?>;
			if ($("#form-wd3").attr('checked')) weekdays |= <?php echo WEEK_FLAG_WED; ?>;
			if ($("#form-wd4").attr('checked')) weekdays |= <?php echo WEEK_FLAG_THU; ?>;
			if ($("#form-wd5").attr('checked')) weekdays |= <?php echo WEEK_FLAG_FRI; ?>;
			if ($("#form-wd6").attr('checked')) weekdays |= <?php echo WEEK_FLAG_SAT; ?>;
			
			params['weekdays'] = weekdays;
			params['month'] = $("#form-from_month").val();
			params['day'] = $("#form-from_day").val();
			params['year'] = $("#form-from_year").val();
			params['to_month'] = $("#form-to_month").val();
			params['to_day'] = $("#form-to_day").val();
			params['to_year'] = $("#form-to_year").val();
		}
		else
		{
			params['month'] = $("#form-month").val();
			params['day'] = $("#form-day").val();
			params['year'] = $("#form-year").val();
		}
		
		json.post("api/ops/event.php", params, onSuccess);
	}
	
	function checkDuration()
	{
		$("#dlg-ok").button("option", "disabled", strToTimespan($("#form-duration").val()) <= 0);
	}
	
	$('#form-scoring_weight').spinner({ step:0.1, max:100, min:0.1 }).width(30);
	$('#form-planned_games').spinner({ step:1, max:1000, min:0, change:eventGamesChange }).width(30);
	refreshRounds();
	
	</script>
<?php
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<error=' . $e->getMessage() . '>';
}

?>