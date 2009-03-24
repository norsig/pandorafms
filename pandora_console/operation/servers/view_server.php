<?php

// Pandora FMS - the Flexible Monitoring System
// ============================================
// Copyright (c) 2008 Artica Soluciones Tecnológicas, http://www.artica.es
// Please see http://pandora.sourceforge.net for full contribution list

// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation for version 2.
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

// Load global vars
require_once ("include/config.php");

check_login ();

if (! give_acl ($config['id_user'], 0, "AR") && ! give_acl ($config['id_user'], 0, "AW")) {
	audit_db ($config["id_user"], $REMOTE_ADDR, "ACL Violation",
		"Trying to access Server view");
	require ("general/noaccess.php");
	return;
}

$modules_server = 0;
$total_modules_network = 0;
$total_modules_data = 0;


echo "<h2>".__('Pandora servers')." &gt; ".__('Configuration detail')."</h2>";

$total_modules = (int) get_db_sql ("SELECT COUNT(*)
				FROM tagente_modulo
				WHERE tagente_modulo.disabled = 0");
$servers = get_server_info ();
	
$table->width = '98%';
$table->size = array ();
$table->size[6] = '60';

$table->align = array ();
$table->align[1] = 'center';
$table->align[6] = 'center';

$table->head = array ();
$table->head[0] = __('Name');
$table->head[1] = __('Status');
$table->head[2] = __('Load');
$table->head[3] = __('Modules');
$table->head[4] = __('Lag');
$table->head[5] = __('Type');
$table->head[6] = __('Version');
// This will have a column of data such as "6 hours"
$table->head[7] = __('Updated');
$table->data = array ();

foreach ($servers as $server) {
	$data = array ();
	if ($server["recon_server"] == 1) {
		$data[0] = '<b><a href="index.php?sec=estado_server&amp;sec2=operation/servers/view_server_detail&amp;server_id='.$server["id_server"].'">'.$server["name"].'</a></b>';
	} else {
		$data[0] = "<b>".$server['name']."</b>";
	}

	if ($server['status'] == 0) {
		$data[1] = print_image ("images/pixel_red.png", true, array ("width" => 20, "height" => 20));
	} else {
		$data[1] = print_image ("images/pixel_green.png", true, array ("width" => 20, "height" => 20));
	}
	
	// Load
	$data[2] = print_image ("reporting/fgraph.php?tipo=progress&percent=".$server["load"]."&height=20&width=80", true);
	$data[3] = $server["modules"] . " ".__('of')." ". $server["modules_total"];
	$data[4] = '<span style="white-space:nowrap;">'.($server["lag"] == 0 ? '-' : human_time_description_raw ($server["lag"])) . " / ". $server["module_lag"].'</span>';
	$data[5] = '<span style="white-space:nowrap;">';
	if ($server['network_server'] == 1) {
		$data[5] .= print_image ("images/network.png", true, array ("title" => __('Network Server')));
	}
	if ($server['data_server'] == 1) {
		$data[5] .= print_image ("images/data.png", true, array ("title" => __('Data Server')));
	}
	if ($server['snmp_server'] == 1) {
		$data[5] .= print_image ("images/snmp.png", true, array ("title" => __('SNMP Server')));
	}
	if ($server['recon_server'] == 1) {
		$data[5] .= print_image ("images/recon.png", true, array ("title" => __('Recon Server')));
	}
	if ($server['export_server'] == 1) {
		$data[5] .= print_image ("images/database_refresh.png", true, array ("title" => __('Export Server')));
	}
	if ($server['wmi_server'] == 1) {
		$data[5] .= print_image ("images/wmi.png", true, array ("title" => __('WMI Server')));
	}
	if ($server['prediction_server'] == 1) {
		$data[5] .= print_image ("images/chart_bar.png", true, array ("title" => __('Prediction Server')));
	}
	if ($server['plugin_server'] == 1) {
		$data[5] .= print_image ("images/plugin.png", true, array ("title" => __('Plugin Server')));
	}
	if ($server['master'] == 1) {
		$data[5] .= print_image ("images/master.png", true, array ("title" => __('Master Server')));
	}
	if ($server['checksum'] == 1){
		$data[5] .= print_image ("images/binary.png", true, array ("title" => __('MD5 Check')));
	}
	$data[5] .= '</span>';
	$data[6] = $server['version'];
	$data[7] = print_timestamp ($server['keepalive'], true);
	
	array_push ($table->data, $data);
}

if (!empty ($table->data)) {
	print_table ($table);	
} else {
	echo "<div class='nf'>".__('There are no servers configured into the database')."</div>";
}
?>