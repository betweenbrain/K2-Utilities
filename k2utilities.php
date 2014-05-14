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
jimport('joomla.environment.request');

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
		if ($this->app->isAdmin() && ($action = JRequest::getVar('k2utils')))
		{

			switch ($action)
			{
				case('updated_aliases'):

					$this->resetK2Aliases($this->getK2Items());

					break;

				case('migrate_fields'):

					$this->migrateFields($this->getK2Items());
					break;

				case('migrate_plugins'):

					$this->migratePluginsData($this->getK2Items());
					break;

				case('enable_plugins'):
					$this->enablePluginParam();
					break;

				case('rewrite_plugins_ini'):
					$this->rewritePluginsIni();
					break;

			}
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

	/**
	 * Creates and INI string from passed data
	 *
	 * @param $data
	 *
	 * @return null|string
	 */
	private function createIniString($data)
	{
		$iniString = null;
		foreach ($data as $key => $value)
		{
			$iniString .= $key . '=' . $value . PHP_EOL;
		}

		return $iniString;
	}

	/**
	 * Sets a K2 module's params to enable plugins
	 */
	private function enablePluginParam()
	{
		foreach ($this->getK2ContentModules() as $module)
		{
			$params              = $this->parseIni($module->params);
			$params['K2Plugins'] = 1;
			$this->setModuleParams($module, $params);
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
		$query = ' SELECT *
		FROM ' . $this->db->nameQuote('#__k2_items');

		$this->db->setQuery($query);
		$k2items = $this->db->loadObjectList();
		$this->checkDbError();

		return $k2items;
	}

	/**
	 * Gets the IDs and Titles of all K2 items in the database
	 *
	 * @param $categoryId
	 *
	 * @return mixed
	 */
	private function getK2ContentModules()
	{
		$query = ' SELECT *
		FROM ' . $this->db->nameQuote('#__modules') .
			' WHERE ' . $this->db->nameQuote('module') . ' = ' . $this->db->quote('mod_k2_content');

		$this->db->setQuery($query);
		$k2modules = $this->db->loadObjectList();
		$this->checkDbError();

		return $k2modules;
	}

	/**
	 * Retrieves extra field definition from item values
	 *
	 * @param $extra_field
	 *
	 * @return mixed
	 */
	private function lookupExtraField($extra_field)
	{
		$query = ' SELECT *
			 FROM ' . $this->db->nameQuote('#__k2_extra_fields') .
			' WHERE ' . $this->db->nameQuote('id') . ' = ' . $this->db->quote($extra_field->id);

		$this->db->setQuery($query);
		$k2item = $this->db->loadObject();
		$this->checkDbError();

		return $k2item;
	}

	/**
	 * Creates plugins data array based on existing extra fields and fields designated in URL
	 *
	 * ?k2utils=migrate_fields&field[itemImage]&field[imageCaption]&plugin=universal_fields
	 *
	 * @param $items
	 */
	private function migrateFields($items)
	{
		foreach ($items as $item)
		{
			$plugins = parse_ini_string($item->plugins, false, INI_SCANNER_RAW);

			if (property_exists($item, 'extra_fields'))
			{
				foreach (json_decode($item->extra_fields) as $extra_field)
				{
					$value = json_decode($this->lookupExtraField($extra_field)->value);

					if (array_key_exists($value[0]->alias, JRequest::getVar('field')))
					{
						$plugins[JRequest::getVar('plugin') . $value[0]->alias] = $extra_field->value;
					}
				}
			}

			$item->plugins = $this->createIniString($plugins);

			$this->updatePluginsData($item);

		}

	}

	/**
	 * Migrates K2 plugins data from one plugin field to another plugin field
	 *
	 * @param $items
	 *
	 * ?k2utils=migrate_plugins&newPlugin=universal_fieldsitemImage&oldPlugin=video_datavideoImageUrl
	 */
	private function migratePluginsData($items)
	{
		foreach ($items as $item)
		{
			$plugins = parse_ini_string($item->plugins, false, INI_SCANNER_RAW);

			if ($plugins[JRequest::getVar('oldPlugin')] != '')
			{
				$plugins[JRequest::getVar('newPlugin')] = $plugins[JRequest::getVar('oldPlugin')];
				$plugins[JRequest::getVar('oldPlugin')] = null;
			}

			$item->plugins = $this->createIniString($plugins);

			$this->updatePluginsData($item);

		}
	}

	/**
	 * Parses an ini string while preserving whitespace
	 *
	 * @param $ini
	 *
	 * @return array
	 */
	private function parseIni($ini)
	{
		$result = array();
		foreach (explode("\n", rtrim($ini, "\n")) as $string)
		{
			$parts             = explode("=", $string);
			$result[$parts[0]] = $parts[1];
		}

		return $result;
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

			$query = ' UPDATE ' . $this->db->nameQuote('#__k2_items') .
				' SET ' . $this->db->nameQuote('alias') . ' = ' . $this->db->quote(JFilterOutput::stringURLSafe($item->title)) .
				' WHERE ' . $this->db->nameQuote('id') . ' = ' . $this->db->quote($item->id);

			$this->db->setQuery($query);

			if ($this->db->query())
			{
				JFactory::getApplication()->enqueueMessage('Updating ' . $item->title);
			}

			$this->checkDbError();
		}
	}

	private function rewritePluginsIni()
	{
		foreach ($this->getK2Items() as $item)
		{
			$iniString = null;
			foreach (explode(PHP_EOL, rtrim($item->plugins, PHP_EOL)) as $string)
			{
				$parts = explode("=", $string);
				$iniString .= $parts[0] . '=' . $parts[1] . PHP_EOL;
			}

			$item->plugins = rtrim($iniString, PHP_EOL);

			$this->updatePluginsData($item);
		}
	}

	/**
	 * Updates a module's parameters with the passed params array
	 *
	 * @param $module object
	 * @param $params array
	 */
	private function setModuleParams($module, $params)
	{
		$query = ' UPDATE ' . $this->db->nameQuote('#__modules') .
			' SET ' . $this->db->nameQuote('params') . ' = ' . $this->db->quote($this->createIniString($params)) .
			' WHERE ' . $this->db->nameQuote('id') . ' = ' . $this->db->quote($module->id);

		$this->db->setQuery($query);

		if ($this->db->query())
		{
			JFactory::getApplication()->enqueueMessage('Updating ' . $module->title);
		}

		$this->checkDbError();

	}

	/**
	 * Updates the plugins column of the appropriate K2 item
	 *
	 * @param $item
	 */
	private function updatePluginsData($item)
	{
		$query = 'UPDATE ' . $this->db->nameQuote('#__k2_items') .
			' SET ' . $this->db->nameQuote('plugins') . ' = ' . $this->db->quote($item->plugins) .
			' WHERE ' . $this->db->nameQuote('id') . ' = ' . $this->db->quote($item->id);

		$this->db->setQuery($query);

		if ($this->db->query())
		{
			$this->app->enqueueMessage('Updating plugins data for ' . $item->title);
		}

		$this->checkDbError();
	}
}