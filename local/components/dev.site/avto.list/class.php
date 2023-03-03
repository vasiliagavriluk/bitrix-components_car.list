<?php
use Bitrix\Main\Context;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

class ExampleCompSimple extends CBitrixComponent {
        public $_infoArray;
        public $_request;
        public $arSelect = array("*"); // - список выводимых столбцов по умолчанию (все)

        /**
         * Вспомогательные функции
         */

        private function _log($arr)
        {
            echo "<pre>"; print_r($arr); echo "</pre>";
        }

        private function _checkModules()
        {
            if (!CModule::IncludeModule('iblock'))
            {
                ShowError('Модуль «Информационные блоки» не установлен');
                return;
            }
        }

        //выполняем проверку, есть ли инфо.блоки в системе
        private function _checkInfoBlock()
        {
            $_infoArray = ["JOB_TITLE","MODEL_AVTO","COMFORT_CATEGORY","BUSINESS_TRAVEL"];
            foreach ($_infoArray as &$value)
            {
                $arFilter = Array("CODE"=>$value);
                $get_info = CIBlock::GetList(Array(), $arFilter, true);
                $ar_result = $get_info->GetNext();
                if (!$ar_result)
                {
                    ShowError('Информационного блока: '.$value.", нет в системе");
                    break;
                }
            }
        }

        //выполняем проверку, есть ли параметры в get запросе
        private function _checkGetParam()
        {
            $int_param = 0;
            $request = Context::getCurrent()->getRequest();
            $result = $request->getQueryList()->toArray();
            $_GetParamArray = ["data_start","data_finish"];
            foreach ($_GetParamArray as &$value)
            {
                if (!$result[$value])
                {
                    $int_param = 1;
                    ShowError('Обязательного параметра: '.$value.", нет в get запросе");
                    break;
                }
            }
            if ($int_param == 0)
            return $result;
        }

        //ПОЛУЧАЕМ ДАННЫЕ ОБ МАШИНАХ КОТОРЫЕ ОН МОЖЕТ ИСПОЛЬЗОВАТЬ
        private function _getAvtoUser()
        {
            global $USER;
            $rsUser = CUser::GetByID($USER->GetID());
            $arUser = $rsUser->Fetch();

            //проверяем еще какие машины ему доступны по должности
            $_IBLOCK_ID = 0;
            $arSelect = Array("*");
            $arFilter = Array("IBLOCK_CODE"=>"JOB_TITLE","ID"=>$arUser["UF_JOB"]);
            $get_info = CIBlockElement::GetList(Array(), $arFilter, $arSelect);
            while($ar_fields = $get_info->GetNext())
            {
                $_IBLOCK_ID = $ar_fields["IBLOCK_ID"];
            }

            $_CarArrayID = [];
            $arFilter = Array("ID"=>$arUser["UF_JOB"]);
            $get_info = CIBlockElement::GetPropertyValues(IntVal($_IBLOCK_ID), $arFilter);
            while ($row = $get_info->Fetch()) //получили id категории комфорта
            {
                //проверяем если массив
                if (is_array($row))
                {
                    foreach ($row[4] as &$value)
                    {
                        $_CarArrayID[] = $this->_checkAllCarKat($value);
                    }
                }
                else
                {
                    $_CarArrayID[] = $this->_checkAllCarKat($row[4]);
                }
            }

            return $_CarArrayID;
        }

        // получаем список всех машин из категории комфорта
        private function _checkAllCarKat($_KAT_KOM_ID)
        {
            $_CarArrayID = [];
            $arSelect = Array("ID");
            $arFilter = Array("IBLOCK_CODE" => "MODEL_AVTO", "PROPERTY_COMFORT_CATEGORY"=>IntVal($_KAT_KOM_ID));

            $get_info = CIBlockElement::GetList(Array(), $arFilter,$arSelect);

            while($ob = $get_info->GetNext(true, false))
            {
                $_CarArrayID[] = $ob["ID"];
            }
            return $_CarArrayID;
        }

