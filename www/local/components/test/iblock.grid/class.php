<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Iblock\ElementTable;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Loader;
use Bitrix\Main\SystemException;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\UI\Filter\Options as FilterOptions;
use Bitrix\Main\Grid\Options as GridOptions;
use Bitrix\Main\UI\PageNavigation;

class IblockGridComponent extends CBitrixComponent
{
    protected string $gridId = 'IBLOCK_GRID';
    protected string $filterId = 'IBLOCK_GRID_FILTER';

    public function onPrepareComponentParams($arParams): array
    {
        $arParams['IBLOCK_TYPE'] = trim((string)($arParams['IBLOCK_TYPE'] ?? ''));
        $arParams['IBLOCK_ID'] = (int)($arParams['IBLOCK_ID'] ?? 0);
        $arParams['FIELD_CODES'] = trim((string)($arParams['FIELD_CODES'] ?? 'ID,NAME,DATE_CREATE'));
        $arParams['PROPERTY_CODES'] = trim((string)($arParams['PROPERTY_CODES'] ?? ''));
        $arParams['CACHE_TIME'] = (int)($arParams['CACHE_TIME'] ?? 3600);

        return $arParams;
    }

    protected function checkModules(): void
    {
        if (!Loader::includeModule('iblock')) {
            throw new SystemException('Модуль iblock не установлен');
        }
    }

    protected function validateParams(): void
    {
        if ($this->arParams['IBLOCK_ID'] <= 0) {
            throw new ArgumentException('Не указан IBLOCK_ID');
        }
    }

    protected function parseCsv(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $items = array_map('trim', explode(',', $value));
        $items = array_filter($items, static fn($item) => $item !== '');

        return array_values(array_unique($items));
    }

    protected function getGridOptions(): GridOptions
    {
        return new GridOptions($this->gridId);
    }

    protected function getNavigation(): PageNavigation
    {
        $gridOptions = $this->getGridOptions();
        $navParams = $gridOptions->GetNavParams([
            'nPageSize' => 10,
        ]);

        $nav = new PageNavigation($this->gridId);
        $nav->allowAllRecords(true)
            ->setPageSize((int)$navParams['nPageSize'])
            ->initFromUri();

        return $nav;
    }

    protected function getFilterOptions(): FilterOptions
    {
        return new FilterOptions($this->filterId);
    }

    protected function getFilterDefinition(): array
    {
        return [
            [
                'id' => 'FIND',
                'name' => 'Поиск',
                'type' => 'string',
                'default' => true,
            ],
            [
                'id' => 'DATE_CREATE',
                'name' => 'Дата создания',
                'type' => 'date',
                'default' => true,
            ],
        ];
    }

    protected function buildOrmFilter(): array
    {
        $filter = [
            '=IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
        ];

        $filterData = $this->getFilterOptions()->getFilter($this->getFilterDefinition());

        if (!empty($filterData['FIND'])) {
            $search = trim((string)$filterData['FIND']);

            if ($search !== '') {
                $filter[] = [
                    'LOGIC' => 'OR',
                    '%NAME' => $search,
                    '%PREVIEW_TEXT' => $search,
                    '%DETAIL_TEXT' => $search,
                ];
            }
        }

        if (!empty($filterData['DATE_CREATE_from'])) {
            try {
                $filter['>=DATE_CREATE'] = new DateTime($filterData['DATE_CREATE_from'] . ' 00:00:00', 'Y-m-d H:i:s');
            } catch (\Throwable $e) {
            }
        }

        if (!empty($filterData['DATE_CREATE_to'])) {
            try {
                $filter['<=DATE_CREATE'] = new DateTime($filterData['DATE_CREATE_to'] . ' 23:59:59', 'Y-m-d H:i:s');
            } catch (\Throwable $e) {
            }
        }

        return $filter;
    }

    protected function buildOrmOrder(): array
    {
        $gridOptions = $this->getGridOptions();
        $sorting = $gridOptions->GetSorting([
            'sort' => ['ID' => 'DESC'],
            'vars' => ['by' => 'by', 'order' => 'order'],
        ]);

        return $sorting['sort'] ?? ['ID' => 'DESC'];
    }

    protected function getSelectFields(): array
    {
        $fieldCodes = $this->parseCsv($this->arParams['FIELD_CODES']);

        $defaultFields = [
            'ID',
            'NAME',
            'DATE_CREATE',
            'PREVIEW_TEXT',
            'DETAIL_TEXT',
            'IBLOCK_ID',
        ];

        $allowedFields = [
            'ID',
            'NAME',
            'CODE',
            'XML_ID',
            'ACTIVE',
            'DATE_CREATE',
            'TIMESTAMP_X',
            'SORT',
            'PREVIEW_TEXT',
            'DETAIL_TEXT',
            'PREVIEW_PICTURE',
            'DETAIL_PICTURE',
            'IBLOCK_ID',
            'IBLOCK_SECTION_ID',
            'ACTIVE_FROM',
            'ACTIVE_TO',
            'SHOW_COUNTER',
        ];

        $fieldCodes = array_filter(
            $fieldCodes,
            static fn($field) => in_array($field, $allowedFields, true)
        );

        $select = array_merge($defaultFields, $fieldCodes);

        return array_values(array_unique($select));
    }

