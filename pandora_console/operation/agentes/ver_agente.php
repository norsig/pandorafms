<?php

// Pandora FMS - http://pandorafms.com
// ==================================================
// Copyright (c) 2005-2010 Artica Soluciones Tecnologicas
// Please see http://pandorafms.org for full contribution list

// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation for version 2.
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.



// Load global vars
global $config;

require_once ("include/functions_gis.php");

require_javascript_file('openlayers.pandora');

enterprise_include ('operation/agentes/ver_agente.php');

check_login ();

if (is_ajax ()) {
	$get_agent_json = (bool) get_parameter ('get_agent_json');
	$get_agent_modules_json = (bool) get_parameter ('get_agent_modules_json');
	$get_agent_status_tooltip = (bool) get_parameter ("get_agent_status_tooltip");
	$get_agents_group_json = (bool) get_parameter ("get_agents_group_json");
	$get_agent_modules_json_for_multiple_agents = (bool) get_parameter("get_agent_modules_json_for_multiple_agents");
	$get_agent_modules_json_for_multiple_agents_id = (bool) get_parameter("get_agent_modules_json_for_multiple_agents_id");

	if ($get_agents_group_json) {
		$id_group = get_parameter('id_group');
		
		if($id_group > 0)
			$filter = " WHERE id_grupo = ". $id_group;
		else {
			$groups_orig = get_user_groups();

			$a = 0;
			$groups = array_keys($groups_orig);
			
			$filter = " WHERE id_grupo IN (". implode(',', $groups) .")";
		}

		$agents = get_db_all_rows_sql("SELECT id_agente, nombre FROM tagente". $filter);

		echo json_encode($agents);
		return;
	}

	if ($get_agent_json) {
		$id_agent = (int) get_parameter ('id_agent');
		
		$agent = get_db_row ('tagente', 'id_agente', $id_agent);
		
		echo json_encode ($agent);
		return;
	}
	
	if ($get_agent_modules_json_for_multiple_agents_id) {
		$idAgents = get_parameter('id_agent');
		
		$nameModules = get_db_all_rows_sql('SELECT nombre, id_agente_modulo FROM tagente_modulo WHERE id_agente IN (' . implode(',', $idAgents) . ')');
		
		echo json_encode($nameModules);
		return;
	}
	
	if ($get_agent_modules_json_for_multiple_agents) {
		$idAgents = get_parameter('id_agent');
		
		$nameModules = get_db_all_rows_sql('SELECT DISTINCT(nombre) FROM tagente_modulo t1 WHERE delete_pending = 0 AND id_agente IN (' . implode(',', $idAgents) . ') AND (SELECT count(nombre) FROM tagente_modulo t2 WHERE delete_pending = 0 AND t1.nombre = t2.nombre AND id_agente IN (' . implode(',', $idAgents) . ')) = (' . count($idAgents) . ')');
		
		$result = array();
		foreach($nameModules as $nameModule) {
			$result[] = $nameModule['nombre'];
		}
		
		echo json_encode($result);
		return;
	}

	if ($get_agent_modules_json) {
		$id_agent = (int) get_parameter ('id_agent');
		$filter = (string) get_parameter ('filter');
		$fields = (string) get_parameter ('fields');
		$indexed = (bool) get_parameter ('indexed', true);
		$agentName = (string) get_parameter ('agent_name', null);
		
		if ($agentName != null) {
				$search = array();
				$search['name'] = $agentName;
		}
		else
			$search = false;
		
		/* Get all agents if no agent was given */
		if ($id_agent == 0)
			$id_agent = array_keys (get_group_agents (array_keys (get_user_groups ()), $search, "none"));
		
		$agent_modules = get_agent_modules ($id_agent,
			($fields != '' ? explode (',', $fields) : "*"),
			($filter != '' ? $filter : false), $indexed);
			
		//Hack to translate text "any" in PHP to javascript
		//$agent_modules['any_text'] = __('Any');
		
		echo json_encode ($agent_modules);
		return;
	}
	
	if ($get_agent_status_tooltip) {
		$id_agent = (int) get_parameter ('id_agent');
		$agent = get_db_row ('tagente', 'id_agente', $id_agent);
		echo '<h3>'.$agent['nombre'].'</h3>';
		echo '<strong>'.__('Main IP').':</strong> '.$agent['direccion'].'<br />';
		echo '<strong>'.__('Group').':</strong> ';
		echo '<img src="images/groups_small/'.get_group_icon ($agent['id_grupo']).'.png" /> ';
		echo get_group_name ($agent['id_grupo']).'<br />';

		echo '<strong>'.__('Last contact').':</strong> '.human_time_comparation($agent['ultimo_contacto']).'<br />';
		echo '<strong>'.__('Last remote contact').':</strong> '.human_time_comparation($agent['ultimo_contacto_remoto']).'<br />';
		
		$sql = sprintf ('SELECT tagente_modulo.descripcion, tagente_modulo.nombre
				FROM tagente_estado, tagente_modulo 
				WHERE tagente_modulo.id_agente = %d
				AND tagente_modulo.id_tipo_modulo in (2, 6, 9, 18, 21, 100)
				AND tagente_estado.id_agente_modulo = tagente_modulo.id_agente_modulo
				AND tagente_modulo.disabled = 0 
				AND tagente_estado.estado = 1', $id_agent);
		$bad_modules = get_db_all_rows_sql ($sql);
		$sql = sprintf ('SELECT COUNT(*)
				FROM tagente_modulo
				WHERE id_agente = %d
				AND disabled = 0 
				AND id_tipo_modulo in (2, 6, 9, 18, 21, 100)', $id_agent);
		$total_modules = get_db_sql ($sql);
		
		if ($bad_modules === false)
			$size_bad_modules = 0;
		else
			$size_bad_modules = sizeof ($bad_modules);

		// Modules down
		if ($size_bad_modules > 0) {
			echo '<strong>'.__('Monitors down').':</strong> '.$size_bad_modules.' / '.$total_modules;
			echo '<ul>';
			foreach ($bad_modules as $module) {
				echo '<li>';
				$name = $module['nombre'];
				echo substr ($name, 0, 25);
				if (strlen ($name) > 25)
					echo '(...)';
				echo '</li>';
			}
			echo '</ul>';
		}

		// Alerts (if present)
		$sql = sprintf ('SELECT COUNT(talert_template_modules.id)
				FROM talert_template_modules, tagente_modulo, tagente
				WHERE tagente.id_agente = %d
				AND tagente.disabled = 0
				AND tagente.id_agente = tagente_modulo.id_agente
				AND tagente_modulo.disabled = 0
				AND tagente_modulo.id_agente_modulo = talert_template_modules.id_agent_module
				AND talert_template_modules.times_fired > 0 ',
				$id_agent);
		$alert_modules = get_db_sql ($sql);
		if ($alert_modules > 0){
			$sql = sprintf ('SELECT tagente_modulo.nombre, talert_template_modules.last_fired
				FROM talert_template_modules, tagente_modulo, tagente
				WHERE tagente.id_agente = %d
				AND tagente.disabled = 0
				AND tagente.id_agente = tagente_modulo.id_agente
				AND tagente_modulo.disabled = 0
				AND tagente_modulo.id_agente_modulo = talert_template_modules.id_agent_module
				AND talert_template_modules.times_fired > 0 ',
				$id_agent);
			$alerts = get_db_all_rows_sql ($sql);
			echo '<strong>'.__('Alerts fired').':</strong>';
			echo "<ul>";
			foreach ($alerts as $alert_item) {
				echo '<li>';
				$name = $alert_item[0];
				echo substr ($name, 0, 25);
				if (strlen ($name) > 25)
					echo '(...)';
				echo "&nbsp;";
				echo human_time_comparation($alert_item[1]);
				echo '</li>';
			}
			echo '</ul>';
		}
		
		return;
	}

	return;
}

$id_agente = (int) get_parameter ("id_agente", 0);
if (empty ($id_agente)) {
	return;
}

$agent = get_db_row ('tagente', 'id_agente', $id_agente);
// get group for this id_agente
$id_grupo = $agent['id_grupo'];
if (! give_acl ($config['id_user'], $id_grupo, "AR")) {
	audit_db ($config['id_user'], $_SERVER['REMOTE_ADDR'], "ACL Violation",
		"Trying to access (read) to agent ".get_agent_name($id_agente));
	include ("general/noaccess.php");
	return;
}

// Check for Network FLAG change request
if (isset($_GET["flag"])) {
	if ($_GET["flag"] == 1 && give_acl ($config['id_user'], $id_grupo, "AW")) {
		$sql = "UPDATE tagente_modulo SET flag=1 WHERE id_agente_modulo = ".$_GET["id_agente_modulo"];
		process_sql ($sql);
	}
}
// Check for Network FLAG change request
if (isset($_GET["flag_agent"])){
	if ($_GET["flag_agent"] == 1 && give_acl ($config['id_user'], $id_grupo, "AW")) {
		$sql ="UPDATE tagente_modulo SET flag=1 WHERE id_agente = ". $id_agente;
		process_sql ($sql);
	}
}

if ($agent["icon_path"]) {
	$icon = get_agent_icon_map($agent["id_agente"], true);
}
else {
	$icon = 'images/bricks.png';
}


$tab = get_parameter ("tab", "main");

/* Manage tab */

$managetab = "";

if (give_acl ($config['id_user'],$id_grupo, "AW")) {
	$managetab['text'] ='<a href="index.php?sec=gagente&sec2=godmode/agentes/configurar_agente&id_agente='.$id_agente.'">'
		. print_image("images/setup.png", true, array("title=" => __('Manage')))
		. '</a>';

	if ($tab == 'manage')
		$managetab['active'] = true;
	else
		$managetab['active'] = false;
}

/* Main tab */
$maintab['text'] = '<a href="index.php?sec=estado&sec2=operation/agentes/ver_agente&id_agente='.$id_agente.'">'
		. print_image("images/monitor.png", true, array("title=" => __('Main')))
		. '</a>';
		
if ($tab == 'main')
	$maintab['active'] = true;
else
	$maintab['active'] = false;
	
/* Data */
$datatab['text']= '<a href="index.php?sec=estado&sec2=operation/agentes/ver_agente&id_agente='.$id_agente.'&tab=data">'
	. print_image("images/lightbulb.png", true, array("title=" => __('Data')))
	. '</a>';

if (($tab == 'data') OR ($tab == 'data_view'))
	$datatab['active'] = true;
else
	$datatab['active'] = false;

/* Alert tab */
$alerttab['text'] = '<a href="index.php?sec=estado&sec2=operation/agentes/ver_agente&id_agente='.$id_agente.'&tab=alert">'
		. print_image("images/bell.png", true, array("title=" => __('Alerts')))
		. '</a>';
		
if ($tab == 'alert')
	$alerttab['active'] = true;
else
	$alerttab['active'] = false;
	
/* SLA view */
$slatab['text']= '<a href="index.php?sec=estado&sec2=operation/agentes/ver_agente&tab=sla&id_agente='.$id_agente.'">'
		. print_image("images/images.png", true, array("title=" => __('S.L.A.')))
		. '</a>';

if ($tab == 'sla') {
	$slatab['active'] = true;
} else {
	$slatab['active'] = false;
}

/* Inventory */
$inventorytab = enterprise_hook ('inventory_tab');

if ($inventorytab == -1)
	$inventorytab = "";

/* Collection */
$collectiontab = enterprise_hook('collection_tab');

if ($collectiontab == -1)
	$collectiontab = "";

/* Group tab */

$grouptab['text']= '<a href="index.php?sec=estado&sec2=operation/agentes/estado_agente&group_id='.$id_grupo.'">'
	. print_image("images/agents_group.png", true, array( "title=" =>  __('Group')))
	. '</a>';
	
$grouptab['active']=false;

/* GIS tab */
$gistab="";
if ($config['activate_gis']) {
	
	$gistab['text'] = '<a href="index.php?sec=estado&sec2=operation/agentes/ver_agente&tab=gis&id_agente='.$id_agente.'">'
		.print_image("images/world.png", true, array( "title=" => __('GIS data')))
		.'</a>';
	
	if ($tab == 'gis')
		$gistab['active'] = true;
	else 
		$gistab['active'] = false;
}

$onheader = array('manage' => $managetab, 'main' => $maintab, 'data' => $datatab, 'alert' => $alerttab, 'sla' => $slatab, 'inventory' => $inventorytab, 'collection' => $collectiontab, 'group' => $grouptab, 'gis' => $gistab);

print_page_header (__('Agent').'&nbsp;-&nbsp;'.mb_substr(get_agent_name($id_agente),0,25), $icon, false, "", false, $onheader);


switch ($tab) {
	case "gis":
		require ("gis_view.php");
		break;
	case "sla":
		require ("sla_view.php");
		break;
	case "manage":	
		require ("estado_generalagente.php");
		break;
	case "main":	
		require ("estado_generalagente.php");
		require ("estado_monitores.php");
		require ("alerts_status.php");
		require ("status_events.php");
		break;
	case "data_view":
		require ("datos_agente.php");
		break;
	case "data":
		require ("estado_ultimopaquete.php");
		break;
	case "alert":
		require ("alerts_status.php");
		break;
	case "inventory":
		enterprise_include ('operation/agentes/agent_inventory.php');
		break;
	case 'collection':
		enterprise_include ('operation/agentes/collection_view.php');
		break;
}

?>
