<?php
/**
 * Created by PhpStorm.
 * User: lewis
 * Date: 13/03/15
 * Time: 13:51
 */

function getEditFieldHTML($module, $fieldname, $aow_field, $view = 'EditView', $id = '', $alt_type = '', $currency_id = '')
{

    global $current_language, $app_strings, $app_list_strings, $current_user, $beanFiles, $beanList;

    $value = getFieldValueFromModule($fieldname, $module, $id);
    $bean = BeanFactory::getBean($module,$id);
    // use the mod_strings for this module
    $mod_strings = return_module_language($current_language, $module);

    // set the filename for this control
    $file = create_cache_directory('include/InlineEditing/') . $module . $view . $alt_type . $fieldname . '.tpl';

    if (!is_file($file)
        || inDeveloperMode()
        || !empty($_SESSION['developerMode'])
    ) {

        if (!isset($vardef)) {
            require_once($beanFiles[$beanList[$module]]);
            $focus = new $beanList[$module];
            $vardef = $focus->getFieldDefinition($fieldname);
        }

        $displayParams = array();
        //$displayParams['formName'] = 'EditView';

        // if this is the id relation field, then don't have a pop-up selector.
        if ($vardef['type'] == 'relate' && $vardef['id_name'] == $vardef['name']) {
            $vardef['type'] = 'varchar';
        }

        if (isset($vardef['precision'])) unset($vardef['precision']);

        //$vardef['precision'] = $locale->getPrecedentPreference('default_currency_significant_digits', $current_user);

        //TODO Fix datetimecomebo
        //temp work around
        if ($vardef['type'] == 'datetime') {
            $vardef['type'] = 'datetimecombo';
        }

        // trim down textbox display
        if ($vardef['type'] == 'text') {
            $vardef['rows'] = 2;
            $vardef['cols'] = 32;
        }

        // create the dropdowns for the parent type fields
        if ($vardef['type'] == 'parent_type') {
            $vardef['type'] = 'enum';
        }

        if ($vardef['type'] == 'link') {
            $vardef['type'] = 'relate';
            $vardef['rname'] = 'name';
            $vardef['id_name'] = $vardef['name'] . '_id';
            if ((!isset($vardef['module']) || $vardef['module'] == '') && $focus->load_relationship($vardef['name'])) {
                $vardef['module'] = $focus->$vardef['name']->getRelatedModuleName();
            }

        }

        //check for $alt_type
        if ($alt_type != '') {
            $vardef['type'] = $alt_type;
        }

        // remove the special text entry field function 'getEmailAddressWidget'
        if (isset($vardef['function'])
            && ($vardef['function'] == 'getEmailAddressWidget'
                || $vardef['function']['name'] == 'getEmailAddressWidget')
        )
            unset($vardef['function']);

        if (isset($vardef['name']) && ($vardef['name'] == 'date_modified')) {
            $vardef['name'] = 'aow_temp_date';
        }

        // load SugarFieldHandler to render the field tpl file
        static $sfh;

        if (!isset($sfh)) {
            require_once('include/SugarFields/SugarFieldHandler.php');
            $sfh = new SugarFieldHandler();
        }

        $contents = $sfh->displaySmarty('fields', $vardef, $view, $displayParams);

        // Remove all the copyright comments
        $contents = preg_replace('/\{\*[^\}]*?\*\}/', '', $contents);

        if ($view == 'EditView' && ($vardef['type'] == 'relate' || $vardef['type'] == 'parent')) {
            $contents = str_replace('"' . $vardef['id_name'] . '"', '{/literal}"{$fields.' . $vardef['name'] . '.id_name}"{literal}', $contents);
            $contents = str_replace('"' . $vardef['name'] . '"', '{/literal}"{$fields.' . $vardef['name'] . '.name}"{literal}', $contents);
        }

        // hack to disable one of the js calls in this control
        if (isset($vardef['function']) && ($vardef['function'] == 'getCurrencyDropDown' || $vardef['function']['name'] == 'getCurrencyDropDown'))
            $contents .= "{literal}<script>function CurrencyConvertAll() { return; }</script>{/literal}";

        // Save it to the cache file
        if ($fh = @sugar_fopen($file, 'w')) {
            fputs($fh, $contents);
            fclose($fh);
        }
    }

    // Now render the template we received
    $ss = new Sugar_Smarty();

    // Create Smarty variables for the Calendar picker widget
    global $timedate;
    $time_format = $timedate->get_user_time_format();
    $date_format = $timedate->get_cal_date_format();
    $ss->assign('USER_DATEFORMAT', $timedate->get_user_date_format());
    $ss->assign('TIME_FORMAT', $time_format);
    $time_separator = ":";
    $match = array();
    if (preg_match('/\d+([^\d])\d+([^\d]*)/s', $time_format, $match)) {
        $time_separator = $match[1];
    }
    $t23 = strpos($time_format, '23') !== false ? '%H' : '%I';
    if (!isset($match[2]) || $match[2] == '') {
        $ss->assign('CALENDAR_FORMAT', $date_format . ' ' . $t23 . $time_separator . "%M");
    } else {
        $pm = $match[2] == "pm" ? "%P" : "%p";
        $ss->assign('CALENDAR_FORMAT', $date_format . ' ' . $t23 . $time_separator . "%M" . $pm);
    }

    $ss->assign('CALENDAR_FDOW', $current_user->get_first_day_of_week());

    $fieldlist = array();
    if (!isset($focus) || !($focus instanceof SugarBean))
        require_once($beanFiles[$beanList[$module]]);
    $focus = new $beanList[$module];
    // create the dropdowns for the parent type fields
    $vardefFields[$fieldname] = $focus->field_defs[$fieldname];
    if ($vardefFields[$fieldname]['type'] == 'parent_type') {
        $focus->field_defs[$fieldname]['options'] = $focus->field_defs[$vardefFields[$fieldname]['group']]['options'];
    }
    foreach ($vardefFields as $name => $properties) {
        $fieldlist[$name] = $properties;
        // fill in enums
        if (isset($fieldlist[$name]['options']) && is_string($fieldlist[$name]['options']) && isset($app_list_strings[$fieldlist[$name]['options']]))
            $fieldlist[$name]['options'] = $app_list_strings[$fieldlist[$name]['options']];
        // Bug 32626: fall back on checking the mod_strings if not in the app_list_strings
        elseif (isset($fieldlist[$name]['options']) && is_string($fieldlist[$name]['options']) && isset($mod_strings[$fieldlist[$name]['options']]))
            $fieldlist[$name]['options'] = $mod_strings[$fieldlist[$name]['options']];
        // Bug 22730: make sure all enums have the ability to select blank as the default value.
        if (!isset($fieldlist[$name]['options']['']))
            $fieldlist[$name]['options'][''] = '';
    }

    // fill in function return values
    if (!in_array($fieldname, array('email1', 'email2'))) {
        if (!empty($fieldlist[$fieldname]['function']['returns']) && $fieldlist[$fieldname]['function']['returns'] == 'html') {
            $function = $fieldlist[$fieldname]['function']['name'];
            // include various functions required in the various vardefs
            if (isset($fieldlist[$fieldname]['function']['include']) && is_file($fieldlist[$fieldname]['function']['include']))
                require_once($fieldlist[$fieldname]['function']['include']);
            $_REQUEST[$fieldname] = $value;
            $value = $function($focus, $fieldname, $value, $view);

            $value = str_ireplace($fieldname, $aow_field, $value);
        }
    }

    if ($fieldlist[$fieldname]['type'] == 'link') {
        $fieldlist[$fieldname]['id_name'] = $fieldlist[$fieldname]['name'] . '_id';

        if ((!isset($fieldlist[$fieldname]['module']) || $fieldlist[$fieldname]['module'] == '') && $focus->load_relationship($fieldlist[$fieldname]['name'])) {
            $fieldlist[$fieldname]['module'] = $focus->$fieldlist[$fieldname]['name']->getRelatedModuleName();
        }
    }

    if (isset($fieldlist[$fieldname]['name']) && ($fieldlist[$fieldname]['name'] == 'date_modified')) {
        $fieldlist[$fieldname]['name'] = 'aow_temp_date';
        $fieldlist['aow_temp_date'] = $fieldlist[$fieldname];
        $fieldname = 'aow_temp_date';
    }

    if (isset($fieldlist[$fieldname]['id_name']) && $fieldlist[$fieldname]['id_name'] != '' && $fieldlist[$fieldname]['id_name'] != $fieldlist[$fieldname]['name']) {
        if($value){
            $rel_value =  $bean->$fieldlist['test_relate_c']['id_name'];

        }
        $fieldlist[$fieldlist[$fieldname]['id_name']]['value'] = $rel_value;
        $fieldlist[$fieldname]['value'] = $value;
        $fieldlist[$fieldname]['id_name'] = $aow_field;
        $fieldlist[$fieldname]['name'] = $aow_field . '_display';
    } else if (isset($fieldlist[$fieldname]['type']) && ($fieldlist[$fieldname]['type'] == 'datetimecombo' || $fieldlist[$fieldname]['type'] == 'datetime')) {
        $value = $focus->convertField($value, $fieldlist[$fieldname]);
        if (!$value) {
            $value = date($timedate->get_date_time_format());
        }
        $fieldlist[$fieldname]['name'] = $aow_field;
        $fieldlist[$fieldname]['value'] = $value;
    } else if (isset($fieldlist[$fieldname]['type']) && ($fieldlist[$fieldname]['type'] == 'date')) {
        $value = $focus->convertField($value, $fieldlist[$fieldname]);
        $fieldlist[$fieldname]['name'] = $aow_field;
        if (empty($value) == "") {
            $value = str_replace("%", "", date($date_format));
        }
        $fieldlist[$fieldname]['value'] = $value;
    } else {
        $fieldlist[$fieldname]['value'] = $value;
        $fieldlist[$fieldname]['name'] = $aow_field;

    }

    if ($fieldlist[$fieldname]['type'] == 'currency' && $view != 'EditView') {
        static $sfh;

        if (!isset($sfh)) {
            require_once('include/SugarFields/SugarFieldHandler.php');
            $sfh = new SugarFieldHandler();
        }

        if ($currency_id != '' && !stripos($fieldname, '_USD')) {
            $userCurrencyId = $current_user->getPreference('currency');
            if ($currency_id != $userCurrencyId) {
                $currency = new Currency();
                $currency->retrieve($currency_id);
                $value = $currency->convertToDollar($value);
                $currency->retrieve($userCurrencyId);
                $value = $currency->convertFromDollar($value);
            }
        }

        $parentfieldlist[strtoupper($fieldname)] = $value;

        return ($sfh->displaySmarty($parentfieldlist, $fieldlist[$fieldname], 'ListView', $displayParams));
    }

    $ss->assign("fields", $fieldlist);
    $ss->assign("form_name", $view);
    $ss->assign("bean", $focus);

    $ss->assign("MOD", $mod_strings);
    $ss->assign("APP", $app_strings);

    return json_encode($ss->fetch($file));
}

