<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/names.php';

define('EMAIL_DEFAULT_FOR_NOTHING', 0);
define('EMAIL_DEFAULT_FOR_INVITE', 1);
define('EMAIL_DEFAULT_FOR_CANCEL', 2);
define('EMAIL_DEFAULT_FOR_CHANGE_ADDRESS', 3);
define('EMAIL_DEFAULT_FOR_CHANGE_TIME', 4);

function create_template($name, $subj, $body, $club_id)
{
	global $_profile;
	
	Db::begin();
	check_email_template_name($name, $club_id);

	Db::exec(
		get_label('email template'), 
		'INSERT INTO email_templates (club_id, name, subject, body) VALUES (?, ?, ?, ?)',
		$club_id, $name, $subj, $body);
	list ($id) = Db::record(get_label('email template'), 'SELECT LAST_INSERT_ID()');
	
	$log_details = new stdClass();
	$log_details->name = $name;
	$log_details->subj = $subj;
	$log_details->body = $body;
	db_log(LOG_OBJECT_EMAIL_TEMPLATE, 'created', $log_details, $id, $club_id);
	
	Db::commit();
}

function update_template($id, $name, $subj, $body, $default_for)
{
	global $_profile;
	
	list ($club_id) = Db::record(get_label('email template'), 'SELECT club_id FROM email_templates WHERE id = ?', $id);
	if ($_profile == NULL || !$_profile->is_club_manager($club_id))
	{
		throw new FatalExc(get_label('No permissions'));
	}
	
	check_email_template_name($name, $club_id, $id);

	Db::begin();
	if ($default_for < 0)
	{
		Db::exec(get_label('email template'), 
			'UPDATE email_templates SET name = ?, subject = ?, body = ? WHERE id = ?',
			$name, $subj, $body, $id);
	}
	else
	{
		Db::exec(get_label('email template'), 'UPDATE email_templates SET default_for = 0 WHERE club_id = ? AND default_for = ?', $club_id, $default_for);
		Db::exec(get_label('email template'), 
			'UPDATE email_templates SET name = ?, subject = ?, body = ?, default_for = ? WHERE id = ?',
			$name, $subj, $body, $default_for, $id);
	}
	if (Db::affected_rows() > 0)
	{
		$log_details = new stdClass();
		$log_details->name = $name;
		$log_details->subj = $subj;
		$log_details->body = $body;
		db_log(LOG_OBJECT_EMAIL_TEMPLATE, 'changed', $log_details, $id, $club_id);
	}
	Db::commit();
}

?>