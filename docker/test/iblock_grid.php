<?php
/** @global CMain $APPLICATION */

require(__DIR__ . "/../bitrix/header.php");
$APPLICATION->SetTitle("Тест компонента");

$APPLICATION->IncludeComponent(
    "test:iblock.grid",
    "",
    [
        "IBLOCK_TYPE" => "content",
        "IBLOCK_ID" => 1,
        "FIELD_CODES" => "ID,NAME,DATE_CREATE",
        "PROPERTY_CODES" => "",
        "CACHE_TIME" => 3600,
    ]
);

require(__DIR__ . "/../bitrix/footer.php");