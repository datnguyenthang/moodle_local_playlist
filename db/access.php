<?php
// This file is part of Playlist Library
//
// (c) 2023 GTN - Global Training Network GmbH <office@gtn-solutions.com>
//
// Playlist Library is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This script is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You can find the GNU General Public License at <http://www.gnu.org/licenses/>.
//
// This copyright notice MUST APPEAR in all copies of the script!

$capabilities = array(
	// can use playlist: browse files etc
	'local/playlist:use' => array(
		'captype' => 'read', // needs to be read, else guest users can't access the library
		'contextlevel' => CONTEXT_SYSTEM,
		'legacy' => array(
			'user' => CAP_ALLOW,
		),
	),
	// can review entries
	'local/playlist:reviewer' => array(
		'captype' => 'write',
		'contextlevel' => CONTEXT_SYSTEM,
		'legacy' => [],
	),
	// can manage entries and categories
	'local/playlist:creator' => array(
		'captype' => 'write',
		'contextlevel' => CONTEXT_SYSTEM,
		'legacy' => array(
			'editingteacher' => CAP_ALLOW,
			'teacher' => CAP_ALLOW,
			'manager' => CAP_ALLOW,
		),
	),
	// all rights
	'local/playlist:admin' => array(
		'captype' => 'write',
		'contextlevel' => CONTEXT_SYSTEM,
		'legacy' => array(
			'manager' => CAP_ALLOW,
		),
	),
	

	'local/playlist:addinstance' => array(
		'captype' => 'write',
		'contextlevel' => CONTEXT_SYSTEM,
		'archetypes' => array(
			'editingteacher' => CAP_ALLOW,
			'manager' => CAP_ALLOW,
		),
		'clonepermissionsfrom' => 'moodle/site:managelocal',
	),
	'local/playlist:myaddinstance' => array(
		'captype' => 'write',
		'contextlevel' => CONTEXT_SYSTEM,
		'archetypes' => array(
			'user' => CAP_PREVENT,
		),
		'clonepermissionsfrom' => 'moodle/my:managelocal',
	),
);
