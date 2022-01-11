<?php
/**
 * @author Anton SH <phpinstall@gmail.com>
 */
require_once($_SERVER['DOCUMENT_ROOT'] . "/bitrix/modules/main/include/prolog_before.php");

(new \ASH\Integration\API\Controller)->process();