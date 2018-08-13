<?php

require_once 'include/session.php';
require_once 'include/event.php';
require_once 'include/event_mailing.php';

initiate_session();

try
{
	if (!isset($_REQUEST['events']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('events')));
	}
	$events = explode(',', $_REQUEST['events']);
	$events_count = count($events);
	if ($events_count == 0)
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('event')));
	}
	
	$event = new Event();
	$event->load($events[0]);
	if ($_profile == NULL || !$_profile->is_manager($event->club_id))
	{
		throw new FatalExc(get_label('No permissions'));
	}
	
	if ($events_count == 1)
	{
		dialog_title(get_label('Create mailing for [0]', $event->get_full_name()));
	}
	else
	{
		dialog_title(get_label('Create mailing for [0] and [1] other events', $event->get_full_name(), $events_count - 1));
	}

	$template_id = 0;
	if (isset($_REQUEST['for']))
	{
		$query = new DbQuery('SELECT id FROM email_templates WHERE club_id = ? AND default_for = ? ORDER BY id DESC', $event->club_id, $_REQUEST['for']);
		if ($row = $query->next())
		{
			list($template_id) = $row;
		}
	}
	
	echo '<table class="dialog_form" width="100%">';
	
	$query = new DbQuery('SELECT id, name FROM email_templates WHERE club_id = ? ORDER BY name', $event->club_id);
	echo '<tr><td width="120">' . get_label('Template') . ':</td><td><select id="form-template">';
	while ($row = $query->next())
	{
		list($template_id, $template_name) = $row;
		echo '<option value="' . $template_id . '">' . $template_name . '</option>';
	}
	echo '</select></td></tr>';
	
	echo '<tr><td>' . get_label('Send time') . ':</td><td>';
	echo '<select id="form-time">';
	show_option(0, $send_time, get_label('As soon as possible'));
	for ($i = 1; $i <= 30; ++$i)
	{
		show_option($i, $send_time, get_label('[0] days before the event', $i));
	}
	echo '</select></td></tr>';
	echo '<tr><td>' . get_label('To') . ':</td><td>';
	echo '<input type="checkbox" id="form-attended" checked> ' . get_label('to attending players');
	echo ' <input type="checkbox" id="form-declined" checked> ' . get_label('to declined players');
	echo ' <input type="checkbox" id="form-others" checked> ' . get_label('to other players');
	echo '</td></tr>';
	
	echo '<tr><td>' . get_label('Additional message').':</td><td><textarea id="form-message" cols="90" rows="8"></textarea></td></tr>';
	
	echo '</table>';
	

?>
	<script>
	function commit(onSuccess)
	{
		json.post("api/ops/advert.php",
		{
			op: "create"
		},
		onSuccess);
	}
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