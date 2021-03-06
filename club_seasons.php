<?php

require_once 'include/pages.php';
require_once 'include/club.php';
require_once 'include/languages.php';

define("PAGE_SIZE",10);

class Page extends ClubPageBase
{
	protected function show_body()
	{
		global $_profile, $_page;
		
		check_permissions(PERMISSION_CLUB_MANAGER, $this->id);
		
		list ($count) = Db::record(get_label('season'), 'SELECT count(*) FROM seasons WHERE club_id = ?', $this->id);
		show_pages_navigation(PAGE_SIZE, $count);
		
		$query = new DbQuery('SELECT id, name, start_time, end_time FROM seasons WHERE club_id = ? ORDER BY start_time DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE, $this->id);
		
		echo '<table class="bordered" width="100%">';
		echo '<script src="ckeditor/ckeditor.js"></script>';
		echo '<tr class="darker"><th width="56">';
		echo '<button class="icon" onclick="mr.createSeason(' . $this->id . ')" title="' . get_label('Create [0]', get_label('season')) . '"><img src="images/create.png" border="0"></button></th>';
		echo '<th>' . get_label('Name') . '</th><th width="150">' . get_label('Start') . '</th><th width="150">' . get_label('End') . '</th></tr>';
		while ($row = $query->next())
		{
			list ($id, $name, $start_time, $end_time) = $row;
			echo '<tr class="light">';
			if ($this->is_manager)
			{
				echo '<td width="56" valign="top" align="center">';
				echo '<button class="icon" onclick="mr.editSeason(' . $id . ')" title="' . get_label('Edit [0]', get_label('season')) . '"><img src="images/edit.png" border="0"></button>';
				echo '<button class="icon" onclick="mr.deleteSeason(' . $id . ', \'' . get_label('Are you sure you want to delete the season?') . '\')" title="' . get_label('Delete [0]', get_label('season')) . '"><img src="images/delete.png" border="0"></button>';
				echo '</td>';
			}
			echo '<td>' . $name . '</td>';
			echo '<td>' . format_date('F d, Y', $start_time, $this->timezone) . '</td>';
			echo '<td>' . format_date('F d, Y', $end_time, $this->timezone) . '</td>';
			echo '</tr>';
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run(get_label('Seasons'));

?>