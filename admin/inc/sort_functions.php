<?php if(!defined('IN_GS')){ die('you cannot load this page directly.'); }
/**
 * Sort Functions
 *
 * Page sorts, sorters
 * 
 * @since  3.4
 * @author shawn_a
 * @todo  create wiki docs
 * @link http://get-simple.info/docs/sorting
 *
 * @package GetSimple
 * @subpackage Sort-Functions
 */


/////////////
// TESTING //
/////////////

// NOTES, random noise
// if we have multiple menus, then there will be no concept of parents, we would be sorting by menus
// of course we can retian a single menu or parent heirarchy for page organization and legacy support

// @todo date field filter and sorter
// probably only need sorter in core
// filter and sort by date field
// date format none = gs default,
// sort flags asc desc
// filter flags between, null start or null end lg gt
// equals mask for date match yyyy, mm, dd, no time
// datetime php min 5.3+ ,  so use unixtime evaluation
// multi sort 2 columns etc.

//
// sorters
// most use subval sort for now
// @todo any sortkey will remain in array, eg. sortbytitle and path key = 'path'

// @todo
// how to do sorters
// most will be a custom comparison sort
// will need to do case conversions
// will need date conversions
// will need multi sort
// will need multi sort using sort index faster to just sort fields you need and use the slug index
// as a sort index depending on the sort needed, this also can help avoid
// requiring a special multidimentional sorts and allowing any sort by slug pattern, and possibly cache sorts easier.
// subval sort is very inefficient in that it creates a tmp array adds sort key value to it then sorts it and then rebuids to tmp index,
// it is great but it might be possible to make it more efficient
// 
// eg uksort($array, "strnatcasecmp"); then resort main array as multi
// some more stuff here http://us2.php.net/array_multisort, supports sorting by sort array or multiple columns.
// multisort does not support local or natural sorting in php < 5.4 and 5.3 respectivly
// 
// Sorting utf-8 by locale is iffy
// strcoll() might be of some use
// 
// sorting by at least 2 columns, and fake columns such as external relationships, parent title / parent slug

// sorters need 2 callouts
// a comparison
// a preparer for the 2 values, but how can we optimize so we only run that once, preparer could be run on entire array
// a preparer will have to adjust for stuff like menuOrder 0 etc and no menuOrder sort fallbacks
// if a perparer exists then we need a sortkey

/**
 * sort by field or custom sort index after using a prepare filter on pages
 * builds the sort index using a callback function
 * 
 * If data massaging needs to be done for a sort key you should use a prepare callback 
 * This operates on the entire array and is therefore more efficient than doing
 * heavy conversions or comparisons inside a comparison callback
 * eg. 
 *   function prepare_pubDate($page,$key){
 *       return strtotime($key);
 *   }
 *   $pagesSorted = sortCustomIndexCallback($pagesArray,'pubDate','prepare_pubDate');
 * @param  array $pages   input multi array
 * @param  str $key       array key to sort by
 * @param  str $prepare   callback function for each subarray
 * @return array          returns array sorted by key or prepared sort index
 */
function sortCustomIndexCallback($array,$key=null,$prepare=null){
	$sortvalue = array();

	if(isset($prepare) && function_exists($prepare)){
		foreach($array as $sortkey=>$page){
			if(isset($key)) $sortvalue[$sortkey] = $prepare($page,$page[$key]);
			else $sortvalue[$sortkey] = $prepare($page);
		}
	}
	// _debugLog($sortvalue);
	return sortCustomIndex($array,$key,$sortvalue);
}

/**
 * sort keyed multidimensional array 
 * by sub key, or a keyed custom sort index
 * 
 * array['id'] = array[$key]
 * @since  3.4
 * @param  array $array     multidimensional array to sort
 * @param  str   $key       (optional) sub array key to sort by
 * @param  array $sortindex (optional) key value array for sorting $array
 * @param  str   $compare   (optional) comparison function
 * @return array            $array sorted by sortindex or key
 */
function sortCustomIndex($array, $key=null, $sortindex = array(), $compare = 'strnatcmp'){	
	
	if(!$sortindex && isset($key)){
		$sortindex = array_column($array,$key,'url');
		uasort($sortindex, $compare);
	} else uasort($sortindex, $compare); // sort values maintain index, use custom cmp

	// debugLog($sortindex);
	return arrayMergeSort($array,$sortindex);
	// return inPlaceKeySort($array,$sortindex);
}

