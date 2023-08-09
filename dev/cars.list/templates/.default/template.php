<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

echo '<h2>$arParams</h2>';
echo "<pre>";
print_r($arParams);
echo "</pre>";

echo '<h2>$_REQUEST</h2>';
echo "<pre>";
print_r($_REQUEST);
echo "</pre>";

echo '<h2>$arResult</h2>';
echo "<pre>";
print_r($arResult);
echo "</pre>";

?>