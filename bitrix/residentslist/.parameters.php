<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$arComponentParameters = [
    "PARAMETERS" => [
        "PAGE_SIZE" => [
            "PARENT" => "BASE",
            "NAME" => "Количество элементов на странице",
            "TYPE" => "STRING",
            "DEFAULT" => "3",
        ],
        "CACHE_TIME" => [
            "DEFAULT" => 3600,
        ],
    ],
]; 