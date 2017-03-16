<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

$arComponentDescription = array(
   "NAME" => "Веб-сервис новостей",
   "DESCRIPTION" => "Веб-сервис новостей сайта Галерея",
   "CACHE_PATH" => "Y",
   "PATH" => array(
      "ID" => "gallery",
      "CHILD" => array(
         "ID" => "webservice",
         "NAME" => "Веб-сервис новостей"
      )
   ),
);
?>