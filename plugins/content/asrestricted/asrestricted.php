<?php
/**
 * @package		akeebasubs
 * @copyright	Copyright (c)2010-2011 Nicholas K. Dionysopoulos / AkeebaBackup.com
 * @license		GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
 */

defined('_JEXEC') or die();

jimport('joomla.plugin.plugin');

class plgContentAsrestricted extends JPlugin
{

	function __construct( &$subject, $params )
	{
		parent::__construct( $subject, $params );
	}


	/**
	 * Gets the level ID out of a level title. If an ID was passed, it simply returns the ID.
	 * If a non-existent subscription level is passed, it returns -1.
	 *
	 * @param $title string|int The subscription level title or ID
	 *
	 * @return int The subscription level ID
	 */
	private static function getId($title)
	{
		static $levels = null;
		
		// Don't process invalid titles
		if(empty($title)) return -1;
		
		// Fetch a list of subscription levels if we haven't done so already
		if(is_null($levels)) {
			$levels = array();
			$list = KFactory::tmp('admin::com.akeebasubs.model.levels')
				->getList();
			if(count($list)) foreach($list as $level) {
				$thisTitle = strtoupper($level->title);
				$levels[$thisTitle] = $level->id;
			}
		}
		
		$title = strtoupper($title);
		if(array_key_exists($title, $levels)) {
			// Mapping found
			return($levels[$title]);
		} elseif( (int)$title == $title ) {
			// Numeric ID passed
			return (int)$title;
		} else {
			// No match!
			return -1;
		}
	}
	
	/**
	 * Checks if a user has a valid, active subscription by that particular ID
	 * 
	 * @param $id int The subscription level ID
	 *
	 * @return bool True if there is such a subscription
	 */
	private static function isTrue($id)
	{
		static $subscriptions = null;
				
		// Don't process empty or invalid IDs
		if(empty($id) || ($id <= 0)) return false;
		
		// Don't process for guests
		$user = JFactory::getUser();
		if($user->guest) return false;
		
		if(is_null($subscriptions)) {
			$subscriptions = array();
			jimport('joomla.utilities.date');
			$jNow = new JDate();
			$list = KFactory::tmp('admin::com.akeebasubs.model.subscriptions')
				->user_id($user->id)
				->enabled(1)
				->expires_to($jNow->toMySQL());
				
			if(count($list)) foreach($list as $sub) {
				if($sub->enabled)
					if(!in_array($sub->akeebasubs_level_id, $subscriptions))
						$subscriptions[] = $sub->akeebasubs_level_id;
			}
		}
		
		return in_array($id, $subscriptions);
	}
	
	/**
	 * preg_match callback to process each match
	 */
	private static function process($match)
	{
		$ret = '';
		
		if (self::analyze($match[1])) {
			$ret = $match[2];
		}
		
		return $ret;
	}
	
	/**
	 * Analyzes a filter statement and decides if it's true or not
	 * 
	 * @return boolean
	 */
	private static function analyze($statement)
	{
		$ret = false;
		
		if ($statement) {
			// Stupid, stupid crap... ampersands replaced by &amp;...
			$statement = str_replace('&amp;&amp;', '&&', $statement);
			// First, break down to OR statements
			$items = explode("||", trim($statement) );
			for ($i=0; $i<count($items) && !$ret; $i++) {
				// Break down AND statements
				$expression = trim($items[$i]);
				$subitems = explode('&&', $expression);
				$ret = true;
				
				foreach($subitems as $item)
				{
					$item = trim($item);
					$negate = false;
					if(substr($item,0,1) == '!') {
						$negate = true;
						$item = substr($item,1);
						$item = trim($item);
					}
					$id = self::getId($item);
					$result = self::isTrue($id);
					$ret = $ret && ($negate ? !$result : $result);
				}
			}
		}
		
		return $ret;
	}

	public function onPrepareContent( &$article, &$params, $limitstart )
	{
		// Check whether the plugin should process or not
		if ( JString::strpos( $article->text, 'akeebasubs' ) === false )
		{
			return true;
		}
		
		// Search for this tag in the content
		$regex = "#{akeebasubs(.*?)}(.*?){/akeebasubs}#s";
		
		$article->text = preg_replace_callback( $regex, array('self', 'process'), $article->text );
	}
}