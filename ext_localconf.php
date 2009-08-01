<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');
t3lib_extMgm::addPItoST43($_EXTKEY,'pi1/class.tx_pmkcat2menu_pi1.php','_pi1','list_type',1);

// Load and activate example hooks
$TYPO3_CONF_VARS['EXTCONF']['pmkcat2menu']['processing'][] = 'EXT:pmkcat2menu/res/class.tx_pmkcat2menu_hook.php:tx_pmkcat2menu_hook';
?>