/**
 * sort an array via another pre-sorted array
 * uses array_merge to sort array by another sorted keyed array or array of keys
 * @since  3.4
 * @param  array  $array keyed array to sort
 * @param  array  $sort  keyed array to sort from
 * @param  boolean $keyed true indicates sort array is already keyed, else array of keys
 * @return array         sorted array
 */
function arrayMergeSort($array,$sort,$keyed = true){
	if(!$keyed) $sort = array_flip($sort);
	return array_merge($sort, $array);
}


/**
 * tests below
 * need converting
 */


/**
 * sort array in place using sort array
 * uses tmp global variable and custom function to sort array 
 * by another sorted keyed array or array of keys
 * @param  array  $array keyed array to sort
 * @param  array  $sort  keyed array to sort from
 * @param  boolean $keyed true indicates sort array is already keyed, else array of keys
 * @return array         sorted array
 */
function inPlaceKeySort($array,$sort,$keyed = true){
	GLOBAL $sortvalue;
	if(!$keyed) $sort = array_flip($sort);
	$sortvalue = $sort;
	function custom_sort($a,$b) {
		GLOBAL $sortvalue;
		return strnatcmp($sortvalue[$a], $sortvalue[$b]);
	}

	if($sort) uksort($array, 'custom_sort');
	unset($sortvalue);
	return $array;
}


/**
 * sort multidimensional array by subarray
 * @param  str $pages array
 * @param  str $key   keyname to sort by
 * @return array      sorted array
 */
function sortKey($array,$key){
	// return subval_sort($pagesArray,$key);
	GLOBAL $sortkey;
	$sortkey = $key;
    function custom_sort($a,$b) {
    	GLOBAL $sortkey;
       	return $a[$sortkey]>$b[$sortkey];
    }
    uasort($array, "custom_sort");
    unset($sortkey);
    return $array;
}


// path = get all parents not just first
// function sortPathTitle($pages)
// function sortPath($pages)

/**
 * sort by "parent-title / page-title"
 * @param  array $pages pages array
 * @return array        sorted
 */
function sortParentTitle($pages){
	$seperator = ' - ';
	foreach ($pages as $slug => &$page) {
		$page['path'] = $page['parent'] ? $pages[$page['parent']]["title"] . $seperator : '';
		$page['path'] .= $page['title'];
	}
	return 	subval_sort($pages,'path');
}

// sort by "parent-title / page-title"
// test using multi sort 
function sortParentTitleMulti($pages){
	$sort = array();
	foreach($pages as $slug => $page) {
    	$sort['title'][$slug] = $page['title'];
    	if(isset($page['parent']) && isset($pages[$page['parent']])){
    		$sort['parenttitle'][$slug] = $page['parent'] ? $pages[$page['parent']]["title"] : '';
    	} else $sort['parenttitle'][$slug] = '';
    }
    // debugLog($sort);
	# sort by event_type desc and then title asc
	array_multisort($sort['parenttitle'], SORT_ASC, $sort['title'], SORT_ASC,$pages);
	return $pages;
}

/**
 * sorts by "parent_slug / page_slug"
 * @param  array $pages pages array
 * @return array        sorted
 */
function sortParentPath($pages){
	$seperator = '/';
	foreach ($pages as $slug => &$page) {
		$page['path'] = $page['parent'] ? $pages[$page['parent']]["url"] . $seperator : '';
		$page['path'] .= $page['url'];
	}
	return 	subval_sort($pages,'path');
}

// in progress
function sortPageFunc($pages,$func=null){
     // Define the custom sort function
	uasort ( $pages,$func);
    return $pages;
}

/**
 * reindex PAGES
 * will reset keys from url,
 * if you have a pagesarray that lost its keys after
 * using a function that does not maintain key indexes
 * @param  array  $pages PAGES, else use pagesArray
 * @return array  	     PAGES rekeyed
 */
function reindexPages($pages = array()){
	if(!$pages){
		GLOBAL $pagesArray;
		$pages = $pagesArray;
	}	
	reindexArray($pages,'url');
}

// use array_column with null key to rekey an array
function reindexArray($array,$key){
	array_column($array,null,$key);
}
