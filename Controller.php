<?php

namespace ASH\Integration\API;

use Bitrix\Main\Context;
use Error;

class Controller
{
    private $context;

    public function __construct()
    {
        $this->context = Context::getCurrent()->getRequest();
    }

    public function process()
    {
        $action = $this->context->getPost("action");
        try {
            $cmd = $this->getCommand($action);
            $cmd->execute($this->context);
        } catch (Error $e) {
            Command::responseError($e->getMessage());
            exit;
        }
    }

    /**
     * @param $actions
     * @return mixed
     */
    public static function getCommand($actions)
    {
        if (empty($actions) || preg_match('/\W/', $actions))
            throw new Error('Недопустимые символы в команде или команда отсутствует');
        $class = "\ASH\Integration\API\Commands\\" . $actions . 'Command';
        if (!class_exists($class))
            throw new Error('Класс ' . $class . ' не обнаружен');
        return new $class;
    }
}