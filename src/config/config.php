<?php

return array(

	/**
	 * Enable deletion of translations
	 *
	 * @type boolean
	 */
	'delete_enabled' => true,
	/**
	 * Exclude specific groups from Laravel Translation Manager.
	 * This is useful if, for example, you want to avoid editing the official Laravel language files.
	 *
	 * @type array
	 *
	 *    array(
	 *        'pagination',
	 *        'reminders',
	 *        'validation',
	 *    )
	 */
	'exclude_groups' => array(),

	/**
	 * Exclude specific groups from Laravel Translation Manager in page edit mode.
	 * This is useful for groups that are used exclusively for non-display strings like page titles and emails
	 *
	 * @type array
	 */
	'exclude_page_edit_groups' => array(
		'page-titles',
		'reminders',
		'validation',
	),

	/**
	 * determines whether missing keys are logged
	 * @type boolean
	 */
	'log_missing_keys' => false,

	/**
	 * determines one out of how many user sessions will have a chance to log missing keys
	 * since the operation hits the database for every missing key you can limit this by setting a
	 * higher number depending on the traffic load to your site.
	 *
	 * @type int
	 *
	 * 1 - means every user
	 * 10 - means 1 in 10 users
	 * 100 - 1 in a 100 users
	 * 1000 ....
	 *
	 */
	'missing_keys_lottery' => 100, // 1 in 100 of users will have the missing translation keys logged.
);
