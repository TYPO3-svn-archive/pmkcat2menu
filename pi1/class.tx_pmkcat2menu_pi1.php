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
 * Plugin 'cat2menu' for the 'pmkcat2menu' extension.
 *
 * Allows the creation of a fe menu to navigate through a category tree. Works with extensions that uses pivars to select records. See ext_typoscript_setup
 *
 * @author	Peter Klein <peter@umloud.dk>
 */

require_once(PATH_tslib.'class.tslib_pibase.php');

class tx_pmkcat2menu_pi1 extends tslib_pibase {
	var $scriptRelPath = 'pi1/class.tx_pmkcat2menu_pi1.php';	// Path to this script relative to the extension dir.
	var $extKey = 'pmkcat2menu';	// The extension key.
	var $pi_checkCHash = TRUE;

	function main($content,$conf){
		$this->conf=$conf;
		$this->prefixId = $this->conf['extTrigger'];

		$this->conf['targetId'] = $this->conf['targetId'] ? intval($this->conf['targetId']) : 0;
		$this->conf['defaultCat'] = $this->conf['defaultCat'] ? intval($this->conf['defaultCat']) : 0;
		$this->conf['entryLevel'] = $this->conf['entryLevel'] ? intval($this->conf['entryLevel']) : 0;
		$this->conf['subShortcut'] = $this->conf['subShortcut'] ? intval($this->conf['subShortcut']) : 0;
		$this->conf['expAll'] = $this->conf['expAll'] ? 1 : 0;
		$this->conf['useCookie'] = $this->conf['useCookie'] ? 1 : 0;
		$this->conf['excludeUidList'] = $this->conf['excludeUidList'] ? t3lib_div::intExplode(',',$this->conf['excludeUidList']) : array();
		$this->conf['states'] = $this->conf['states'] ? explode(',',strtoupper($this->conf['states'])) : array('IFSUB','CUR','ACT');
		$this->conf['pidList'] = t3lib_div::intExplode(',',$this->conf['pidList']);
		$this->actUid = t3lib_div::_GET($this->prefixId);
		$this->actUid = intval($this->actUid[$this->conf['varCat']]);
		$this->catArr = $this->getCategoryTableContents($this->conf['catTable'],$this->conf['pidList'],'','',$this->conf['_altSortField']);

		 // Prepare user defined objects (if any) for hooks which extend this class
		$this->hookObjectsArr = array();
		if (is_array ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['pmkcat2menu']['processing'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['pmkcat2menu']['processing'] as $classRef) {
				$this->hookObjectsArr[] = &t3lib_div::getUserObj ($classRef);
			}
		}
		// Call hook for possible manipulation of catArr before generating menu
		foreach($this->hookObjectsArr as $hookObj) {
		    if (method_exists ($hookObj, 'preProcessHook')) {
		        $hookObj->preProcessHook($this->catArr, $this);
		    } 
		} 
		
		if ($this->conf['catList']) {
			$this->catList = t3lib_div::intExplode(',',$this->conf['catList']);
		}
		else {
			$this->catList = array();
			foreach ($this->catArr as $v) {
				if($v[$this->conf['parentEntry']]==0){
					$this->catList[] = $v['uid'];
				}
			}
		}

		if ($this->conf['useCookie']) {
			$cookie = $GLOBALS["TSFE"]->fe_user->getKey('ses','pmkcat2menu');
			if (!$this->actUid) {
				$this->actUid = $cookie[$this->conf['extTrigger']];
			}
			$cookie[$this->conf['extTrigger']] = $this->actUid;
			$GLOBALS["TSFE"]->fe_user->setKey('ses','pmkcat2menu',$cookie);
		}
		if (!$this->actUid && $this->conf['defaultCat']) {
			$this->actUid = $this->conf['defaultCat'];
		}

		$this->activeRootline = $this->getRootLine($this->actUid);
		$this->setActiveStates($this->catArr);

		$this->menuArray = $this->makeMenuArray($this->catList);
		$this->menuArray = $this->setEntryLevel($this->menuArray,$this->conf['entryLevel']);

		// Call hook for possible manipulation of final menuArray before returning
		foreach($this->hookObjectsArr as $hookObj) {
		    if (method_exists ($hookObj, 'postProcessHook')) {
		        $hookObj->preProcessHook($this->menuArray, $this);
		    } 
		}
		return $this->menuArray;
	}

	/**
	 * Crops array to simulate an entryLevel
	 *
	 * @param	array		menuArray
	 * @param	integer		"menulevel"
	 * @return	array		cropped menuArray
	 */
	function setEntryLevel($menuArray,$level=0) {
		$level = intval($level);
		while ($level!=0) {
			$levelContent = array();
			foreach ($menuArray as $v) {
				if ($v['_SUB_MENU'] && in_array($v['uid'],$this->activeRootline)) {
					foreach ($v['_SUB_MENU'] as $s) {
						$levelContent[] = $s;
					}
				}
			}
			$menuArray = $levelContent;
			$level--;
		}
		return $menuArray;
	}

	/**
	 * Creates fakemenu array for use in HMENU
	 *
	 * @param	array		array of category root pids
	 * @return	array		final menuarray
	 */
	function makeMenuArray($rootLine){
		foreach($rootLine as $k => $v){
			if(in_array($v, $this->conf['excludeUidList'])) continue;
			$menuArray[$k]=$this->catArr[$v];
			$menuArray[$k]['ITEM_STATE'] = $menuArray[$k]['ITEM_STATE'] ? $menuArray[$k]['ITEM_STATE'] : 'NO';
			$menuArray[$k]['_SAFE'] = TRUE;
			$menuArray[$k]['_level'] = 0;
			$this->setHref($menuArray[$k]);
			$this->uid2cid($menuArray[$k]);
			$this->makeSubMenu($menuArray[$k],1);
		}
		//debug($menuArray, 'menuArray', __LINE__, __FILE__,5);
		return $menuArray;
	}

