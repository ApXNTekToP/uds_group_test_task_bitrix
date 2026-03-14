<?php

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @var array $arCurrentValues */

use Bitrix\Main\Loader;

if (!Loader::includeModule('iblock')) {
    return;
}

$arIBlockTypes = CIBlockParameters::GetIBlockTypes();

$arIBlocks = [];

$filter = [
    'ACTIVE' => 'Y',
];

if (!empty($arCurrentValues['IBLOCK_TYPE'])) {
    $filter['TYPE'] = $arCurrentValues['IBLOCK_TYPE'];
}

$rsIBlock = CIBlock::GetList(
    ['SORT' => 'ASC'],
    $filter
);

while ($arIBlock = $rsIBlock->Fetch()) {
    $arIBlocks[$arIBlock['ID']] = '[' . $arIBlock['ID'] . '] ' . $arIBlock['NAME'];
}

$arComponentParameters = [
    "PARAMETERS" => [
        "IBLOCK_TYPE" => [
            "PARENT" => "BASE",
            "NAME" => "Тип инфоблока",
            "TYPE" => "LIST",
            "VALUES" => $arIBlockTypes,
            "REFRESH" => "Y",
        ],
        "IBLOCK_ID" => [
            "PARENT" => "BASE",
            "NAME" => "Инфоблок",
            "TYPE" => "LIST",
            "VALUES" => $arIBlocks,
            "REFRESH" => "Y",
            "ADDITIONAL_VALUES" => "N",
        ],
        "FIELD_CODES" => [
            "PARENT" => "BASE",
            "NAME" => "Список полей для вывода (через запятую)",
            "TYPE" => "STRING",
            "DEFAULT" => "ID,NAME,DATE_CREATE",
        ],
        "PROPERTY_CODES" => [
            "PARENT" => "BASE",
            "NAME" => "Список свойств для вывода (через запятую)",
            "TYPE" => "STRING",
            "DEFAULT" => "",
        ],
        "CACHE_TIME" => [
            "DEFAULT" => 3600,
        ],
    ],
];