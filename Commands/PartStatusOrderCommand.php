<?php

namespace ASH\Integration\API\Commands;

use ASH\Base\HighLoadBlock;
use ASH\Integration\API\Command;
use Bitrix\Main\Loader;

/**
 * Сохранение данных о частично собранном заказе в hl блок
 */
class PartStatusOrderCommand extends Command
{
    /*
     * Разрешено ли обновлять данные в hl блоке Y/N
     */
    const RIGHTS_UPDATE_DATA = 'N';

    public function execute($context): void
    {
        $data = \json_decode($this->context->getPost("DATA"), true);
        $orderId = $data['ORDER_ID'];
        $items = $data['ITEMS'];
        if (empty($orderId) || !is_numeric($orderId) || empty($items))
            Command::responseError('Пустые данные');;

        $hl = new HighLoadBlock("hl_part_status_order");
        $resultQueryHlPartOrderStatus = $hl->getList(["filter" => ['=UF_ORDER_ID' => $orderId]]);
        if (count($resultQueryHlPartOrderStatus) && self::RIGHTS_UPDATE_DATA == 'N') {
            Command::responseError('Заблокировано повторное изменение данных');
        }

        $arElements = [];
        Loader::includeModule('iblock');
        $order = \Bitrix\Sale\Order::load($orderId);
        if (empty($order))
            Command::responseError('Заказ не существует');
        $basket = $order->getBasket();
        $idIbOffers = \ASH\Base\Helper::getIblockId('offers');
        //Сбор части данных из заказа внутри битрикса
        $itemsFromBasket = [];
        foreach ($basket as $basketItem) {
            $key = $basketItem->getProductId();
            $itemsFromBasket[$key]['OFFER_NAME'] = $basketItem->getField('NAME');
            $itemsFromBasket[$key]['OFFER_TYPE'] = \CIBlockElement::GetProperty($idIbOffers, $basketItem->getProductId(), 'sort', 'asc', ['CODE' => 'TYPE'])->Fetch()['VALUE_ENUM'];
            $itemsFromBasket[$key]['OFFER_PRICE'] = $basketItem->getPrice();
            $itemsFromBasket[$key]['OFFER_QUANTITY'] = $basketItem->getQuantity();
        }
        //Сбор части данных из параметров входящего запроса и свойств торговых предложений внутри инфоблока сайта
        $resultOffers = \CIBlockElement::getList(
            ['SORT' => 'ASC'],
            [
                'IBLOCK_ID' => $idIbOffers,
                'PROPERTY_ID_1C' => array_column($items, 'PARENT_BOOK_ID'),
            ],
            false,
            false,
            [
                'ID',
                'IBLOCK_ID',
                'PROPERTY_ID_1C',
            ]
        );
        $resultListItem = [];
        while ($result = $resultOffers->fetch()) {
            $resultListItem[$result['PROPERTY_ID_1C_VALUE']] = $result;
        }
        foreach ($items as $item) {
            $resultOffer = !empty($resultListItem[$item['PARENT_BOOK_ID']]);
            $id = $resultListItem[$item['PARENT_BOOK_ID']]['ID'];
            $arElements[] = [
                'MAIN_ITEM_ID' => ($resultOffer) ? \CCatalogSku::GetProductInfo($id)['ID'] : null,
                'OFFER_ID' => ($resultOffer) ? $id : null,
                '1C_GUID' => $item['ID'],
                'PARENT_BOOK_ID' => $item['PARENT_BOOK_ID'],
                'COLLECT' => $item['COLLECT'],
                'NOT_FOUND' => $item['NOT_FOUND'],
                'OFFER_NAME' => ($resultOffer) ? $itemsFromBasket[$id]['OFFER_NAME'] : null,
                'OFFER_TYPE' => ($resultOffer) ? $itemsFromBasket[$id]['OFFER_TYPE'] : null,
                'OFFER_PRICE' => ($resultOffer) ? $itemsFromBasket[$id]['OFFER_PRICE'] : null,
                'OFFER_QUANTITY' => ($resultOffer) ? $itemsFromBasket[$id]['OFFER_QUANTITY'] : null,
            ];
        }

        //сохранить информацию в hl hl_part_status_order
        $data = [
            'UF_ORDER_ID' => $orderId,
            'UF_ORDER_PART_STATUS_DATA' => json_encode($arElements, JSON_UNESCAPED_UNICODE)
        ];
        if (!count($resultQueryHlPartOrderStatus)) {
            $hl->add($data);
        } else {
            $hl->upd($data, $resultQueryHlPartOrderStatus[0]['ID']);
        }

        Command::responseSuccess();
    }
}