<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

if( !CModule::includeModule("iblock") ) {
    throw new Exception('Не загружены модули необходимые для работы компонента');
}

    $arComponentParameters = array(
        'GROUPS' => array(),
        'PARAMETERS' => array()
    );


