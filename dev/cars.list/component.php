<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Loader;

if(!Loader::includeModule("iblock"))
{
	ShowError(GetMessage("IBLOCK_MODULE_NOT_INSTALLED"));
	return;
}

$IBLOCK_CARS_ID = $arParams['IBLOCK_CARS_ID'];
if (!$IBLOCK_CARS_ID) {
    ShowError(GetMessage("T_CARS_BLOCK_IS_NOT_SELECTED"));
	return;
}
$IBLOCK_RESERVES_ID = $arParams['IBLOCK_RESERVES_ID'];
if (!$IBLOCK_RESERVES_ID) {
    ShowError(GetMessage("T_CARS_RESERVES_BLOCK_IS_NOT_SELECTED"));
	return;
}

global $USER;
if (!$USER->IsAuthorized()) {
    ShowError(GetMessage("T_CARS_USER_IS_NOT_AUTHORIZED"));
    return;
}

$begin = htmlspecialchars_decode($_REQUEST['begin']);
$end = htmlspecialchars_decode($_REQUEST['end']);

$parsed = date_parse($begin);
if ($parsed['error_count'] > 0) {
    ShowError(GetMessage("T_CARS_PARAMS_BEGIN_WRONG"));
	return;
}

$parsed = date_parse($end);
if ($parsed['error_count'] > 0) {
    ShowError(GetMessage("T_CARS_PARAMS_END_WRONG"));
	return;
}


/*
* Получаем доступные сотруднику модели автомобилей.
* 1. У текущего пользователя берем id сотрудника
* 2. Получаем должность сотрудника
* 3. Получаем категории заданные для данной должности
* 4. Получаем модели автомобилей соответствующие нужным категориям
*/

//1
$dbResult = CUser::GetList(
    "timestamp_x",
    "desc",
    ["ID" => $USER->GetID()],
    [
        'SELECT' => ["UF_EMPLOYEE"]
    ]
);
if ($row = $dbResult->GetNext()) {
    $employeeID = $row['UF_EMPLOYEE'];
}

//2
$dbResult = CIBlockElement::GetList(
    [],
    ['ID' => $employeeID],
    false,
    false,
    ['ID', 'NAME', 'PROPERTY_APPOINTMENT']
);
if ($row = $dbResult->GetNext()) {
    $employee = $row;
}

//3
$arCategoryesIDs = array();
$dbResult = CIBlockElement::GetList(
    [],
    ['ID' => $employee['PROPERTY_APPOINTMENT_VALUE']],
    false,
    false,
    ['ID', 'NAME', 'PROPERTY_CATEGORYES']
);
while ($row = $dbResult->GetNext()) {
    $arCategoryesIDs[] = $row['PROPERTY_CATEGORYES_VALUE'];
}

//4
$arModels = array();
$dbResult = CIBlockElement::GetList(
    [],
    ['PROPERTY_CATEGORY' => $arCategoryesIDs],
    false,
    false,
    ['ID', 'NAME', 'PROPERTY_CATEGORY']
);
while ($row = $dbResult->GetNext()) {
    $arModels[$row['ID']] = $row;
}

/*
* Получаем зарезервированные автомобили
*/
$arReservedCars = array();
$dbResult = CIBlockElement::GetList(
    ["SORT" => "ASC"],
    [
        "IBLOCK_ID" => $IBLOCK_RESERVES_ID,
        "ACTIVE" => 'Y',
        ">=PROPERTY_end" => $begin,
        "<=PROPERTY_begin" => $end,
    ],
    false,
    false,
    ['ID', 'PROPERTY_car']
);
while ($row = $dbResult->getNext()) {
    $arReservedCars[] = $row['PROPERTY_CAR_VALUE'];
}
$arReservedCars = array_unique($arReservedCars);


/*
* Непосредственно сам выбор автомобилей
*/
$cars = array();
$dbResult = CIBlockElement::GetList(
    ["SORT"=>"ASC"],
    [
        "IBLOCK_ID" => $IBLOCK_CARS_ID,
        "ACTIVE" => 'Y',
        "PROPERTY_MODEL" => array_keys($arModels),
        "!ID" => $arReservedCars
    ],
    false,
    false,
    [
        'IBLOCK_ID', 'ID', 'NAME', 'PROPERTY_DRIVER', 'PROPERTY_MODEL'
    ]
);
while ($row = $dbResult->getNext()) {
    $cars[] = $row;
}


/*
* Получаем названия водителей и категорий
*/
$names = array();
$arIDs = array();
foreach ($cars as $value) {
    $arIDs[] = $value['PROPERTY_DRIVER_VALUE'];
}
$arIDs = array_unique(array_merge($arIDs, $arCategoryesIDs));
$dbResult = CIBlockElement::GetList(
    [],
    ['ID' => $arIDs ],
    false,
    false,
    ['ID', 'NAME']
);
while ($row = $dbResult->getNext()) {
    $names[$row['ID']] = $row['NAME'];
}

/*
* Вывод результата компоненты
*/

$arResult['ITEMS'] = array();
foreach ($cars as $id => $value) {
    $model = $arModels[$value['PROPERTY_MODEL_VALUE']];

    $arResult['ITEMS'][] = array(
        'ID' => $value['ID'],
        'NAME' => $value['NAME'],
        'MODEL_ID' => $model['ID'],
        'MODEL_NAME' => $model['NAME'],
        'DRIVER_ID' => $value['PROPERTY_DRIVER_VALUE'],
        'DRIVER_NAME' => $names[$value['PROPERTY_DRIVER_VALUE']],
        'CATEGORY_ID' => $model['PROPERTY_CATEGORY_VALUE'],
        'CATEGORY_NAME' => $names[$model['PROPERTY_CATEGORY_VALUE']],
    );
}

$this->includeComponentTemplate();