        //ПРОВЕРЯЕМ МАШИНА СВОБОДНА
        private function _checkAvtoAway($data_start, $data_finish, $ID_USER, $ID_CAR)
        {
            $d1 = strtotime($data_start); // переводит из строки в дату
            $data_start = date("d.m.Y H:i:s", $d1); // переводит в новый формат

            $d2 = strtotime($data_finish); // переводит из строки в дату
            $data_finish = date("d.m.Y H:i:s", $d2); // переводит в новый формат

            $_CarArray = [];

            $arSelect = Array("ID","IBLOCK_ID","ACTIVE","NAME","DATE_ACTIVE_FROM","DATE_ACTIVE_TO","PROPERTY_USERS","PROPERTY_CAR");
            $arFilter = Array("IBLOCK_CODE"=>"BUSINESS_TRAVEL","PROPERTY_CAR"=>IntVal($ID_CAR));

            $get_info = CIBlockElement::GetList(Array(), $arFilter,$arSelect);

            while($ob = $get_info->GetNext(true, false))
            {
                $DATE_ACTIVE_FROM   = $ob["DATE_ACTIVE_FROM"];
                $DATE_ACTIVE_TO     = $ob["DATE_ACTIVE_TO"];

                //если дата окончания активности пустая то задаем текущую дату и время
                if (!$DATE_ACTIVE_TO){ $DATE_ACTIVE_TO = date("d.m.Y H:i:s"); }

                //проверяем входит в диапазон
                if ($data_start >= $DATE_ACTIVE_FROM || $data_finish >= $DATE_ACTIVE_FROM)
                {
                    if ($data_finish <= $DATE_ACTIVE_TO || $data_start <= $DATE_ACTIVE_TO)
                    {
                        $_CarArray[] = $ob["PROPERTY_CAR_VALUE"];
                    }
                }
            }

            return $_CarArray;
        }

        private function _view($_model, $_ID)
        {
            $_Array = [];
            $arSelect = Array("*");
            $arFilter = Array("IBLOCK_CODE"=>$_model,"ID"=>IntVal($_ID));
            $get_info = CIBlockElement::GetList(Array(), $arFilter,$arSelect);

            while($ob = $get_info->GetNext(true, false))
            {
                $_Array = $ob["NAME"];
            }

            return $_Array;
        }

        private function _viewUser($_ID)
        {
            $res = Bitrix\Main\UserTable::getList(Array(
                "select"=>array('NAME','LAST_NAME'),
                "filter"=>array('ID'=>$_ID),
            ));
            while ($arUser = $res->fetch()) {
                $arUserResult = $arUser["NAME"]." ".$arUser["LAST_NAME"];
            }
            return $arUserResult;
        }

        private function _viewAll($_CarID)
        {
            $_CarArray = [];
            $arSelect = Array("ID","NAME","PROPERTY_COMFORT_CATEGORY","PROPERTY_DRIVER");
            $arFilter = Array("IBLOCK_CODE"=>"MODEL_AVTO","ID"=>IntVal($_CarID));
            $get_info = CIBlockElement::GetList(Array(), $arFilter,$arSelect);

            while($ob = $get_info->GetNext(true, false))
            {
                $ob["PROPERTY_COMFORT_CATEGORY_VALUE"] = $this->_view("COMFORT_CATEGORY",$ob["PROPERTY_COMFORT_CATEGORY_VALUE"]);
                $ob["PROPERTY_DRIVER_VALUE"] = $this->_viewUser($ob["PROPERTY_DRIVER_VALUE"]);
                $_CarArray = $ob;
            }

            return $_CarArray;
        }

        /**
         * Точка входа в компонент
         */
        public function executeComponent()
        {
            global $USER;
            if (!$USER->GetID()) { ShowError('Пользователь не авторизован'); return; }

            $this->_checkModules();
            $this->_checkInfoBlock();
            $_result_cGP = $this->_checkGetParam();

            if ($_result_cGP )
            {
                $data_start =  $_result_cGP["data_start"];
                $data_finish = $_result_cGP["data_finish"];

                //проверяем какие машины доступны для пользователя
                $_CarArrayID = $this->_getAvtoUser();

                //проверяем автомобиль свободен? если да то записываем его из массива
                $_CarArray = [];
                foreach ($_CarArrayID as &$_CarArrayValue)
                {
                    foreach ($_CarArrayValue as &$value)
                    {
                        $_Element = $this->_checkAvtoAway($data_start, $data_finish, $USER->GetID(),$value);
                        if ($_Element == NULL){ $_CarArray[] = $value; }
                    }
                }

                //получаем нужные нам данные и выводим пользователю
                $arResult = [];
                $arResult["data_start"]  = $data_start;
                $arResult["data_finish"] = $data_finish;
                $arResult["user_name"]   = $USER->GetFullName();

                foreach ($_CarArray as &$_CarID)
                {
                    $arResult["car"][] =  $this->_viewAll($_CarID);
                }

                $this->_log($arResult);
            }
           // $this->includeComponentTemplate();
        }
}