    protected function getPropertyCodes(): array
    {
        return $this->parseCsv($this->arParams['PROPERTY_CODES']);
    }

    protected function loadPropertiesForItems(array $itemIds, array $propertyCodes): array
    {
        $result = [];

        if (empty($itemIds) || empty($propertyCodes)) {
            return $result;
        }

        $res = \CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
                'ID' => $itemIds,
            ],
            false,
            false,
            ['ID', 'IBLOCK_ID']
        );

        while ($element = $res->GetNextElement()) {
            $fields = $element->GetFields();
            $props = $element->GetProperties();

            foreach ($propertyCodes as $propertyCode) {
                if (isset($props[$propertyCode])) {
                    $value = $props[$propertyCode]['VALUE'];

                    if (is_array($value)) {
                        $value = implode(', ', $value);
                    }

                    $result[(int)$fields['ID']][$propertyCode] = $value;
                }
            }
        }

        return $result;
    }

    protected function loadItems(): array
    {
        $nav = $this->getNavigation();
        $filter = $this->buildOrmFilter();
        $order = $this->buildOrmOrder();
        $select = $this->getSelectFields();

        $totalCount = ElementTable::getCount($filter);

        $result = ElementTable::getList([
            'filter' => $filter,
            'order' => $order,
            'select' => $select,
            'offset' => $nav->getOffset(),
            'limit' => $nav->getLimit(),
        ]);

        $items = [];
        $itemIds = [];

        while ($row = $result->fetch()) {
            $row['ID'] = (int)$row['ID'];
            $items[] = $row;
            $itemIds[] = (int)$row['ID'];
        }

        $propertyCodes = $this->getPropertyCodes();
        $propertiesMap = $this->loadPropertiesForItems($itemIds, $propertyCodes);

        foreach ($items as &$item) {
            $item['PROPERTIES'] = $propertiesMap[$item['ID']] ?? [];
        }
        unset($item);

        $nav->setRecordCount($totalCount);

        return [
            'ITEMS' => $items,
            'TOTAL_COUNT' => $totalCount,
            'NAV_OBJECT' => $nav,
        ];
    }

    protected function getGridColumns(): array
    {
        $fieldCodes = $this->parseCsv($this->arParams['FIELD_CODES']);
        $propertyCodes = $this->getPropertyCodes();

        $columns = [];

        foreach ($fieldCodes as $fieldCode) {
            $columns[] = [
                'id' => $fieldCode,
                'name' => $fieldCode,
                'sort' => $fieldCode,
                'default' => true,
            ];
        }

        foreach ($propertyCodes as $propertyCode) {
            $columns[] = [
                'id' => 'PROPERTY_' . $propertyCode,
                'name' => 'PROPERTY_' . $propertyCode,
                'default' => true,
            ];
        }

        return $columns;
    }

    protected function prepareGridRows(array $items): array
    {
        $fieldCodes = $this->parseCsv($this->arParams['FIELD_CODES']);
        $propertyCodes = $this->getPropertyCodes();

        $rows = [];

        foreach ($items as $item) {
            $columns = [];

            foreach ($fieldCodes as $fieldCode) {
                $value = $item[$fieldCode] ?? '';

                if ($value instanceof DateTime) {
                    $value = $value->toString();
                } elseif (is_array($value)) {
                    $value = implode(', ', $value);
                } elseif (is_object($value) && method_exists($value, '__toString')) {
                    $value = (string)$value;
                }

                $columns[$fieldCode] = $value;
            }

            foreach ($propertyCodes as $propertyCode) {
                $value = $item['PROPERTIES'][$propertyCode] ?? '';
                $columns['PROPERTY_' . $propertyCode] = $value;
            }

            $rows[] = [
                'id' => $item['ID'],
                'columns' => $columns,
            ];
        }

        return $rows;
    }

    protected function prepareResult(): void
    {
        $data = $this->loadItems();

        $this->arResult['GRID_ID'] = $this->gridId;
        $this->arResult['FILTER_ID'] = $this->filterId;
        $this->arResult['FILTER_FIELDS'] = $this->getFilterDefinition();

        $this->arResult['ITEMS'] = $data['ITEMS'];
        $this->arResult['TOTAL_COUNT'] = $data['TOTAL_COUNT'];
        $this->arResult['NAV_OBJECT'] = $data['NAV_OBJECT'];

        $this->arResult['COLUMNS'] = $this->getGridColumns();
        $this->arResult['ROWS'] = $this->prepareGridRows($data['ITEMS']);
    }

    public function executeComponent(): void
    {
        try {
            $this->checkModules();
            $this->validateParams();

            $this->prepareResult();
            $this->includeComponentTemplate();
        } catch (\Throwable $e) {
            ShowError($e->getMessage());
        }
    }
}