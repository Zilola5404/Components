<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Loader;
use Bitrix\Main\UI\PageNavigation;
use Bitrix\Main\Context;
use Bitrix\Main\Application;

class ResidentsListComponent extends CBitrixComponent
{
    // Константы для кодов инфоблоков по умолчанию
    const DEFAULT_RESIDENTS_IBLOCK_CODE = 'residents';
    const DEFAULT_HOMES_IBLOCK_CODE = 'homes';
    const DEFAULT_PAGE_SIZE = 3;
    const DEFAULT_CACHE_TIME = 3600;

    // ID инфоблоков
    private $residentsIblockId;
    private $homesIblockId;

    /**
     * Подготовка параметров компонента
     */
    public function onPrepareComponentParams($arParams)
    {
        // Приведение типов и установка значений по умолчанию
        $arParams['PAGE_SIZE'] = (int)($arParams['PAGE_SIZE'] ?? self::DEFAULT_PAGE_SIZE);
        $arParams['CACHE_TIME'] = (int)($arParams['CACHE_TIME'] ?? self::DEFAULT_CACHE_TIME);
        $arParams['RESIDENTS_IBLOCK_CODE'] = $arParams['RESIDENTS_IBLOCK_CODE'] ?? self::DEFAULT_RESIDENTS_IBLOCK_CODE;
        $arParams['HOMES_IBLOCK_CODE'] = $arParams['HOMES_IBLOCK_CODE'] ?? self::DEFAULT_HOMES_IBLOCK_CODE;

        return $arParams;
    }

    /**
     * Основной метод выполнения компонента
     */
    public function executeComponent()
    {
        try {
            $this->checkRequirements();
            $this->initIblockIds();
            $this->processRequest();
        } catch (Exception $e) {
            $this->handleError($e->getMessage());
        }
    }

    /**
     * Проверка необходимых модулей
     */
    private function checkRequirements(): void
    {
        if (!Loader::includeModule('iblock')) {
            throw new Exception('Модуль iblock не установлен');
        }
    }

    /**
     * Инициализация ID инфоблоков
     */
    private function initIblockIds(): void
    {
        $this->residentsIblockId = $this->getIblockIdByCode($this->arParams['RESIDENTS_IBLOCK_CODE']);
        $this->homesIblockId = $this->getIblockIdByCode($this->arParams['HOMES_IBLOCK_CODE']);

        if (!$this->residentsIblockId) {
            throw new Exception('Инфоблок жителей не найден');
        }
    }

    /**
     * Обработка запроса (обычный или AJAX)
     */
    private function processRequest(): void
    {
        $request = Context::getCurrent()->getRequest();

        if ($request->isAjaxRequest()) {
            $this->processAjaxRequest();
        } else {
            $this->processRegularRequest();
        }
    }

    /**
     * Обработка AJAX-запроса
     */
    private function processAjaxRequest(): void
    {
        $this->arResult = $this->prepareResultData();
        
        global $APPLICATION;
        $APPLICATION->RestartBuffer();
        $this->includeComponentTemplate();
        exit;
    }

    /**
     * Обработка обычного запроса с кешированием
     */
    private function processRegularRequest(): void
    {
        $currentPage = Context::getCurrent()->getRequest()->get('PAGEN_nav-residents') ?: 1;

        if ($this->startResultCache($this->arParams['CACHE_TIME'], [$currentPage])) {
            $this->arResult = $this->prepareResultData();
            $this->setResultCacheKeys(['ITEMS', 'NAV', 'CURRENT_PAGE']);
            $this->includeComponentTemplate();
        }
    }

    /**
     * Подготовка данных для результата
     */
    private function prepareResultData(): array
    {
        $navigation = $this->createNavigation();
        $residents = $this->getResidents($navigation);
        $homes = $this->getHomes($this->extractHomeIds($residents));

        return [
            'ITEMS' => $this->combineResidentsWithHomes($residents, $homes),
            'NAV' => $navigation,
            'CURRENT_PAGE' => $navigation->getCurrentPage()
        ];
    }

    /**
     * Получение ID инфоблока по коду
     */
    private function getIblockIdByCode(string $code): ?int
    {
        $iblock = CIBlock::GetList([], ['CODE' => $code, 'CHECK_PERMISSIONS' => 'N'])->Fetch();
        return $iblock ? (int)$iblock['ID'] : null;
    }

    /**
     * Создание объекта пагинации
     */
    private function createNavigation(): PageNavigation
    {
        $nav = new PageNavigation('nav-residents');
        $nav->allowAllRecords(false)
            ->setPageSize($this->arParams['PAGE_SIZE'])
            ->initFromUri();
        return $nav;
    }

    /**
     * Получение списка жителей
     */
    private function getResidents(PageNavigation $nav): array
    {
        $res = CIBlockElement::GetList(
            ['ID' => 'ASC'],
            [
                'IBLOCK_ID' => $this->residentsIblockId,
                'ACTIVE' => 'Y',
                '!PROPERTY_HOME' => false
            ],
            false,
            [
                'nPageSize' => $this->arParams['PAGE_SIZE'],
                'iNumPage' => $nav->getCurrentPage(),
                'bShowAll' => false
            ],
            ['ID', 'NAME', 'PROPERTY_FIO', 'PROPERTY_HOME']
        );

        $items = [];
        while ($item = $res->GetNext()) {
            $items[] = $item;
        }

        $nav->setRecordCount($res->NavRecordCount);
        return $items;
    }

    /**
     * Извлечение ID домов из списка жителей
     */
    private function extractHomeIds(array $residents): array
    {
        $ids = [];
        foreach ($residents as $resident) {
            if ($resident['PROPERTY_HOME_VALUE']) {
                $ids[] = $resident['PROPERTY_HOME_VALUE'];
            }
        }
        return array_unique($ids);
    }

    /**
     * Получение данных о домах
     */
    private function getHomes(array $homeIds): array
    {
        if (empty($homeIds) || !$this->homesIblockId) {
            return [];
        }

        $res = CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $this->homesIblockId,
                'ID' => $homeIds
            ],
            false,
            false,
            ['ID', 'PROPERTY_NUMBER', 'PROPERTY_STREET', 'PROPERTY_CITY']
        );

        $homes = [];
        while ($home = $res->GetNext()) {
            $homes[$home['ID']] = [
                'NUMBER' => $home['PROPERTY_NUMBER_VALUE'],
                'STREET' => $home['PROPERTY_STREET_VALUE'],
                'CITY' => $home['PROPERTY_CITY_VALUE']
            ];
        }

        return $homes;
    }

    /**
     * Связывание жителей с домами
     */
    private function combineResidentsWithHomes(array $residents, array $homes): array
    {
        $result = [];
        foreach ($residents as $resident) {
            $result[] = [
                'ID' => $resident['ID'],
                'NAME' => $resident['NAME'],
                'FIO' => $resident['PROPERTY_FIO_VALUE'],
                'HOME' => $homes[$resident['PROPERTY_HOME_VALUE']] ?? null
            ];
        }
        return $result;
    }

    /**
     * Обработка ошибок
     */
    private function handleError(string $message): void
    {
        $this->abortResultCache();
        ShowError($message);
    }
}