function saveField($field, $id, $module, $value)
{

    $bean = BeanFactory::getBean($module, $id);

    if (is_object($bean) && $bean->id != "") {

        if ($bean->field_defs[$field]['type'] == "multienum") {
            $bean->$field = encodeMultienumValue($value);
        }else if ($bean->field_defs[$field]['type'] == "relate"){
            $save_field = $bean->field_defs[$field]['id_name'];
            $bean->$save_field = $value;
        }else{
            $bean->$field = $value;
        }


        $bean->save();
        return getDisplayValue($bean, $field);
    } else {
        return false;
    }

}

function getDisplayValue($bean, $field, $method = "save")
{

    if (file_exists("custom/modules/Accounts/metadata/listviewdefs.php")) {
        $metadata = require("custom/modules/Accounts/metadata/listviewdefs.php");
    } else {
        $metadata = require("modules/Accounts/metadata/listviewdefs.php");
    }

    $listViewDefs = $listViewDefs['Accounts'][strtoupper($field)];

    $fieldlist[$field] = $bean->getFieldDefinition($field);
    $fieldlist[$field] = array_merge($fieldlist[$field], $listViewDefs);

    $value = formatDisplayValue($bean, $bean->$field, $fieldlist[$field], $method);

    return $value;
}

function formatDisplayValue($bean, $value, $vardef, $method = "save")
{

    global $current_user, $app_list_strings, $timedate;

    //Fake the params so we can pass the values through the sugarwidgets to get the correct display html.

    $GLOBALS['focus'] = $bean;
    $_REQUEST['record'] = $bean->id;
    $vardef['fields']['ID'] = $bean->id;
    $vardef['fields'][strtoupper($vardef['name'])] = $value;

    // If field is of type email.
    if ($vardef['name'] == "email1" && $vardef['group'] == "email1") {

        require_once("include/generic/SugarWidgets/SugarWidgetSubPanelEmailLink.php");
        $SugarWidgetSubPanelEmailLink = new SugarWidgetSubPanelEmailLink($vardef);
        $value = $SugarWidgetSubPanelEmailLink->displayList($vardef);

    }

    //If field is of type link and name.
    if ($vardef['link'] && $vardef['type'] == "name") {

        require_once("include/generic/SugarWidgets/SugarWidgetSubPanelDetailViewLink.php");

        $vardef['module'] = $bean->module_dir;

        $SugarWidgetSubPanelDetailViewLink = new SugarWidgetSubPanelDetailViewLink($vardef);
        $value = "<b>" . $SugarWidgetSubPanelDetailViewLink->displayList($vardef) . "</b>";

    }

    //If field is of type date time or datetimecombo
    if ($vardef['type'] == "datetimecombo" || $vardef['type'] == "datetime") {


        if ($method != "save") {
            $value = convertDateUserToDB($value);
        }
        $datetime_format = $timedate->get_date_time_format();
        $datetime = DateTime::createFromFormat("Y-m-d H:i:s", $value);

        $value = $datetime->format($datetime_format);

    }

    //If field is of type bool, checkbox.
    if ($vardef['type'] == "bool") {

        require_once("include/generic/LayoutManager.php");
        $layoutManager = new LayoutManager();

        require_once("include/generic/SugarWidgets/SugarWidgetFieldbool.php");

        $SugarWidgetFieldbool = new SugarWidgetFieldbool($layoutManager);
        $value = $SugarWidgetFieldbool->displayListPlain($vardef);

    }

    //if field is of type multienum.
    if ($vardef['type'] == "multienum") {
        $value = str_replace("^", "", $value);

        $array_values = explode(",", $value);

        foreach ($array_values as $value) {
            $values[] = $app_list_strings[$vardef['options']][$value];
        }
        $value = implode(", ", $values);
    }

    //if field is of type relate.
    if ($vardef['type'] == "relate") {

        $record = $bean->$vardef['id_name'];

        $value = "<a target='_blank' class=\"listViewTdLinkS1\" href=\"index.php?action=DetailView&module=".$vardef['ext2']."&record=$record\">";

        $value .= getFieldValueFromModule($vardef['rname'],$vardef['ext2'],$record) . "</a>";
    }

    return $value;
}

function getFieldValueFromModule($fieldname, $module, $id)
{
    $bean = BeanFactory::getBean($module, $id);
    if (is_object($bean) && $bean->id != "") {
        return $bean->$fieldname;
    }

}

function convertDateUserToDB($value)
{
    global $timedate;

    $datetime_format = $timedate->get_date_time_format();
    $datetime = DateTime::createFromFormat($datetime_format, $value);

    $value = $datetime->format("Y-m-d H:i:s");
    return $value;
}