	/**
	 * Creates submenu records
	 *
	 * @param	array		menuArray
	 * @return	void
	 */
	function makeSubMenu(&$menuArray,$level){
		$subCount = FALSE;
		foreach($this->catArr as $v){
			if(in_array($v['uid'], $this->conf['excludeUidList'])) continue;
			if($menuArray['cid']==$v[$this->conf['parentEntry']]){
				$v['ITEM_STATE'] = $v['ITEM_STATE'] ? $v['ITEM_STATE'] : 'NO';
				$v['_SAFE'] = TRUE;
				$v['_level'] = $level;
				if (in_array('IFSUB', $this->conf['states'])) {
					switch ($menuArray['ITEM_STATE']) {
						case 'NO' :
							$menuArray['ITEM_STATE'] ='IFSUB';
						break;
						case 'CUR' :
						case 'ACT' :
							$menuArray['ITEM_STATE'].='IFSUB';
						break;
					}
				}
				$this->setHref($v);
				$this->uid2cid($v);
				$this->makeSubMenu($v,$level+1);
				if ($this->conf['expAll'] || in_array($menuArray['cid'],$this->activeRootline)) {
					$menuArray['_SUB_MENU'][]=$v;
				}
				if (!$subCount && $this->conf['subShortcut']){
					$subCount = TRUE;
					$menuArray['_OVERRIDE_HREF'] = $v['_OVERRIDE_HREF'];
				}
			}
		}
	}

	/**
	 * Sets the _OVERRIDE_HREF field to id based on config vars and category
	 * Also includes hooks for processing the menuArray record
	 *
	 * @param	array		menuArray
	 * @return	void
	 */
	function setHref(&$menuArray) {
		$menuArray['_OVERRIDE_HREF'] = $this->pi_linkTP_keepPIvars_url(array($this->conf['varCat'] => $menuArray['uid']),1,0,$this->conf['targetId']);
		// Call hook for possible manipulation of each menuArray record
		foreach($this->hookObjectsArr as $hookObj) {
		    if (method_exists ($hookObj, 'singleRecordProcessHook')) {
		        $hookObj->singleRecordProcessHook($menuArray, $this);
		    }
		}
	}

	/**
	 * Menu gets "polluted" with normal pages records if an uid field is present in the fake menu records
	 * So we create a pseudo field named cid (Category ID) which holds the original uid value, and unset the original uid field.
	 *
	 * @param	array		menuRecord
	 * @return	void
	 */
	function uid2cid(&$menuRecord) {
		$menuRecord['cid'] = $menuRecord['uid'];
		$menuRecord['pid'] = $menuRecord[$this->conf['parentEntry']]; // Move parentEntry to pid field
		unset($menuRecord['uid']);
	}

	/**
	 * Sets ACT state on all records in the rootline.
	 * If CUR is selected in the list of item states, then the active record is set to CUR.
	 *
	 * @param	array		category Array
	 * @return	void
	 */
	function setActiveStates(&$catArray) {
		if (in_array('ACT', $this->conf['states'])) {
			foreach($this->activeRootline as $uid){
				$catArray[$uid]['ITEM_STATE'] = 'ACT';
			}
		}
		if (in_array('CUR', $this->conf['states'])) {
			$catArray[$this->actUid]['ITEM_STATE'] = 'CUR';
		}
	}

	/**
	 * Gets the list of rootline ids, based on current category id
	 *
	 * @param	integer		current category id
	 * @return	array		array of ids in rotline
	 */
	function getRootLine($uid) {
		$loopCheck = 0;
		$rootline = array();
		$uid = intval($uid);
		while ($uid!=0 && !in_array($uid,$this->catList) && $loopCheck<10) {	// Max 10 levels.
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($this->conf['parentEntry'], $this->conf['catTable'], 'uid='.$uid.' AND deleted=0');
			$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			$GLOBALS['TYPO3_DB']->sql_free_result($res);
			$uid = intval($row[$this->conf['parentEntry']]);
			if ($uid) {
				$rootline[] = $uid;
			}
			$loopCheck++;
		}
		$rootline[] = $this->actUid;
		return $rootline;
	}

	/**
	 * Will select all records from the "category table", $table, and return them in an array.
	 *
	 * @param	string		The name of the category table to select from.
	 * @param	array		The page(s) from where to select the category records.
	 * @param	string		Optional additional WHERE clauses put in the end of the query. DO NOT PUT IN GROUP BY, ORDER BY or LIMIT!
	 * @param	string		Optional GROUP BY field(s), if none, supply blank string.
	 * @param	string		Optional ORDER BY field(s), if none, supply blank string.
	 * @param	string		Optional LIMIT value ([begin,]max), if none, supply blank string.
	 * @return	array		The array with the category records in.
	 */
	function getCategoryTableContents($table,$pidList,$whereClause='',$groupBy='',$orderBy='',$limit='')	{
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'*',
				$table,
				'pid IN ('.implode(',',$pidList).')'.
					$this->cObj->enableFields($table).' '.
					$whereClause,	// whereClauseMightContainGroupOrderBy
				$groupBy,
				$orderBy,
				$limit
			);
		$outArr = array();
		while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
			$outArr[$row['uid']] = $row;
		}
		$GLOBALS['TYPO3_DB']->sql_free_result($res);
		return $outArr;
	}

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/pmkcat2menu/pi1/class.tx_pmkcat2menu_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/pmkcat2menu/pi1/class.tx_pmkcat2menu_pi1.php']);
}

?>