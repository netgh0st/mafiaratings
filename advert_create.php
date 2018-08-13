<?php

require_once 'include/session.php';

initiate_session();

try
{
	dialog_title(get_label('Create [0]', get_label('advert')));

	if (!isset($_REQUEST['club']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('club')));
	}
	$club_id = $_REQUEST['club'];
	
	if (!isset($_profile->clubs[$club_id]))
	{
		throw new FatalExc(get_label('No permissions'));
	}
	$club = $_profile->clubs[$club_id];
	
	$timezone = new DateTimeZone($club->timezone);
	$start_date = new DateTime("now", $timezone);
	$end_date = new DateTime("now", $timezone);
	$end_date->add(new DateInterval('P2W'));
	
	$start_date->setTime($start_date->format('G'), 0);
	$end_date->setTime($end_date->format('G'), 0);
	
	$start_date_str = $start_date->format('Y-m-d');
	$end_date_str = $end_date->format('Y-m-d');
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="80" valign="top">' . get_label('Text').':</td><td><textarea id="form-advert" cols="93" rows="8"></textarea></td></tr>';
	echo '<tr><td valign="top">' . get_label('Starting from').':</td><td>';
	echo '<input type="text" id="form-start-date" value="' . $start_date_str . '"> <input id="form-start-hour" value="' . $start_date->format('H') . '"> : <input id="form-start-minute" value="0"></td></tr>';
	echo '<tr><td valign="top">' . get_label('Ending at').':</td><td>';
	echo '<input type="text" id="form-end-date" value="' . $end_date_str . '"> <input id="form-end-hour" value="' . $end_date->format('H') . '"> : <input id="form-end-minute" value="0"></td></tr>';

?>
	<script>
	var dateFormat = "yy-mm-dd";
	var parts = "<?php echo $end_date_str; ?>".split("-")
	var startDate = $('#form-start-date').datepicker({ maxDate:14, dateFormat:dateFormat, changeMonth: true, changeYear: true }).on("change", function() { endDate.datepicker("option", "minDate", this.value); });
	var endDate = $('#form-end-date').datepicker({ minDate:0, dateFormat:dateFormat, changeMonth: true, changeYear: true }).on("change", function() { startDate.datepicker("option", "maxDate", this.value); });
	
	$("#form-start-hour").spinner({ step:1, max:23, min:0 }).width(16);
	$("#form-end-hour").spinner({ step:1, max:23, min:0 }).width(16);
	$("#form-start-minute").spinner({ step:10, max:50, min:0, numberFormat: "d2" }).width(16);
	$("#form-end-minute").spinner({ step:10, max:50, min:0, numberFormat: "d2" }).width(16);
	
	function addZero(str)
	{
		switch (str.length)
		{
			case 0:
				return "00";
			case 1:
				return "0" + str;
		}
		return str;
	}
	
	function commit(onSuccess)
	{
		var start = startDate.val() + " " + addZero($("#form-start-hour").val()) + ":" + addZero($("#form-start-minute").val());
		var end = endDate.val() + " " + addZero($("#form-end-hour").val()) + ":" + addZero($("#form-end-minute").val());
		json.post("api/ops/advert.php",
		{
			op: "create"
			, club_id: <?php echo $club_id; ?>
			, message: $('#form-advert').val()
			, start: start
			, end: end
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