<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2008 Peter Klein (peter@umloud.dk)
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * Plugin 'tx_pmkcat2menu_hook' for the 'pmkcat2menu' extension.
 *
 * @author	Peter Klein <peter@umloud.dk>
 */

class tx_pmkcat2menu_hook {
	/**
	 * Remember that the "uid" field is not renamed to "cid" yet
	 */

	/**
	 * *********************************************************
	 * Example preProcessHook function.
	 * The variable $catArr holds the complete "catArr" array.
	 * *********************************************************
	 *
	 * @param	array		complete category array
	 * @param	reference	reference to calling object
	 * @return	void
	 */
	function preProcessHook(&$catArr, &$pObj) {
		/**
		* *********************************************************************
		* Sorts the "catArr" array, based on the content of the "title" field.
		* *********************************************************************
		*/
		//uasort($catArr, array(&$this, "sortTitle"));
	}

	/**
	 * ******************************************
	 * Example singleRecordProcessHook function.
	 * The variable $menuArray holds
	 * the current "menuArray" array record.
	 * ******************************************
	 *
	 * @param	array		current menuArray
	 * @param	reference	reference to calling object
	 * @return	void
	 */
	function singleRecordProcessHook(&$menuArray, &$pObj) {
		switch ($pObj->conf['catTable']) {
			case 'tt_news_cat':
				/**
				* ***************************************************************
				* Changes the content of the title field, to the localized title
				* from the "title_lang_ol" field if the "L" GETvar is set.
	 			* ***************************************************************
				*/
				$lang = intval(t3lib_div::_GET('L'));
				if ($lang) {
					if ($menuArray['title_lang_ol']) {
						$title = explode('|',$menuArray['title_lang_ol']);
						$menuArray['title'] = trim($title[($lang-1)]);
					}
				}
				/**
				* *************************************************************
				* Overrides the normal targetId setting if the "single_pid"
				* is set in the category table.
	 			* **************************************************************
				*/
				if ($pageId=intval($menuArray['single_pid'])) {
					$menuArray['_OVERRIDE_HREF'] = $pObj->pi_linkTP_keepPIvars_url(array($pObj->conf['varCat'] => $menuArray['uid']),1,0,$pageId);
				}
			break;
			case 'tt_products_cat':
				/**
				* ********************************************************************
				* Changes the content of the title field, to the localized title
				* from the "tt_products_cat_language" table if the "L" GETvar is set.
	 			* ********************************************************************
				*/
				$lang = intval(t3lib_div::_GET('L'));
				if ($lang) {
					$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
						'title',
						'tt_products_cat_language',
						'sys_language_uid='.$lang.' AND cat_uid='.$menuArray['uid'].' AND deleted=0');
					if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)) {
						$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
						if ($row['title']) {
							$menuArray['title'] = $row['title'];
						}
					}
					$GLOBALS['TYPO3_DB']->sql_free_result($res);
				}
			break;
		}
	}

	static function sortTitle($a, $b){
		//return strcasecmp($a,$b);
		$a1 = strtolower($a['title']);
		$b1 = strtolower($b['title']);
		return ($a1 == $b1 ? 0 : ($a1 > $b1 ? +1 : -1));
	}

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/pmkcat2menu/res/class.tx_pmkcat2menu_hook.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/pmkcat2menu/res/class.tx_pmkcat2menu_hook.php']);
}
?>
