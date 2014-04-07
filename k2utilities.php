<?php defined('_JEXEC') or die;

/**
 * File       k2utilities.php
 * Created    4/7/14 6:22 PM
 * Author     Matt Thomas | matt@betweenbrain.com | http://betweenbrain.com
 * Support    https://github.com/betweenbrain/
 * Copyright  Copyright (C) 2014 betweenbrain llc. All Rights Reserved.
 * License    GNU GPL v2 or later
 */

jimport('joomla.application.application');

class plgSystemK2utilities extends JPlugin
{

	/**
	 * Construct
	 *
	 * @param $subject
	 * @param $params
	 */
	function __construct(&$subject, $params)
	{
		parent::__construct($subject, $params);

		$this->app = JFactory::getApplication();
		$this->db  = JFactory::getDBO();
	}

	/**
	 * After route, do stuff ;-)
	 *
	 */
	function onAfterRoute()
	{
		if ($this->app->isAdmin() && ($action = $this->app->input->get('k2utils')))
		{

			switch ($action)
			{
				case('updated_aliases'):

					$this->resetK2Aliases($this->getK2Items());

					break;
			}

		}
	}

	/**
	 * Gets the IDs and Titles of all K2 items in the database
	 *
	 * @param $categoryId
	 *
	 * @return mixed
	 */
	private function getK2Items()
	{

		$query = $this->db->getQuery(true);
		$query->select($this->db->quoteName(array('id', 'title')))
			->from($this->db->quoteName('#__k2_items'));

		$this->db->setQuery($query);
		$k2items = $this->db->loadObjectList();
		$this->checkDbError();

		return $k2items;
	}

	/**
	 * Resets all K2 item's alias field to be the default based on title
	 *
	 * @param $items
	 */
	private function resetK2Aliases($items)
	{
		foreach ($items as $item)
		{
			$query = $this->db->getQuery(true);
			$query->update($this->db->quoteName('#__k2_items'))
				->set($this->db->quoteName('alias') . ' = ' . $this->db->quote(JFilterOutput::stringURLSafe($item->title)))
				->where($this->db->quoteName('id') . ' = ' . $this->db->quote($item->id));

			$this->db->setQuery($query);

			if ($this->db->query())
			{
				JFactory::getApplication()->enqueueMessage('Updating ' . $item->title);
			}

			$this->checkDbError();
		}
	}

	/**
	 * Checks for any database errors after running a query
	 *
	 * @param null $backtrace
	 */
	private function checkDbError($backtrace = null)
	{
		if ($error = $this->db->getErrorMsg())
		{
			if ($backtrace)
			{
				$e = new Exception();
				$error .= "\n" . $e->getTraceAsString();
			}

			JError::raiseWarning(100, $error);
		}
	}
}