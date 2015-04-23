<?php

$EM_CONF[$_EXTKEY] = array(
	'title' => 'FAL copy/move between storages',
	'description' => 'Backport of 7.1 feature to copy/move files between storages',
	'category' => 'be',
	'author' => 'Wolfgang Klinger',
	'author_email' => 'info@plan2.net',
	'shy' => '',
	'dependencies' => '',
	'conflicts' => '',
	'priority' => 'bottom',
	'module' => '',
	'state' => 'beta',
	'internal' => '',
	'uploadfolder' => 0,
	'createDirs' => '',
	'modify_tables' => '',
	'clearCacheOnLoad' => 1,
	'lockType' => '',
	'author_company' => 'plan2net',
	'version' => '0.1.0',
	'constraints' => array(
		'depends' => array(
			'typo3' => '6.2.0-6.2.99',
		),
		'conflicts' => array(),
		'suggests' => array(),
	),
	'suggests' => array(),
);
