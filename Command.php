<?php

namespace ASH\Integration\API;

use Bitrix\Main\Context;

/**
 * Базовый класс для api
 */
abstract class Command
{
    protected $secretKey;
    protected $context;

    public function __construct()
    {
        $this->context = Context::getCurrent()->getRequest();
        $this->secretKey = getenv('API_SECRET_KEY');
        $this->init();
    }

    /**
     * Основная функция для выполнения скриптов в наследуемых классах
     *
     * @param $context
     * @return mixed
     */
    abstract public function execute($context);

    private function init()
    {
        $this->isAuthorize();
        $this->verifyAuthTicket();
    }

    /**
     * Проверить авторизацию
     */
    private function isAuthorize()
    {
        //скрыто
    }

    /**
     * Проверка подписи входящего запроса
     */
    private function verifyAuthTicket()
    {
        $action = $this->context->getPost("action") ?? '';
        $data = $this->context->getPost("data") ?? '';
        $hash = $this->context->getPost("hash") ?? '';
        $checkHash = sha1($action . $data . $this->secretKey);
        if (strtoupper($checkHash) != strtoupper($hash)) {
            static::responseError('Bad hash');
            exit;
        }
    }

    /**
     * Формат ошибочного ответа
     *
     * @param $desc
     */
    public static function responseError($desc)
    {
        global $APPLICATION;
        $APPLICATION->RestartBuffer();
        echo json_encode(['status' => 'error', 'description' => $desc], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Формат успешного ответа
     */
    public static function responseSuccess()
    {
        global $APPLICATION;
        $APPLICATION->RestartBuffer();
        echo json_encode(['status' => 'success'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
